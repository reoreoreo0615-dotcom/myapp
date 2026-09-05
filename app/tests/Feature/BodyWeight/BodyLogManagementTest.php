<?php

namespace Tests\Feature\BodyWeight;

use App\Models\BodyLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 体重・体脂肪率の記録(Issue #21)。
 */
class BodyLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('body-logs.index'))->assertRedirect(route('login'));
    }

    public function test_index_lists_only_the_current_users_logs(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-01', 'weight_kg' => 68.5]);
        BodyLog::factory()->create(['user_id' => $otherUser->id, 'measured_on' => '2026-09-01', 'weight_kg' => 99.9]);

        $response = $this->actingAs($user)->get(route('body-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('BodyWeight/Index')
            ->has('logs', 1)
            ->where('logs.0.weight_kg', 68.5)
        );
    }

    public function test_user_can_record_a_new_body_log(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('body-logs.store'), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 70.25,
            'body_fat_percentage' => 15.5,
            'memo' => '朝食前',
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $user->id,
            'measured_on' => '2026-09-01',
            'weight_kg' => 70.25,
            'body_fat_percentage' => 15.5,
            'memo' => '朝食前',
        ]);
    }

    /**
     * 受入条件: 同日の再入力は上書きになる(重複エラーにならない)。
     */
    public function test_recording_on_the_same_day_again_overwrites_instead_of_erroring(): void
    {
        $user = User::factory()->create();
        BodyLog::factory()->create([
            'user_id' => $user->id,
            'measured_on' => '2026-09-01',
            'weight_kg' => 70.0,
        ]);

        $response = $this->actingAs($user)->post(route('body-logs.store'), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 71.5,
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $this->assertDatabaseCount('body_logs', 1);
        $this->assertDatabaseHas('body_logs', [
            'user_id' => $user->id,
            'measured_on' => '2026-09-01',
            'weight_kg' => 71.5,
        ]);
    }

    public function test_weight_is_required_and_bounded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('body-logs.store'), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 1000,
        ])->assertSessionHasErrors('weight_kg');

        $this->actingAs($user)->post(route('body-logs.store'), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 0,
        ])->assertSessionHasErrors('weight_kg');
    }

    public function test_owner_can_update_their_body_log(): void
    {
        $user = User::factory()->create();
        $log = BodyLog::factory()->create([
            'user_id' => $user->id,
            'measured_on' => '2026-09-01',
            'weight_kg' => 70.0,
        ]);

        $response = $this->actingAs($user)->patch(route('body-logs.update', $log), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 69.0,
            'body_fat_percentage' => null,
            'memo' => null,
        ]);

        $response->assertRedirect(route('body-logs.index'));
        $this->assertDatabaseHas('body_logs', ['id' => $log->id, 'weight_kg' => 69.0]);
    }

    public function test_other_user_cannot_update_someone_elses_body_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $log = BodyLog::factory()->create(['user_id' => $otherUser->id, 'measured_on' => '2026-09-01']);

        $response = $this->actingAs($user)->patch(route('body-logs.update', $log), [
            'measured_on' => '2026-09-01',
            'weight_kg' => 50.0,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('body_logs', ['id' => $log->id, 'user_id' => $otherUser->id]);
    }

    public function test_owner_can_delete_their_body_log(): void
    {
        $user = User::factory()->create();
        $log = BodyLog::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('body-logs.destroy', $log));

        $response->assertRedirect(route('body-logs.index'));
        $this->assertDatabaseMissing('body_logs', ['id' => $log->id]);
    }

    /**
     * 受入条件: 他ユーザーの体重が見えない(403 または 404)。
     */
    public function test_other_user_cannot_delete_someone_elses_body_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $log = BodyLog::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->delete(route('body-logs.destroy', $log));

        $response->assertForbidden();
        $this->assertDatabaseHas('body_logs', ['id' => $log->id]);
    }

    public function test_period_filter_excludes_records_outside_the_window(): void
    {
        $user = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => now()->subMonths(2)->toDateString()]);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => now()->subMonths(4)->toDateString()]);

        $threeMonths = $this->actingAs($user)->get(route('body-logs.index', ['period' => '3m']));
        $threeMonths->assertInertia(fn ($page) => $page->has('logs', 1));

        $all = $this->actingAs($user)->get(route('body-logs.index', ['period' => 'all']));
        $all->assertInertia(fn ($page) => $page->has('logs', 2));
    }
}
