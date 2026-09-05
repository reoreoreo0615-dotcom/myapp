<?php

namespace Tests\Feature\Routines;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineExerciseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_default_exercise_to_their_routine_with_default_target_sets(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->post(route('routines.exercises.store', $routine), [
            'exercise_id' => $exercise->id,
        ]);

        $response->assertRedirect(route('routines.edit', $routine));
        $this->assertDatabaseHas('routine_exercises', [
            'routine_id' => $routine->id,
            'exercise_id' => $exercise->id,
            'target_sets' => 3,
        ]);
    }

    public function test_owner_can_add_their_own_custom_exercise(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $customExercise = Exercise::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('routines.exercises.store', $routine), [
            'exercise_id' => $customExercise->id,
            'target_sets' => 5,
        ]);

        $response->assertRedirect(route('routines.edit', $routine));
        $this->assertDatabaseHas('routine_exercises', [
            'routine_id' => $routine->id,
            'exercise_id' => $customExercise->id,
            'target_sets' => 5,
        ]);
    }

    public function test_user_cannot_add_an_exercise_to_another_users_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($intruder)->post(route('routines.exercises.store', $routine), [
            'exercise_id' => $exercise->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('routine_exercises', [
            'routine_id' => $routine->id,
            'exercise_id' => $exercise->id,
        ]);
    }

    public function test_user_cannot_add_another_users_custom_exercise_to_their_own_routine(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $othersExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->post(route('routines.exercises.store', $routine), [
            'exercise_id' => $othersExercise->id,
        ]);

        $response->assertSessionHasErrors('exercise_id');
        $this->assertDatabaseMissing('routine_exercises', [
            'routine_id' => $routine->id,
            'exercise_id' => $othersExercise->id,
        ]);
    }

    public function test_the_same_exercise_cannot_be_added_to_a_routine_twice(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $this->makeRoutineExercise([
            'routine_id' => $routine->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->actingAs($user)->post(route('routines.exercises.store', $routine), [
            'exercise_id' => $exercise->id,
        ]);

        $response->assertSessionHasErrors('exercise_id');
        $this->assertSame(1, RoutineExercise::where('routine_id', $routine->id)->count());
    }

    public function test_owner_can_update_target_sets_for_an_exercise_in_their_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineExercise = $this->makeRoutineExercise([
            'routine_id' => $routine->id,
            'target_sets' => 3,
        ]);

        $response = $this->actingAs($user)->patch(
            route('routines.exercises.update', [$routine, $routineExercise]),
            ['target_sets' => 5]
        );

        $response->assertRedirect(route('routines.edit', $routine));
        $this->assertSame(5, $routineExercise->fresh()->target_sets);
    }

    public function test_other_user_cannot_update_target_sets_for_someone_elses_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);
        $routineExercise = $this->makeRoutineExercise([
            'routine_id' => $routine->id,
            'target_sets' => 3,
        ]);

        $response = $this->actingAs($intruder)->patch(
            route('routines.exercises.update', [$routine, $routineExercise]),
            ['target_sets' => 10]
        );

        $response->assertForbidden();
        $this->assertSame(3, $routineExercise->fresh()->target_sets);
    }

    public function test_owner_can_remove_an_exercise_from_their_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineExercise = $this->makeRoutineExercise(['routine_id' => $routine->id]);

        $response = $this->actingAs($user)->delete(
            route('routines.exercises.destroy', [$routine, $routineExercise])
        );

        $response->assertRedirect(route('routines.edit', $routine));
        $this->assertDatabaseMissing('routine_exercises', ['id' => $routineExercise->id]);
    }

    public function test_other_user_cannot_remove_an_exercise_from_someone_elses_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);
        $routineExercise = $this->makeRoutineExercise(['routine_id' => $routine->id]);

        $response = $this->actingAs($intruder)->delete(
            route('routines.exercises.destroy', [$routine, $routineExercise])
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('routine_exercises', ['id' => $routineExercise->id]);
    }

    public function test_owner_can_reorder_exercises_in_a_single_request(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $first = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 10]);
        $second = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 20]);
        $third = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 30]);

        $response = $this->actingAs($user)->patch(
            route('routines.exercises.reorder', $routine),
            ['order' => [$third->id, $first->id, $second->id]]
        );

        $response->assertRedirect(route('routines.edit', $routine));

        $ordered = RoutineExercise::where('routine_id', $routine->id)
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        $this->assertSame([$third->id, $first->id, $second->id], $ordered);
    }

    public function test_reorder_rejects_a_list_that_does_not_match_the_routines_exercises(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $first = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 10]);
        $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 20]);

        // Only one of the two routine_exercise ids is included.
        $response = $this->actingAs($user)->patch(
            route('routines.exercises.reorder', $routine),
            ['order' => [$first->id]]
        );

        $response->assertSessionHasErrors('order');
    }

    public function test_other_user_cannot_reorder_someone_elses_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);
        $first = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 10]);
        $second = $this->makeRoutineExercise(['routine_id' => $routine->id, 'sort_order' => 20]);

        $response = $this->actingAs($intruder)->patch(
            route('routines.exercises.reorder', $routine),
            ['order' => [$second->id, $first->id]]
        );

        $response->assertForbidden();
    }

    /**
     * RoutineExercise has no factory of its own (it is a thin pivot model that
     * the task instructions marked as "existing, do not change" — adding
     * HasFactory to it was out of scope), so tests build rows directly.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function makeRoutineExercise(array $attributes = []): RoutineExercise
    {
        return RoutineExercise::create([
            'exercise_id' => Exercise::factory()->create(['user_id' => null])->id,
            'sort_order' => 0,
            'target_sets' => 3,
            ...$attributes,
        ]);
    }
}
