<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    // Exceptions::respond() は app.debug が有効だと素通しする(ローカル開発では
    // Laravel 標準のデバッグ画面を優先する)。ここでは本番相当の挙動を検証したいので
    // 明示的に無効化する。
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    public function test_404_renders_the_custom_error_page(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 404));
    }

    public function test_403_renders_the_custom_error_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertForbidden();
        $response->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 403));
    }

    public function test_500_renders_the_custom_error_page(): void
    {
        Route::get('/__test-throws-500', function () {
            throw new \RuntimeException('boom');
        })->middleware('web');

        $response = $this->get('/__test-throws-500');

        $response->assertStatus(500);
        $response->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 500));
    }

    public function test_debug_mode_bypasses_the_custom_error_page(): void
    {
        config(['app.debug' => true]);

        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        // デバッグ画面(標準の Whoops 相当)が返り、Inertia ページにはならない。
        $response->assertHeaderMissing('X-Inertia');
    }
}
