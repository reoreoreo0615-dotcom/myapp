<?php

namespace Tests\Unit;

use App\Services\MuscleBalanceService;
use PHPUnit\Framework\TestCase;

/**
 * MuscleBalanceService は Eloquent / DB に依存しない純粋なロジックなので、
 * モックを使わず値を直接渡してテストする。RefreshDatabase は使わない。
 */
class MuscleBalanceServiceTest extends TestCase
{
    private MuscleBalanceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MuscleBalanceService;
    }

    public function test_insufficient_data_when_push_plus_pull_is_below_the_minimum(): void
    {
        // push(5) + pull(4) = 9 < 10(MIN_TOTAL_SETS)。
        $result = $this->service->analyze(['push' => 5, 'pull' => 4, 'legs' => 20, 'core' => 3]);

        $this->assertFalse($result['sufficient_data']);
        $this->assertFalse($result['is_imbalanced']);
        $this->assertNull($result['dominant']);
        $this->assertNull($result['ratio']);
        $this->assertSame(9, $result['push_pull_total']);
    }

    public function test_balanced_push_pull_is_not_imbalanced(): void
    {
        $result = $this->service->analyze(['push' => 10, 'pull' => 9]);

        $this->assertTrue($result['sufficient_data']);
        $this->assertFalse($result['is_imbalanced']);
        $this->assertNull($result['dominant']);
        $this->assertSame(1.11, $result['ratio']);
    }

    public function test_ratio_exactly_at_threshold_is_not_imbalanced(): void
    {
        // 15 / 10 = 1.5 ちょうど。「閾値を超えたら」警告なので、ちょうどは警告しない。
        $result = $this->service->analyze(['push' => 15, 'pull' => 10]);

        $this->assertFalse($result['is_imbalanced']);
        $this->assertSame(1.5, $result['ratio']);
    }

    public function test_push_dominant_beyond_threshold_is_imbalanced(): void
    {
        $result = $this->service->analyze(['push' => 20, 'pull' => 8]);

        $this->assertTrue($result['sufficient_data']);
        $this->assertTrue($result['is_imbalanced']);
        $this->assertSame('push', $result['dominant']);
        $this->assertSame(2.5, $result['ratio']);
    }

    public function test_pull_dominant_beyond_threshold_is_imbalanced(): void
    {
        $result = $this->service->analyze(['push' => 6, 'pull' => 15]);

        $this->assertTrue($result['is_imbalanced']);
        $this->assertSame('pull', $result['dominant']);
    }

    public function test_zero_pull_with_push_only_is_imbalanced_without_a_numeric_ratio(): void
    {
        $result = $this->service->analyze(['push' => 12, 'pull' => 0]);

        $this->assertTrue($result['sufficient_data']);
        $this->assertTrue($result['is_imbalanced']);
        $this->assertSame('push', $result['dominant']);
        $this->assertNull($result['ratio']);
    }

    public function test_missing_movement_types_default_to_zero_counts(): void
    {
        $result = $this->service->analyze(['push' => 6, 'pull' => 6]);

        $this->assertSame(['push' => 6, 'pull' => 6, 'legs' => 0, 'core' => 0], $result['counts']);
    }

    public function test_legs_and_core_do_not_affect_the_push_pull_ratio(): void
    {
        $result = $this->service->analyze(['push' => 10, 'pull' => 9, 'legs' => 500, 'core' => 200]);

        $this->assertSame(19, $result['push_pull_total']);
        $this->assertFalse($result['is_imbalanced']);
    }
}
