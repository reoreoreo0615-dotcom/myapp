<?php

namespace Tests\Unit;

use App\Services\Progression\AbstractProgressionStrategy;
use App\Services\Progression\DoubleProgressionStrategy;
use App\Services\Progression\FiveByFiveStrategy;
use App\Services\Progression\LinearProgressionStrategy;
use App\Services\Progression\ProgressionContext;
use App\Services\Progression\ProgressionStrategy;
use App\Services\Progression\ProgressionTarget;
use App\Services\ProgressionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * 抽象クラス AbstractProgressionStrategy が担う共通処理(継承で共有される部分)を検証する。
 */
class AbstractProgressionStrategyTest extends TestCase
{
    public static function concreteStrategies(): array
    {
        return [
            'ダブルプログレッション' => [DoubleProgressionStrategy::class],
            'リニアプログレッション' => [LinearProgressionStrategy::class],
            '5x5' => [FiveByFiveStrategy::class],
        ];
    }

    #[DataProvider('concreteStrategies')]
    public function test_all_strategies_extend_the_abstract_class(string $class): void
    {
        $strategy = new $class(new ProgressionService);

        $this->assertInstanceOf(AbstractProgressionStrategy::class, $strategy);
        // 継承してもインターフェースは維持され、呼び出し側は引き続きこれだけを知っていればよい
        $this->assertInstanceOf(ProgressionStrategy::class, $strategy);
    }

    #[DataProvider('concreteStrategies')]
    public function test_no_history_returns_null_for_every_strategy(string $class): void
    {
        $strategy = new $class(new ProgressionService);
        $context = new ProgressionContext([], 2.5, 8, 12);

        // 「履歴が無ければ目標を出さない」は基底クラスで一度だけ実装されている
        $this->assertNull($strategy->nextTarget($context));
    }

    #[DataProvider('concreteStrategies')]
    public function test_warmup_only_history_returns_null_for_every_strategy(string $class): void
    {
        $strategy = new $class(new ProgressionService);
        $context = new ProgressionContext(
            [['weight' => 40.0, 'reps' => 10, 'is_warmup' => true]],
            2.5,
            8,
            12,
        );

        $this->assertNull($strategy->nextTarget($context));
    }

    public function test_next_target_is_final_so_subclasses_cannot_change_the_procedure(): void
    {
        $method = new ReflectionMethod(AbstractProgressionStrategy::class, 'nextTarget');

        $this->assertTrue($method->isFinal());
    }

    public function test_calculate_is_abstract_and_must_be_implemented_by_subclasses(): void
    {
        $method = new ReflectionMethod(AbstractProgressionStrategy::class, 'calculate');

        $this->assertTrue($method->isAbstract());
        $this->assertTrue($method->isProtected());
    }

    public function test_a_new_strategy_only_needs_to_implement_calculate(): void
    {
        // 新しい漸進法を追加するとき、共通処理を書き直さなくてよいことを示す
        $fixedStrategy = new class(new ProgressionService) extends AbstractProgressionStrategy
        {
            protected function calculate(array $topSet, ProgressionContext $context): ?ProgressionTarget
            {
                return new ProgressionTarget($this->roundWeight($topSet['weight'] + 0.004), 99, 'reps');
            }

            public function label(): string
            {
                return 'テスト用';
            }
        };

        $this->assertNull($fixedStrategy->nextTarget(new ProgressionContext([], 2.5, 8, 12)));

        $target = $fixedStrategy->nextTarget(new ProgressionContext(
            [['weight' => 60.0, 'reps' => 8, 'is_warmup' => false]],
            2.5,
            8,
            12,
        ));

        // roundWeight() も基底クラスから継承して使える(小数第2位に丸まる)
        $this->assertSame(60.0, $target->weight);
        $this->assertSame(99, $target->reps);
    }
}
