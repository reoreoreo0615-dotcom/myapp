<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use App\Enums\ProgressionStrategyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderExercisesRequest;
use App\Http\Requests\Admin\StoreExerciseRequest;
use App\Http\Requests\Admin\UpdateExerciseRequest;
use App\Models\Exercise;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class AdminExerciseController extends Controller
{
    /**
     * MySQL driver error code for "Cannot delete or update a parent row: a
     * foreign key constraint fails" (workout_sets.exercise_id is RESTRICT).
     */
    private const FK_CONSTRAINT_VIOLATION = 1451;

    /**
     * List the default exercises (user_id = null), grouped by muscle_group
     * and ordered by sort_order. Each row carries its recorded-sets count
     * so the UI can show up front which exercises cannot be deleted.
     */
    public function index(Request $request): Response
    {
        $filters = [
            'muscle_group' => $request->query('muscle_group') ?: null,
            'equipment' => $request->query('equipment') ?: null,
        ];

        $query = Exercise::query()
            ->whereNull('user_id')
            ->withCount('workoutSets');

        if ($filters['muscle_group'] !== null) {
            $query->where('muscle_group', $filters['muscle_group']);
        }

        if ($filters['equipment'] !== null) {
            $query->where('equipment', $filters['equipment']);
        }

        $exercises = $query
            ->orderBy('muscle_group')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Exercise $exercise) => $this->toPayload($exercise))
            ->values();

        return Inertia::render('Admin/Exercises/Index', [
            'exercises' => $exercises,
            'filters' => $filters,
            'muscleGroups' => array_column(MuscleGroup::cases(), 'value'),
            'movementTypes' => array_column(MovementType::cases(), 'value'),
            'equipmentOptions' => array_column(Equipment::cases(), 'value'),
            'progressionStrategies' => array_column(ProgressionStrategyType::cases(), 'value'),
        ]);
    }

    /**
     * Create a new default exercise (user_id = null).
     */
    public function store(StoreExerciseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Exercise::create([
            'user_id' => null,
            'name' => $data['name'],
            'muscle_group' => MuscleGroup::from($data['muscle_group']),
            'movement_type' => MovementType::from($data['movement_type']),
            'equipment' => Equipment::from($data['equipment']),
            'is_bodyweight' => $data['is_bodyweight'],
            'weight_increment' => $data['weight_increment'],
            'target_rep_min' => $data['target_rep_min'],
            'target_rep_max' => $data['target_rep_max'],
            'progression_strategy' => $data['progression_strategy'] ?? ProgressionStrategyType::Double->value,
            'sort_order' => $data['sort_order'],
        ]);

        return Redirect::route('admin.exercises.index')->with('success', '種目を追加しました。');
    }

    /**
     * Update a default exercise. In particular, changing weight_increment
     * here changes the input ProgressionService uses for every future
     * "today's target" calculation for this exercise (see
     * WorkoutProgressionSnapshotService::ensure(), which reads
     * $exercise->weight_increment fresh for each new workout).
     */
    public function update(UpdateExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        abort_if($exercise->user_id !== null, 404);

        $data = $request->validated();

        $exercise->update([
            'name' => $data['name'],
            'muscle_group' => MuscleGroup::from($data['muscle_group']),
            'movement_type' => MovementType::from($data['movement_type']),
            'equipment' => Equipment::from($data['equipment']),
            'is_bodyweight' => $data['is_bodyweight'],
            'weight_increment' => $data['weight_increment'],
            'target_rep_min' => $data['target_rep_min'],
            'target_rep_max' => $data['target_rep_max'],
            // 未指定なら現在の設定を維持する。Double にフォールバックすると、
            // リニアや5x5を選んでいた種目が黙ってダブルプログレッションに戻ってしまう。
            'progression_strategy' => $data['progression_strategy'] ?? $exercise->progression_strategy->value,
            'sort_order' => $data['sort_order'],
        ]);

        return Redirect::route('admin.exercises.index')->with('success', '種目を更新しました。');
    }

    /**
     * workout_sets.exercise_id is RESTRICT: an exercise that has been used
     * in any recorded set cannot be deleted at the database level. Rather
     * than let that bubble up as a 500, catch the FK violation and report
     * exactly how many sets are blocking the deletion.
     */
    public function destroy(Exercise $exercise): RedirectResponse
    {
        abort_if($exercise->user_id !== null, 404);

        try {
            $exercise->delete();
        } catch (QueryException $e) {
            if (! $this->isForeignKeyConstraintViolation($e)) {
                throw $e;
            }

            $count = $exercise->workoutSets()->count();

            return Redirect::route('admin.exercises.index')
                ->with('error', "この種目は{$count}件の記録で使われているため削除できません。");
        }

        return Redirect::route('admin.exercises.index')->with('success', '種目を削除しました。');
    }

    /**
     * Persist the new display order for every default exercise within one
     * muscle_group, in one request (never one PATCH per row).
     */
    public function reorder(ReorderExercisesRequest $request): RedirectResponse
    {
        $order = $request->validated('order');

        DB::transaction(function () use ($order) {
            foreach ($order as $index => $exerciseId) {
                Exercise::whereKey($exerciseId)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return Redirect::route('admin.exercises.index');
    }

    private function isForeignKeyConstraintViolation(QueryException $e): bool
    {
        return ((int) ($e->errorInfo[1] ?? 0)) === self::FK_CONSTRAINT_VIOLATION;
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id,
            'name' => $exercise->name,
            'muscle_group' => $exercise->muscle_group->value,
            'movement_type' => $exercise->movement_type->value,
            'equipment' => $exercise->equipment->value,
            'is_bodyweight' => $exercise->is_bodyweight,
            'weight_increment' => (float) $exercise->weight_increment,
            'target_rep_min' => $exercise->target_rep_min,
            'target_rep_max' => $exercise->target_rep_max,
            'progression_strategy' => $exercise->progression_strategy->value,
            'sort_order' => $exercise->sort_order,
            'workout_sets_count' => $exercise->workout_sets_count,
        ];
    }
}
