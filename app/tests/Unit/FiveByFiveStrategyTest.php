<?php

namespace Tests\Unit;

use App\Services\Progression\FiveByFiveStrategy;
use App\Services\Progression\ProgressionContext;
use App\Services\ProgressionService;
use PHPUnit\Framework\TestCase;

class FiveByFiveStrategyTest extends TestCase
{
    private FiveByFiveStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new FiveByFiveStrategy(new ProgressionService);
    }

    private function set(float $weight, int $reps, bool $isWarmup = false): array
    {
        return ['weight' => $weight, 'reps' => $reps, 'is_warmup' => $isWarmup];
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

    public function test_returns_null_when_only_warmup_sets(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [$this->set(60.0, 5, isWarmup: true)],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $this->assertNull($this->strategy->nextTarget($context));
    }

    public function test_five_sets_of_five_reps_triggers_weight_up(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
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

    /**
     * 4セット目までは5回達成しているが、最後の1セットが4回で終わった
     * (5セット全達成ではない)場合は重量を上げない。
     */
    public function test_missing_one_set_keeps_weight(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 4),
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(60.0, $target->weight);
        $this->assertSame(5, $target->reps);
        $this->assertSame('reps', $target->type);
    }

    /**
     * 記録されたセット数自体が5セットに満たない場合も「全て達成」とは
     * 見なさない。
     */
    public function test_fewer_than_five_sets_keeps_weight(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(60.0, $target->weight);
        $this->assertSame('reps', $target->type);
    }

    /**
     * 6セット目まで記録されていても(必要数の5セットを超えていても)、
     * 全セットが目標レップ以上なら重量を上げる。
     */
    public function test_more_than_five_qualifying_sets_still_triggers_weight_up(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(60.0, 5),
                $this->set(60.0, 6),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame('weight', $target->type);
    }

    /**
     * トップセットと異なる重量(ドロップセット等)は達成数に数えない。
     * トップウェイトでの5セット未達なら重量アップしない。
     */
    public function test_sets_at_a_different_weight_do_not_count_toward_achieved_sets(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                // トップウェイト(60.0)ではない5セット目 → 未達成扱い。
                $this->set(50.0, 5),
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(60.0, $target->weight);
        $this->assertSame('reps', $target->type);
    }

    public function test_warmup_sets_are_excluded_from_both_top_set_and_achieved_count(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: [
                $this->set(100.0, 5, isWarmup: true),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
                $this->set(60.0, 5),
            ],
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame(62.5, $target->weight);
        $this->assertSame('weight', $target->type);
    }

    /**
     * reps が repMax ちょうど(境界値)は「達成」に含める(>=)。
     */
    public function test_reps_exactly_at_rep_max_counts_as_achieved(): void
    {
        $context = new ProgressionContext(
            lastWorkingSets: array_fill(0, 5, $this->set(60.0, 5)),
            weightIncrement: 2.50,
            repMin: 5,
            repMax: 5,
        );

        $target = $this->strategy->nextTarget($context);

        $this->assertNotNull($target);
        $this->assertSame('weight', $target->type);
    }

    public function test_label(): void
    {
        $this->assertSame('5×5', $this->strategy->label());
    }
}
