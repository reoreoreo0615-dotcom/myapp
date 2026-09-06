<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutRequest;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Repositories\WorkoutSetRepository;
use App\Services\PlateauAnalysisService;
use App\Services\WorkoutProgressionSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    public function __construct(
        private readonly WorkoutProgressionSnapshotService $snapshotService,
        private readonly WorkoutSetRepository $workoutSetRepository,
        private readonly PlateauAnalysisService $plateauAnalysisService,
    ) {}

    /**
     * Show the routine picker: choose a menu to start a workout from, or
     * jump in without one ("メニューなしの飛び込み").
     *
     * If the user already has an unfinished workout, send them back into it
     * instead of letting them start a second one in parallel — a
     * "進行中のトレーニングがあります" style redirect. See {@see redirectToActiveWorkout()}.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if ($redirect = $this->redirectToActiveWorkout($request)) {
            return $redirect;
        }

        $routines = Routine::query()
            ->where('user_id', $request->user()->id)
            ->withCount('routineExercises')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'exercises_count' => $routine->routine_exercises_count,
            ]);

        return Inertia::render('Workouts/Create', [
            'routines' => $routines,
        ]);
    }

    /**
     * Start a new workout, optionally based on a routine, and send the user
     * straight into the record screen.
     *
     * Defensive duplicate of the {@see create()} guard: a direct POST (double
     * tap, stale tab, replayed request) must not be able to create a second
     * in-progress workout even if it skipped the picker screen.
     */
    public function store(StoreWorkoutRequest $request): RedirectResponse
    {
        if ($redirect = $this->redirectToActiveWorkout($request)) {
            return $redirect;
        }

        $workout = Workout::create([
            'user_id' => $request->user()->id,
            'routine_id' => $request->validated('routine_id'),
            'performed_on' => $request->validated('performed_on') ?? now()->toDateString(),
            'started_at' => now(),
        ]);

        return Redirect::route('workouts.show', $workout);
    }

    /**
     * The record screen itself: for each exercise, the previous working
     * sets and today's target (both frozen once per session via
     * {@see WorkoutProgressionSnapshotService}), plus any sets already
     * recorded in this workout.
     *
     * Query count is intentionally independent of the number of exercises
     * shown (routine path: workout + routine + routineExercises/exercise +
     * at most one batched progression lookup + recorded sets ≈ a handful of
     * fixed queries, never one per exercise).
     */
    public function show(Request $request, Workout $workout): Response
    {
        $this->authorize('view', $workout);

        $userId = $request->user()->id;
        $targetSetsByExerciseId = [];

        if ($workout->routine_id !== null) {
            $routine = $workout->routine;

            $routineExercises = $routine->routineExercises()
                ->with('exercise')
                ->orderBy('sort_order')
                ->get();

            $exerciseModels = [];
            foreach ($routineExercises as $routineExercise) {
                $exerciseModels[$routineExercise->exercise_id] = $routineExercise->exercise;
                $targetSetsByExerciseId[$routineExercise->exercise_id] = $routineExercise->target_sets;
            }
        } else {
            $exerciseModels = Exercise::query()
                ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
                ->orderBy('muscle_group')
                ->orderBy('sort_order')
                ->get()
                ->keyBy('id')
                ->all();
        }

        $progression = $this->snapshotService->ensure($workout, $exerciseModels);

        $exercisesPayload = [];
        foreach ($exerciseModels as $exerciseId => $exercise) {
            $exercisesPayload[] = [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscle_group->value,
                'is_bodyweight' => $exercise->is_bodyweight,
                'weight_increment' => (float) $exercise->weight_increment,
                'target_rep_min' => $exercise->target_rep_min,
                'target_rep_max' => $exercise->target_rep_max,
                'target_sets' => $targetSetsByExerciseId[$exerciseId] ?? null,
            ];
        }

        // Issue #24①: この画面に表示中の種目について、停滞していないかを
        // まとめて1クエリで判定する(種目数によらずクエリ数が変わらないことを
        // test_show_page_query_count_does_not_scale_with_number_of_exercises() で担保)。
        // Issue #23②と同じ理由で、過去日のワークアウトはその日付以前のセッションだけを見て
        // 判定し、この workout 自身のセットで汚染しない。
        $plateauSessions = $this->workoutSetRepository->sessionTopSetsForExercises(
            array_keys($exerciseModels),
            $userId,
            $workout->performed_on->format('Y-m-d'),
            $workout->id,
        );

        $plateauByExerciseId = [];
        foreach ($this->plateauAnalysisService->analyze($exercisesPayload, $plateauSessions) as $row) {
            $plateauByExerciseId[$row['exercise_id']] = $row;
        }

        $recordedSets = [];
        WorkoutSet::query()
            ->where('workout_id', $workout->id)
            ->orderBy('set_number')
            ->get(['id', 'exercise_id', 'set_number', 'weight', 'reps', 'is_warmup'])
            ->each(function (WorkoutSet $set) use (&$recordedSets) {
                $recordedSets[$set->exercise_id][] = [
                    'id' => $set->id,
                    'set_number' => $set->set_number,
                    'weight' => (float) $set->weight,
                    'reps' => $set->reps,
                    'is_warmup' => (bool) $set->is_warmup,
                ];
            });

        // Issue #23①: 終了済みでも「修正する」で明示的な編集モードに入っていれば
        // セットの追加・編集・削除を許可する({@see \App\Policies\WorkoutPolicy::update()}
        // と同じ条件)。finished_at 自体・progression_snapshot はここでは変化しない。
        $isFinished = $workout->finished_at !== null;
        $isEditing = $workout->editing_started_at !== null;
        $canEditSets = ! $isFinished || $isEditing;

        return Inertia::render('Workouts/Show', [
            'workout' => [
                'id' => $workout->id,
                'performed_on' => $workout->performed_on->format('Y-m-d'),
                'started_at' => optional($workout->started_at)->toIso8601String(),
                'finished_at' => optional($workout->finished_at)->toIso8601String(),
                'routine_name' => $workout->routine->name ?? null,
            ],
            // 終了済み・非編集モードのワークアウトは閲覧専用(セットの追加・編集・削除・種目追加は不可)。
            'isFinished' => $isFinished,
            'isEditing' => $isEditing,
            'canEditSets' => $canEditSets,
            'canAddExercises' => $workout->routine_id === null && $canEditSets,
            'exercises' => array_values($exercisesPayload),
            'progression' => $progression,
            'plateau' => $plateauByExerciseId,
            'recordedSets' => $recordedSets,
        ]);
    }

    /**
     * Confirm finished_at on the workout ("トレーニング終了").
     *
     * - Already finished: idempotent no-op (guards against a double
     *   tap/retry surfacing an error instead of just landing back on the
     *   now-read-only screen).
     * - Zero sets recorded: the workout is discarded outright rather than
     *   kept as an empty history row — an empty workout has no volume, no
     *   PRs, and nothing for the progressive-overload nav to key off, so
     *   keeping it would only pollute history/aggregates (and issue #4's
     *   dashboard "training time" stat) with a zero-content session.
     *   This is an automatic cleanup, not something the user asked to
     *   delete, so it is force-deleted outright (Issue #23③'s "元に戻す"
     *   undo affordance is for intentional user deletes, not this).
     */
    public function finish(Request $request, Workout $workout): RedirectResponse
    {
        $this->authorize('finish', $workout);

        if ($workout->finished_at !== null) {
            return Redirect::route('workouts.show', $workout);
        }

        if (! $workout->workoutSets()->exists()) {
            $workout->forceDelete();

            return Redirect::route('workouts.create')
                ->with('info', 'セットが記録されなかったため、このトレーニングは破棄しました。');
        }

        $workout->update(['finished_at' => now()]);

        return Redirect::route('workouts.show', $workout)
            ->with('success', 'トレーニングを終了しました。お疲れ様でした。');
    }

    /**
     * Enter the explicit "記録を修正する" edit mode on a finished workout
     * (Issue #23①). Idempotent: re-entering while already editing does
     * nothing to the recorded editing_started_at timestamp.
     */
    public function startEditing(Request $request, Workout $workout): RedirectResponse
    {
        $this->authorize('startEditing', $workout);

        if ($workout->editing_started_at === null) {
            $workout->update(['editing_started_at' => now()]);
        }

        return Redirect::route('workouts.show', $workout)
            ->with('info', '修正モードにしました。セットの追加・編集・削除ができます。');
    }

    /**
     * End the explicit edit mode ("修正を終える"), returning a finished
     * workout to read-only. finished_at and progression_snapshot are left
     * untouched.
     */
    public function endEditing(Request $request, Workout $workout): RedirectResponse
    {
        $this->authorize('endEditing', $workout);

        $workout->update(['editing_started_at' => null]);

        return Redirect::route('workouts.show', $workout)
            ->with('success', '修正を終了しました。');
    }

    /**
     * Soft-delete a workout the user intentionally wants gone (Issue #23③).
     * Redirects to the picker screen (there is no workouts index) with an
     * immediate "元に戻す" undo link in the flash data.
     */
    public function destroy(Workout $workout): RedirectResponse
    {
        $this->authorize('delete', $workout);

        $workout->delete();

        return Redirect::route('workouts.create')
            ->with('success', 'ワークアウトを削除しました。')
            ->with('undo', route('workouts.restore', $workout));
    }

    /**
     * Restore a workout that was just soft-deleted ("元に戻す").
     */
    public function restore(Workout $workout): RedirectResponse
    {
        $this->authorize('restore', $workout);

        $workout->restore();

        return Redirect::route('workouts.show', $workout)
            ->with('success', 'ワークアウトを元に戻しました。');
    }

    /**
     * If the user already has an unfinished workout, redirect them into it
     * instead of the caller's default behaviour (picker screen / new
     * workout). Returns null when there is no active workout to redirect to.
     */
    private function redirectToActiveWorkout(Request $request): ?RedirectResponse
    {
        $activeWorkout = Workout::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('finished_at')
            ->latest('started_at')
            ->first();

        if ($activeWorkout === null) {
            return null;
        }

        return Redirect::route('workouts.show', $activeWorkout)
            ->with('info', '進行中のトレーニングがあります。続きから再開してください。');
    }
}
