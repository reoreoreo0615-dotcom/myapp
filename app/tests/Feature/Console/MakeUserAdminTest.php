<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeUserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_admin_privileges_to_an_existing_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->artisan('user:make-admin', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_it_fails_gracefully_for_an_unknown_email(): void
    {
        $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])
            ->assertFailed();
    }
}
