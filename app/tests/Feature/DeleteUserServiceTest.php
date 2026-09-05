<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\DeleteUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteUserServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeleteUserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DeleteUserService;
    }

    /**
     * 最重要の再発防止テスト。
     *
     * exercises.user_id の削除ルールがかつて SET NULL だったため、
     * ユーザーを削除すると独自種目が user_id = NULL になり、
     * 全ユーザーに見える既定種目に「昇格」してしまうバグがあった。
     * ユーザー削除後、独自種目は user_id = NULL の行として残らず、
     * 完全に削除されていなければならない。
     */
    public function test_deleting_a_user_does_not_leak_their_custom_exercises_as_default_exercises(): void
    {
        $user = User::factory()->create();
        $customExercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Secret Custom Exercise',
        ]);

        $this->service->delete($user);

        $this->assertDatabaseMissing('exercises', [
            'id' => $customExercise->id,
        ]);

        // user_id = NULL の行として紛れ込んでいないことも明示的に確認する。
        $this->assertDatabaseMissing('exercises', [
            'name' => 'My Secret Custom Exercise',
            'user_id' => null,
        ]);
    }

    /**
     * 既定種目(user_id = NULL)は他ユーザーの削除の影響を受けず残ること。
     */
    public function test_deleting_a_user_does_not_remove_default_exercises(): void
    {
        $user = User::factory()->create();
        $defaultExercise = Exercise::factory()->create([
            'user_id' => null,
            'name' => 'Default Bench Press',
        ]);

        $this->service->delete($user);

        $this->assertDatabaseHas('exercises', [
            'id' => $defaultExercise->id,
            'user_id' => null,
        ]);
    }

    public function test_deleting_a_user_cascades_their_workouts_and_workout_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->service->delete($user);

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
        $this->assertDatabaseMissing('workout_sets', ['id' => $set->id]);
    }

    public function test_deleting_a_user_cascades_their_routines_and_routine_exercises(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineExercise = RoutineExercise::create([
            'routine_id' => $routine->id,
            'exercise_id' => $exercise->id,
            'sort_order' => 0,
            'target_sets' => 3,
        ]);

        $this->service->delete($user);

        $this->assertDatabaseMissing('routines', ['id' => $routine->id]);
        $this->assertDatabaseMissing('routine_exercises', ['id' => $routineExercise->id]);
    }

    /**
     * workout_sets.exercise_id は RESTRICT のため、セット記録が残る種目を
     * 素朴に削除すると失敗する。DeleteUserService は正しい順序
     * (workouts → routines → exercises → user)で削除するため、
     * 記録を持つユーザーでも例外を出さずに削除できなければならない。
     */
    public function test_a_user_with_workout_records_can_be_deleted_without_restrict_violation(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->service->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
    }

    public function test_deleting_a_user_removes_the_user_itself(): void
    {
        $user = User::factory()->create();

        $this->service->delete($user);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
