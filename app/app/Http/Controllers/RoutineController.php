<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoutineRequest;
use App\Http\Requests\UpdateRoutineRequest;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class RoutineController extends Controller
{
    /**
     * Display the current user's routines.
     */
    public function index(Request $request): Response
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
                'description' => $routine->description,
                'exercises_count' => $routine->routine_exercises_count,
            ]);

        return Inertia::render('Routines/Index', [
            'routines' => $routines,
        ]);
    }

    /**
     * Show the form for creating a new routine.
     */
    public function create(): Response
    {
        return Inertia::render('Routines/Create');
    }

    /**
     * Store a newly created routine, then send the user straight into the
     * exercise builder (an empty menu is not useful on its own).
     */
    public function store(StoreRoutineRequest $request): RedirectResponse
    {
        $routine = Routine::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return Redirect::route('routines.edit', $routine)
            ->with('success', 'メニューを作成しました。続けて種目を追加してください。');
    }

    /**
     * Show the routine builder: name/description plus its exercises.
     */
    public function edit(Request $request, Routine $routine): Response
    {
        $this->authorize('update', $routine);

        $routineExercises = $routine->routineExercises()
            ->with('exercise')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RoutineExercise $routineExercise) => [
                'id' => $routineExercise->id,
                'exercise_id' => $routineExercise->exercise_id,
                'name' => $routineExercise->exercise->name,
                'muscle_group' => $routineExercise->exercise->muscle_group->value,
                'sort_order' => $routineExercise->sort_order,
                'target_sets' => $routineExercise->target_sets,
            ]);

        $availableExercises = Exercise::query()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->orderBy('muscle_group')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Exercise $exercise) => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscle_group->value,
                'is_custom' => $exercise->user_id !== null,
            ]);

        return Inertia::render('Routines/Edit', [
            'routine' => [
                'id' => $routine->id,
                'name' => $routine->name,
                'description' => $routine->description,
            ],
            'exercises' => $routineExercises,
            'availableExercises' => $availableExercises,
        ]);
    }

    /**
     * Update the routine's name/description.
     */
    public function update(UpdateRoutineRequest $request, Routine $routine): RedirectResponse
    {
        $routine->update($request->validated());

        return Redirect::route('routines.edit', $routine)->with('success', '保存しました。');
    }

    /**
     * Delete the routine (physical delete; past workouts keep their history
     * because workouts.routine_id is nullOnDelete).
     */
    public function destroy(Routine $routine): RedirectResponse
    {
        $this->authorize('delete', $routine);

        $routine->delete();

        return Redirect::route('routines.index')->with('success', 'メニューを削除しました。');
    }
}
