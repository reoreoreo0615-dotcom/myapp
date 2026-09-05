<?php

namespace Tests\Feature\Routines;

use App\Enums\Equipment;
use App\Enums\MovementType;
use App\Enums\MuscleGroup;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomExerciseCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create_a_custom_exercise(): void
    {
        $response = $this->post(route('exercises.store'), [
            'name' => 'マイ種目',
            'muscle_group' => 'chest',
            'equipment' => 'barbell',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('exercises', 0);
    }

    public function test_user_can_create_a_custom_exercise_owned_by_themselves(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => 'オリジナルカール',
            'muscle_group' => 'arms',
            'equipment' => 'dumbbell',
        ]);

        $response->assertRedirect();
        $exercise = Exercise::where('name', 'オリジナルカール')->firstOrFail();
        $this->assertSame($user->id, $exercise->user_id);
        $this->assertSame(MuscleGroup::Arms, $exercise->muscle_group);
        $this->assertSame(Equipment::Dumbbell, $exercise->equipment);
    }

    public function test_movement_type_weight_increment_and_bodyweight_flag_are_derived_server_side(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exercises.store'), [
            'name' => '自重バックエクステンション',
            'muscle_group' => 'back',
            'equipment' => 'bodyweight',
        ]);

        $exercise = Exercise::where('name', '自重バックエクステンション')->firstOrFail();

        $this->assertSame(MovementType::Pull, $exercise->movement_type);
        $this->assertTrue($exercise->is_bodyweight);
        $this->assertEquals(1.25, (float) $exercise->weight_increment);
    }

    public function test_a_user_cannot_create_two_custom_exercises_with_the_same_name(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => $user->id, 'name' => '重複種目']);

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => '重複種目',
            'muscle_group' => 'chest',
            'equipment' => 'barbell',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Exercise::where('name', '重複種目')->count());
    }

    public function test_a_custom_exercise_name_may_duplicate_another_users_custom_exercise(): void
    {
        $otherUser = User::factory()->create();
        Exercise::factory()->create(['user_id' => $otherUser->id, 'name' => '共通の名前']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => '共通の名前',
            'muscle_group' => 'chest',
            'equipment' => 'barbell',
        ]);

        $response->assertSessionDoesntHaveErrors('name');
        $this->assertSame(2, Exercise::where('name', '共通の名前')->count());
    }

    public function test_a_custom_exercise_name_may_duplicate_a_default_exercises_name(): void
    {
        Exercise::factory()->create(['user_id' => null, 'name' => 'スクワット']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('exercises.store'), [
            'name' => 'スクワット',
            'muscle_group' => 'legs',
            'equipment' => 'barbell',
        ]);

        $response->assertSessionDoesntHaveErrors('name');
        $this->assertSame(2, Exercise::where('name', 'スクワット')->count());
    }
}
