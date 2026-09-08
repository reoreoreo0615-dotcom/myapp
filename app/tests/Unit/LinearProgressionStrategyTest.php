<?php

namespace Tests\Unit;

use App\Services\Progression\LinearProgressionStrategy;
use App\Services\Progression\ProgressionContext;
use App\Services\ProgressionService;
use PHPUnit\Framework\TestCase;

class LinearProgressionStrategyTest extends TestCase
{
    private LinearProgressionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new LinearProgressionStrategy(new ProgressionService);
    }

    public function test_returns_null_when_no_history(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $this->assertNull($this->strategy->nextTarget($context));
    }

    public function test_returns_null_when_history_is_only_warmup_sets(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 20.0, 'reps' => 5, 'is_warmup' => true],
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $this->assertNull($this->strategy->nextTarget($context));
    }

    /**
     * リニアプログレッションはレップ数の達成状況に関わらず、必ず重量を
     * increment 分上げる(ダブルプログレッションとの最大の違い)。
     */
    public function test_weight_always_increases_regardless_of_reps(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                // 目標レップ(5)を大きく下回っていても、リニアでは関係ない。
                ['weight' => 60.0, 'reps' => 3, 'is_warmup' => false],
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(62.5, $target->weight);
        $this->assertSame(5, $target->reps);
        $this->assertSame('weight', $target->type);
    }

    public function test_reps_target_is_always_rep_min(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 60.0, 'reps' => 12, 'is_warmup' => false],
            ],
            weightIncrement: 1.25,
            repMin: 5,
            repMax: 8,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(61.25, $target->weight);
        $this->assertSame(5, $target->reps);
        $this->assertSame('weight', $target->type);
    }

    public function test_picks_top_set_by_weight_then_reps_among_working_sets(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 100.0, 'reps' => 1, 'is_warmup' => true],
                ['weight' => 60.0, 'reps' => 5, 'is_warmup' => false],
                ['weight' => 62.5, 'reps' => 3, 'is_warmup' => false],
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(65.0, $target->weight);
    }

    public function test_rounds_weight_to_two_decimal_places(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 20.0, 'reps' => 5, 'is_warmup' => false],
            ],
            weightIncrement: 1.25,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(21.25, $target->weight);
    }

    public function test_label(): void
    {
        $this->assertSame('リニアプログレッション', $this->strategy->label());
    }
}
