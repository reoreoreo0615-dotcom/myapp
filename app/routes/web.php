<?php

use App\Http\Controllers\Admin\AdminExerciseController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BodyLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\RoutineExerciseController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutSetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return app(AuthenticatedSessionController::class)->create();
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('routines', RoutineController::class)->except(['show']);
    Route::patch('/routines/{routine}/restore', [RoutineController::class, 'restore'])
        ->name('routines.restore')
        ->withTrashed();

    // /reorder は {routineExercise} の暗黙バインディングと衝突するため、
    // 動的パラメータを持つルートより先に定義する。
    Route::patch('/routines/{routine}/exercises/reorder', [RoutineExerciseController::class, 'reorder'])
        ->name('routines.exercises.reorder');
    Route::post('/routines/{routine}/exercises', [RoutineExerciseController::class, 'store'])
        ->name('routines.exercises.store');
    Route::patch('/routines/{routine}/exercises/{routineExercise}', [RoutineExerciseController::class, 'update'])
        ->name('routines.exercises.update');
    Route::delete('/routines/{routine}/exercises/{routineExercise}', [RoutineExerciseController::class, 'destroy'])
        ->name('routines.exercises.destroy');

    Route::post('/exercises', [ExerciseController::class, 'store'])->name('exercises.store');

    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    Route::get('/body-weight', [BodyLogController::class, 'index'])->name('body-logs.index');
    Route::post('/body-weight', [BodyLogController::class, 'store'])->name('body-logs.store');
    Route::patch('/body-weight/{bodyLog}', [BodyLogController::class, 'update'])->name('body-logs.update');
    Route::delete('/body-weight/{bodyLog}', [BodyLogController::class, 'destroy'])->name('body-logs.destroy');

    Route::get('/workouts/create', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::post('/workouts', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/workouts/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');
    Route::patch('/workouts/{workout}/finish', [WorkoutController::class, 'finish'])->name('workouts.finish');
    Route::patch('/workouts/{workout}/start-editing', [WorkoutController::class, 'startEditing'])->name('workouts.start-editing');
    Route::patch('/workouts/{workout}/end-editing', [WorkoutController::class, 'endEditing'])->name('workouts.end-editing');
    Route::delete('/workouts/{workout}', [WorkoutController::class, 'destroy'])->name('workouts.destroy');
    Route::patch('/workouts/{workout}/restore', [WorkoutController::class, 'restore'])
        ->name('workouts.restore')
        ->withTrashed();

    Route::post('/workouts/{workout}/sets', [WorkoutSetController::class, 'store'])->name('workouts.sets.store');
    Route::patch('/workouts/{workout}/sets/{workoutSet}', [WorkoutSetController::class, 'update'])->name('workouts.sets.update');
    Route::delete('/workouts/{workout}/sets/{workoutSet}', [WorkoutSetController::class, 'destroy'])->name('workouts.sets.destroy');
    // withTrashed() applies to both {workout} and {workoutSet} bindings on this route (Laravel has no
    // per-parameter granularity for manually defined routes); the controller still requires
    // authorize('update', $workout) + a matching workout_id, so this is not a security concern.
    Route::patch('/workouts/{workout}/sets/{workoutSet}/restore', [WorkoutSetController::class, 'restore'])
        ->name('workouts.sets.restore')
        ->withTrashed();
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/admin', [AdminUserController::class, 'updateAdmin'])->name('users.update-admin');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/exercises', [AdminExerciseController::class, 'index'])->name('exercises.index');
    Route::post('/exercises', [AdminExerciseController::class, 'store'])->name('exercises.store');
    // /reorder は {exercise} の暗黙バインディングと衝突するため、先に定義する。
    Route::patch('/exercises/reorder', [AdminExerciseController::class, 'reorder'])->name('exercises.reorder');
    Route::patch('/exercises/{exercise}', [AdminExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('/exercises/{exercise}', [AdminExerciseController::class, 'destroy'])->name('exercises.destroy');
});

require __DIR__.'/auth.php';
