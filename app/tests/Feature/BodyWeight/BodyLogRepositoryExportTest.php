<?php

namespace Tests\Feature\BodyWeight;

use App\Models\BodyLog;
use App\Models\User;
use App\Repositories\BodyLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BodyLogRepository::exportRows()(Issue #26①)。
 */
class BodyLogRepositoryExportTest extends TestCase
{
    use RefreshDatabase;

    private BodyLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BodyLogRepository;
    }

    public function test_export_rows_returns_rows_in_measured_on_order(): void
    {
        $user = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-05', 'weight_kg' => 70.0]);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-01', 'weight_kg' => 68.5]);

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-09-01', $rows[0]->measured_on->format('Y-m-d'));
        $this->assertSame('2026-09-05', $rows[1]->measured_on->format('Y-m-d'));
    }

    public function test_export_rows_respects_from_and_to_date_filters(): void
    {
        $user = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-08-01']);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-01']);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-10-01']);

        $rows = $this->repository->exportRows($user->id, '2026-08-15', '2026-09-15')->all();

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-01', $rows[0]->measured_on->format('Y-m-d'));
    }

    public function test_export_rows_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $otherUser->id]);

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertSame([], $rows);
    }
}
