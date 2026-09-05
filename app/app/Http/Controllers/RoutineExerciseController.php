<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReorderRoutineExercisesRequest;
use App\Http\Requests\StoreRoutineExerciseRequest;
use App\Http\Requests\UpdateRoutineExerciseRequest;
use App\Models\Routine;
use App\Models\RoutineExercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class RoutineExerciseController extends Controller
{
    /**
     * Add an exercise to the routine.
     */
    public function store(StoreRoutineExerciseRequest $request, Routine $routine): RedirectResponse
    {
        $data = $request->validated();

        $nextSortOrder = (int) $routine->routineExercises()->max('sort_order') + 10;

        $routine->routineExercises()->create([
            'exercise_id' => $data['exercise_id'],
            'target_sets' => $data['target_sets'] ?? 3,
            'sort_order' => $nextSortOrder,
        ]);

        return Redirect::route('routines.edit', $routine)->with('success', '種目を追加しました。');
    }

    /**
     * Update the target set count for one exercise in the routine.
     */
    public function update(UpdateRoutineExerciseRequest $request, Routine $routine, RoutineExercise $routineExercise): RedirectResponse
    {
        abort_unless($routineExercise->routine_id === $routine->id, 404);

        $routineExercise->update($request->validated());

        return Redirect::route('routines.edit', $routine);
    }

    /**
     * Remove an exercise from the routine.
     */
    public function destroy(Routine $routine, RoutineExercise $routineExercise): RedirectResponse
    {
        $this->authorize('update', $routine);
        abort_unless($routineExercise->routine_id === $routine->id, 404);

        $routineExercise->delete();

        return Redirect::route('routines.edit', $routine)->with('success', '種目を削除しました。');
    }

    /**
     * Persist the new display order for all of the routine's exercises in
     * one request (never one PATCH per row).
     */
    public function reorder(ReorderRoutineExercisesRequest $request, Routine $routine): RedirectResponse
    {
        $order = $request->validated('order');

        DB::transaction(function () use ($order) {
            foreach ($order as $index => $routineExerciseId) {
                RoutineExercise::whereKey($routineExerciseId)->update([
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        });

        return Redirect::route('routines.edit', $routine);
    }
}
