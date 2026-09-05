<?php

use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\RoutineExerciseController;
use App\Http\Controllers\WorkoutController;
use App\Http\Controllers\WorkoutSetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return app(AuthenticatedSessionController::class)->create();
})->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('routines', RoutineController::class)->except(['show']);

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

    Route::get('/workouts/create', [WorkoutController::class, 'create'])->name('workouts.create');
    Route::post('/workouts', [WorkoutController::class, 'store'])->name('workouts.store');
    Route::get('/workouts/{workout}', [WorkoutController::class, 'show'])->name('workouts.show');

    Route::post('/workouts/{workout}/sets', [WorkoutSetController::class, 'store'])->name('workouts.sets.store');
    Route::patch('/workouts/{workout}/sets/{workoutSet}', [WorkoutSetController::class, 'update'])->name('workouts.sets.update');
    Route::delete('/workouts/{workout}/sets/{workoutSet}', [WorkoutSetController::class, 'destroy'])->name('workouts.sets.destroy');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/admin', [AdminUserController::class, 'updateAdmin'])->name('users.update-admin');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';
