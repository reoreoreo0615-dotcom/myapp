<?php

namespace Tests\Feature\Routines;

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_index(): void
    {
        $this->get(route('routines.index'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_for_create(): void
    {
        $this->get(route('routines.create'))->assertRedirect(route('login'));
    }

    public function test_user_sees_only_their_own_routines_on_index(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Routine::factory()->create(['user_id' => $user->id, 'name' => '胸の日']);
        Routine::factory()->create(['user_id' => $other->id, 'name' => '他人のメニュー']);

        $response = $this->actingAs($user)->get(route('routines.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Routines/Index')
            ->has('routines', 1)
            ->where('routines.0.id', $mine->id)
        );
    }

    public function test_user_can_create_a_routine_and_is_sent_to_the_builder(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('routines.store'), [
            'name' => '胸の日',
            'description' => 'ベンチ中心',
        ]);

        $this->assertDatabaseHas('routines', [
            'user_id' => $user->id,
            'name' => '胸の日',
            'description' => 'ベンチ中心',
        ]);

        $routine = Routine::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('routines.edit', $routine));
    }

    public function test_creating_a_routine_requires_a_name(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('routines.store'), [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('routines', 0);
    }

    public function test_owner_can_view_the_edit_page(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('routines.edit', $routine));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Routines/Edit')
            ->where('routine.id', $routine->id)
        );
    }

    public function test_other_user_cannot_view_the_edit_page(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->get(route('routines.edit', $routine));

        $response->assertForbidden();
    }

    public function test_owner_can_update_their_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'name' => '旧名']);

        $response = $this->actingAs($user)->patch(route('routines.update', $routine), [
            'name' => '新しい名前',
            'description' => null,
        ]);

        $response->assertRedirect(route('routines.edit', $routine));
        $this->assertSame('新しい名前', $routine->fresh()->name);
    }

    public function test_other_user_cannot_update_the_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id, 'name' => '旧名']);

        $response = $this->actingAs($intruder)->patch(route('routines.update', $routine), [
            'name' => '書き換え',
        ]);

        $response->assertForbidden();
        $this->assertSame('旧名', $routine->fresh()->name);
    }

    /**
     * Issue #23③: routines are now soft-deleted (so they can be restored
     * immediately after via the flash "undo" link). A soft delete is a
     * plain UPDATE, so it does not fire the physical nullOnDelete FK on
     * workouts.routine_id — the workout keeps pointing at the (now
     * trashed) routine, which is what lets an immediate restore put
     * everything back exactly as it was with no extra bookkeeping.
     */
    public function test_owner_can_delete_their_routine_and_past_workout_history_is_kept(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
        ]);

        $response = $this->actingAs($user)->delete(route('routines.destroy', $routine));

        $response->assertRedirect(route('routines.index'));
        $this->assertSoftDeleted('routines', ['id' => $routine->id]);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'routine_id' => $routine->id]);
    }

    /**
     * Issue #23③: the delete response carries an immediate "元に戻す" undo
     * link, and hitting it restores the routine.
     */
    public function test_owner_can_restore_a_just_deleted_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routine->delete();

        $response = $this->actingAs($user)->patch(route('routines.restore', $routine));

        $response->assertRedirect(route('routines.index'));
        $this->assertDatabaseHas('routines', ['id' => $routine->id, 'deleted_at' => null]);
    }

    public function test_other_user_cannot_restore_the_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);
        $routine->delete();

        $response = $this->actingAs($intruder)->patch(route('routines.restore', $routine));

        $response->assertForbidden();
        $this->assertSoftDeleted('routines', ['id' => $routine->id]);
    }

    public function test_other_user_cannot_delete_the_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->delete(route('routines.destroy', $routine));

        $response->assertForbidden();
        $this->assertDatabaseHas('routines', ['id' => $routine->id]);
    }
}
