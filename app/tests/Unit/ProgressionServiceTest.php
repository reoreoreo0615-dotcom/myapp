<?php

namespace Tests\Unit;

use App\Services\ProgressionService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ProgressionService は Eloquent / DB に依存しない純粋なロジックなので、
 * モックを使わず値を直接渡してテストする。RefreshDatabase は使わない。
 */
class ProgressionServiceTest extends TestCase
{
    private ProgressionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ProgressionService;
    }

    // ------------------------------------------------------------------
    // nextTarget()
    // ------------------------------------------------------------------

    public static function nextTargetProvider(): array
    {
        return [
            'reps > repMax でも重量アップ扱い(60x12, increment2.5, 8-12)' => [
                'lastWeight' => 60.0,
                'lastReps' => 12,
                'increment' => 2.50,
                'repMin' => 8,
                'repMax' => 12,
                'expectedWeight' => 62.5,
                'expectedReps' => 8,
                'expectedType' => 'weight',
            ],
            'reps < repMax はレップアップ(60x9, 8-12)' => [
                'lastWeight' => 60.0,
                'lastReps' => 9,
                'increment' => 2.50,
                'repMin' => 8,
                'repMax' => 12,
                'expectedWeight' => 60.0,
                'expectedReps' => 10,
                'expectedType' => 'reps',
            ],
            'repMax を超過していても重量アップ(60x13, 8-12)' => [
                'lastWeight' => 60.0,
                'lastReps' => 13,
                'increment' => 2.50,
                'repMin' => 8,
                'repMax' => 12,
                'expectedWeight' => 62.5,
                'expectedReps' => 8,
                'expectedType' => 'weight',
            ],
            '軽量種目・increment2.0(14x15, 10-15)' => [
                'lastWeight' => 14.0,
                'lastReps' => 15,
                'increment' => 2.00,
                'repMin' => 10,
                'repMax' => 15,
                'expectedWeight' => 16.0,
                'expectedReps' => 10,
                'expectedType' => 'weight',
            ],
            '高重量・increment5.0(180x13, 10-15)' => [
                'lastWeight' => 180.0,
                'lastReps' => 13,
                'increment' => 5.00,
                'repMin' => 10,
                'repMax' => 15,
                'expectedWeight' => 180.0,
                'expectedReps' => 14,
                'expectedType' => 'reps',
            ],
            'repMin ちょうど(60x8, 8-12)' => [
                'lastWeight' => 60.0,
                'lastReps' => 8,
                'increment' => 2.50,
                'repMin' => 8,
                'repMax' => 12,
                'expectedWeight' => 60.0,
                'expectedReps' => 9,
                'expectedType' => 'reps',
            ],
            '自重0からのスタート(0x10, increment1.25, 6-10)' => [
                'lastWeight' => 0.0,
                'lastReps' => 10,
                'increment' => 1.25,
                'repMin' => 6,
                'repMax' => 10,
                'expectedWeight' => 1.25,
                'expectedReps' => 6,
                'expectedType' => 'weight',
            ],
        ];
    }

    #[DataProvider('nextTargetProvider')]
    public function test_next_target_returns_expected_value(
        ?float $lastWeight,
        ?int $lastReps,
        float $increment,
        int $repMin,
        int $repMax,
        float $expectedWeight,
        int $expectedReps,
        string $expectedType,
    ): void {
        $result = $this->service->nextTarget($lastWeight, $lastReps, $increment, $repMin, $repMax);

        $this->assertNotNull($result);
        $this->assertSame($expectedWeight, $result['weight']);
        $this->assertSame($expectedReps, $result['reps']);
        $this->assertSame($expectedType, $result['type']);
    }

    public static function nextTargetNullProvider(): array
    {
        return [
            '前回の記録が完全に無い(weight/reps とも null)' => [null, null],
            'reps だけ無い' => [60.0, null],
            'weight だけ無い' => [null, 8],
        ];
    }

    #[DataProvider('nextTargetNullProvider')]
    public function test_next_target_returns_null_when_history_missing(?float $lastWeight, ?int $lastReps): void
    {
        $result = $this->service->nextTarget($lastWeight, $lastReps, 2.50, 8, 12);

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    // estimateOneRepMax()
    // ------------------------------------------------------------------

    public static function estimateOneRepMaxProvider(): array
    {
        return [
            '60x8' => [60.0, 8, 76.0],
            '62.5x10' => [62.5, 10, 83.3],
            '65x11' => [65.0, 11, 88.8],
            '1レップはEpley式を適用せずそのまま返す(60x1)' => [60.0, 1, 60.0],
            '自重種目で加重なし(0x10)' => [0.0, 10, 0.0],
            '100x5' => [100.0, 5, 116.7],
        ];
    }

    #[DataProvider('estimateOneRepMaxProvider')]
    public function test_estimate_one_rep_max_returns_expected_value(float $weight, int $reps, float $expected): void
    {
        $this->assertSame($expected, $this->service->estimateOneRepMax($weight, $reps));
    }

    public static function invalidRepsProvider(): array
    {
        return [
            'reps = 0' => [0],
            'reps = -1' => [-1],
        ];
    }

    #[DataProvider('invalidRepsProvider')]
    public function test_estimate_one_rep_max_throws_when_reps_not_positive(int $reps): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->estimateOneRepMax(60.0, $reps);
    }

    // ------------------------------------------------------------------
    // pickTopSet()
    // ------------------------------------------------------------------

    public function test_pick_top_set_selects_highest_reps_among_same_max_weight(): void
    {
        // 55x12 が配列内で最後のセットだが、選ばれてはいけない。
        $sets = [
            ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
            ['weight' => 60.0, 'reps' => 10, 'is_warmup' => false],
            ['weight' => 55.0, 'reps' => 12, 'is_warmup' => false],
        ];

        $result = $this->service->pickTopSet($sets);

        $this->assertSame(['weight' => 60.0, 'reps' => 10, 'is_warmup' => false], $result);
    }

    public function test_pick_top_set_excludes_warmup_sets(): void
    {
        $sets = [
            ['weight' => 40.0, 'reps' => 10, 'is_warmup' => true],
            ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
        ];

        $result = $this->service->pickTopSet($sets);

        $this->assertSame(['weight' => 60.0, 'reps' => 8, 'is_warmup' => false], $result);
    }

    public function test_pick_top_set_excludes_warmup_even_when_it_is_heavier(): void
    {
        // ウォームアップの方が重量が大きくても除外される(最重要)。
        $sets = [
            ['weight' => 100.0, 'reps' => 1, 'is_warmup' => true],
            ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
        ];

        $result = $this->service->pickTopSet($sets);

        $this->assertSame(['weight' => 60.0, 'reps' => 8, 'is_warmup' => false], $result);
    }

    public function test_pick_top_set_returns_null_when_only_warmup_sets(): void
    {
        $sets = [
            ['weight' => 40.0, 'reps' => 10, 'is_warmup' => true],
            ['weight' => 50.0, 'reps' => 5, 'is_warmup' => true],
        ];

        $this->assertNull($this->service->pickTopSet($sets));
    }

    public function test_pick_top_set_returns_null_when_empty(): void
    {
        $this->assertNull($this->service->pickTopSet([]));
    }

    public function test_pick_top_set_returns_the_only_working_set(): void
    {
        $sets = [
            ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
        ];

        $result = $this->service->pickTopSet($sets);

        $this->assertSame(['weight' => 60.0, 'reps' => 8, 'is_warmup' => false], $result);
    }

    public function test_pick_top_set_compares_decimal_weights_correctly(): void
    {
        $sets = [
            ['weight' => 62.5, 'reps' => 8, 'is_warmup' => false],
            ['weight' => 60.0, 'reps' => 12, 'is_warmup' => false],
        ];

        $result = $this->service->pickTopSet($sets);

        $this->assertSame(['weight' => 62.5, 'reps' => 8, 'is_warmup' => false], $result);
    }
}
