<?php

namespace Tests\Feature\Admin;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_receives_a_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this
            ->actingAs($user)
            ->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_user_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->get('/admin/users');

        $response->assertOk();
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($admin)
            ->delete("/admin/users/{$admin->id}");

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNotNull($admin->fresh());
    }

    public function test_admin_can_delete_another_user_and_their_custom_exercises_are_removed(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create();
        $customExercise = Exercise::factory()->create([
            'user_id' => $target->id,
            'name' => 'Target User Secret Exercise',
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete("/admin/users/{$target->id}");

        $response->assertRedirect(route('admin.users.index'));
        $this->assertNull($target->fresh());
        $this->assertDatabaseMissing('exercises', ['id' => $customExercise->id]);
        $this->assertDatabaseMissing('exercises', [
            'name' => 'Target User Secret Exercise',
            'user_id' => null,
        ]);
    }

    public function test_last_admin_cannot_have_admin_privileges_revoked(): void
    {
        // Only one admin exists. Revoking their own admin flag would leave
        // the system with zero admins, so it must be rejected.
        $lastAdmin = User::factory()->create(['is_admin' => true]);
        User::factory()->create(['is_admin' => false]);

        $response = $this
            ->actingAs($lastAdmin)
            ->patch(route('admin.users.update-admin', $lastAdmin->id), [
                'is_admin' => false,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($lastAdmin->fresh()->is_admin);
    }

    public function test_admin_privileges_can_be_granted_to_a_non_admin_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.users.update-admin', $user->id), [
                'is_admin' => true,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_admin_privileges_can_be_revoked_when_another_admin_remains(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('admin.users.update-admin', $otherAdmin->id), [
                'is_admin' => false,
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertFalse($otherAdmin->fresh()->is_admin);
    }
}
