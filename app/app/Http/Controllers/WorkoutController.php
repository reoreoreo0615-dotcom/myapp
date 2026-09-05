<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutRequest;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\Workout;
use App\Models\WorkoutSet;
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
            'performed_on' => now()->toDateString(),
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

        return Inertia::render('Workouts/Show', [
            'workout' => [
                'id' => $workout->id,
                'performed_on' => $workout->performed_on->format('Y-m-d'),
                'started_at' => optional($workout->started_at)->toIso8601String(),
                'finished_at' => optional($workout->finished_at)->toIso8601String(),
                'routine_name' => $workout->routine->name ?? null,
            ],
            // 終了済みのワークアウトは閲覧専用(セットの追加・編集・削除・種目追加は不可)。
            'isFinished' => $workout->finished_at !== null,
            'canAddExercises' => $workout->routine_id === null && $workout->finished_at === null,
            'exercises' => array_values($exercisesPayload),
            'progression' => $progression,
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
     */
    public function finish(Request $request, Workout $workout): RedirectResponse
    {
        $this->authorize('finish', $workout);

        if ($workout->finished_at !== null) {
            return Redirect::route('workouts.show', $workout);
        }

        if (! $workout->workoutSets()->exists()) {
            $workout->delete();

            return Redirect::route('workouts.create')
                ->with('info', 'セットが記録されなかったため、このトレーニングは破棄しました。');
        }

        $workout->update(['finished_at' => now()]);

        return Redirect::route('workouts.show', $workout)
            ->with('success', 'トレーニングを終了しました。お疲れ様でした。');
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
