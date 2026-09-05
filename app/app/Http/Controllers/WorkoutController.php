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
     */
    public function create(Request $request): Response
    {
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
     */
    public function store(StoreWorkoutRequest $request): RedirectResponse
    {
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
                'routine_name' => $workout->routine->name ?? null,
            ],
            'canAddExercises' => $workout->routine_id === null,
            'exercises' => array_values($exercisesPayload),
            'progression' => $progression,
            'recordedSets' => $recordedSets,
        ]);
    }
}
