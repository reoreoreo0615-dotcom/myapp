<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutFinishTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 終了(finished_at の確定)
    // ------------------------------------------------------------------

    public function test_owner_can_finish_a_workout_with_recorded_sets(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->actingAs($user)->patch(route('workouts.finish', $workout));

        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertNotNull($workout->fresh()->finished_at);
    }

    public function test_guest_cannot_finish_a_workout(): void
    {
        $workout = Workout::factory()->create();

        $this->patch(route('workouts.finish', $workout))->assertRedirect(route('login'));
    }

    public function test_user_cannot_finish_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($intruder)->patch(route('workouts.finish', $workout))->assertForbidden();
        $this->assertNull($workout->fresh()->finished_at);
    }

    public function test_finishing_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($user)->patch(route('workouts.finish', $workout))->assertRedirect(route('workouts.show', $workout));
        $finishedAt = $workout->fresh()->finished_at;

        // 二重タップ/リトライを再現。エラーにならず、finished_at も変わらない。
        $this->actingAs($user)->patch(route('workouts.finish', $workout))->assertRedirect(route('workouts.show', $workout));

        $this->assertTrue($finishedAt->equalTo($workout->fresh()->finished_at));
    }

    /**
     * 受入条件: セットが0件のまま終了した場合、空のワークアウトを履歴に残さない
     * (実装判断: ワークアウト自体を破棄する)。
     */
    public function test_finishing_a_workout_with_no_sets_discards_it(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->patch(route('workouts.finish', $workout));

        $response->assertRedirect(route('workouts.create'));
        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
    }

    // ------------------------------------------------------------------
    // 終了済みワークアウトは編集不可
    // ------------------------------------------------------------------

    public function test_cannot_add_a_set_to_a_finished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 60,
            'reps' => 8,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('workout_sets', 0);
    }

    public function test_cannot_edit_a_set_on_a_finished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
        ]);

        $response = $this->actingAs($user)->patch(route('workouts.sets.update', [$workout, $set]), [
            'weight' => 100,
            'reps' => 1,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'weight' => 60.0, 'reps' => 8]);
    }

    public function test_cannot_delete_a_set_on_a_finished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->actingAs($user)->delete(route('workouts.sets.destroy', [$workout, $set]));

        $response->assertForbidden();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id]);
    }

    public function test_show_page_marks_a_finished_workout_as_read_only(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workouts/Show')
            ->where('isFinished', true)
            ->where('canAddExercises', false)
        );
    }

    // ------------------------------------------------------------------
    // 進行中のワークアウトがある状態で新規開始しようとしたとき
    // (実装判断: 新規作成させず、進行中のワークアウトへ再誘導する)
    // ------------------------------------------------------------------

    public function test_visiting_the_picker_with_an_active_workout_redirects_into_it(): void
    {
        $user = User::factory()->create();
        $activeWorkout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->get(route('workouts.create'));

        $response->assertRedirect(route('workouts.show', $activeWorkout));
    }

    public function test_starting_a_new_workout_with_an_active_workout_redirects_into_it_instead_of_creating_a_second_one(): void
    {
        $user = User::factory()->create();
        $activeWorkout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->post(route('workouts.store'), ['routine_id' => null]);

        $response->assertRedirect(route('workouts.show', $activeWorkout));
        $this->assertSame(1, Workout::where('user_id', $user->id)->count());
    }

    public function test_starting_a_new_workout_is_allowed_once_the_previous_one_is_finished(): void
    {
        $user = User::factory()->create();
        Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('workouts.store'), ['routine_id' => null]);

        $newWorkout = Workout::where('user_id', $user->id)->whereNull('finished_at')->firstOrFail();
        $response->assertRedirect(route('workouts.show', $newWorkout));
        $this->assertSame(2, Workout::where('user_id', $user->id)->count());
    }
}
