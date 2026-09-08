<?php

namespace Tests\Unit;

use App\Services\Progression\DoubleProgressionStrategy;
use App\Services\Progression\ProgressionContext;
use App\Services\ProgressionService;
use PHPUnit\Framework\TestCase;

/**
 * DoubleProgressionStrategy は既存の ProgressionService::nextTarget() /
 * pickTopSet() の計算をそのまま使うだけの薄いラッパー。Eloquent / DB には
 * 依存しないので、ProgressionServiceTest と同じくモックを使わず値を
 * 直接渡してテストする。
 */
class DoubleProgressionStrategyTest extends TestCase
{
    private DoubleProgressionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new DoubleProgressionStrategy(new ProgressionService);
    }

    public function test_returns_null_when_no_history(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [],
            weightIncrement: 2.50,
            repMin: 8,
            repMax: 12,
        );

        $this->assertNull($this->strategy->nextTarget($context));
    }

    public function test_returns_null_when_history_is_only_warmup_sets(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 40.0, 'reps' => 10, 'is_warmup' => true],
            ],
            weightIncrement: 2.50,
            repMin: 8,
            repMax: 12,
        );

        $this->assertNull($this->strategy->nextTarget($context));
    }

    public function test_reps_at_max_triggers_weight_up(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
                ['weight' => 60.0, 'reps' => 12, 'is_warmup' => false],
            ],
            weightIncrement: 2.50,
            repMin: 8,
            repMax: 12,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(62.5, $target->weight);
        $this->assertSame(8, $target->reps);
        $this->assertSame('weight', $target->type);
    }

    public function test_reps_below_max_triggers_reps_up(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 60.0, 'reps' => 9, 'is_warmup' => false],
            ],
            weightIncrement: 2.50,
            repMin: 8,
            repMax: 12,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(60.0, $target->weight);
        $this->assertSame(10, $target->reps);
        $this->assertSame('reps', $target->type);
    }

    public function test_warmup_sets_are_excluded_from_top_set_selection(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                ['weight' => 100.0, 'reps' => 1, 'is_warmup' => true],
                ['weight' => 60.0, 'reps' => 8, 'is_warmup' => false],
            ],
            weightIncrement: 2.50,
            repMin: 8,
            repMax: 12,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        // 60x8 が repMin(8) ちょうどのため reps アップ扱いになる。
        $this->assertSame(60.0, $target->weight);
        $this->assertSame(9, $target->reps);
        $this->assertSame('reps', $target->type);
    }

    public function test_label(): void
    {
        $this->assertSame('ダブルプログレッション', $this->strategy->label());
    }
}
