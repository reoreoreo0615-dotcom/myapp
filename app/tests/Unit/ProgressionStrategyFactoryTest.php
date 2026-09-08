<?php

namespace Tests\Unit;

use App\Enums\ProgressionStrategyType;
use App\Models\Exercise;
use App\Services\Progression\DoubleProgressionStrategy;
use App\Services\Progression\FiveByFiveStrategy;
use App\Services\Progression\LinearProgressionStrategy;
use App\Services\Progression\ProgressionStrategyFactory;
use App\Services\ProgressionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ProgressionStrategyFactory は Exercise の progression_strategy を見て
 * 対応する ProgressionStrategy 実装を返すだけの単純な組み立て役なので、
 * DB に触れず素の new で組み立てて検証する。
 */
class ProgressionStrategyFactoryTest extends TestCase
{
    private ProgressionStrategyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $progressionService = new ProgressionService;

        $this->factory = new ProgressionStrategyFactory(
            new DoubleProgressionStrategy($progressionService),
            new LinearProgressionStrategy($progressionService),
            new FiveByFiveStrategy($progressionService),
        );
    }

    public static function strategyProvider(): array
    {
        return [
            'double' => [ProgressionStrategyType::Double, DoubleProgressionStrategy::class],
            'linear' => [ProgressionStrategyType::Linear, LinearProgressionStrategy::class],
            'five_by_five' => [ProgressionStrategyType::FiveByFive, FiveByFiveStrategy::class],
        ];
    }

    #[DataProvider('strategyProvider')]
    public function test_for_returns_the_strategy_matching_the_exercise(
        ProgressionStrategyType $type,
        string $expectedClass,
    ): void {
        $exercise = new Exercise(['progression_strategy' => $type]);

        $strategy = $this->factory->for($exercise);

        $this->assertInstanceOf($expectedClass, $strategy);
    }

    #[DataProvider('strategyProvider')]
    public function test_make_returns_the_strategy_matching_the_type(
        ProgressionStrategyType $type,
        string $expectedClass,
    ): void {
        $this->assertInstanceOf($expectedClass, $this->factory->make($type));
    }
}
