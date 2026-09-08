<?php

namespace App\Services\Progression;

use App\Enums\ProgressionStrategyType;
use App\Models\Exercise;

/**
 * 種目(Exercise)の progression_strategy に応じて、対応する
 * {@see ProgressionStrategy} 実装を返す。
 *
 * Issue #27: 漸進法の分岐はこのクラス1箇所に閉じる。呼び出し側
 * (WorkoutProgressionSnapshotService・WorkoutController 等)は
 * ProgressionStrategy インターフェースしか知らず、if / match で
 * 実装クラスを選び分けることはしない。
 *
 * 新しい戦略を追加する場合、実装クラスを1つ追加してこのクラスの match に
 * 1行足すだけでよく、既存の3クラス・呼び出し側のコードは変更しない
 * (開放閉鎖の原則)。
 */
class ProgressionStrategyFactory
{
    public function __construct(
        private readonly DoubleProgressionStrategy $doubleProgressionStrategy,
        private readonly LinearProgressionStrategy $linearProgressionStrategy,
        private readonly FiveByFiveStrategy $fiveByFiveStrategy,
    ) {}

    public function for(Exercise $exercise): ProgressionStrategy
    {
        return $this->make($exercise->progression_strategy);
    }

    public function make(ProgressionStrategyType $type): ProgressionStrategy
    {
        return match ($type) {
            ProgressionStrategyType::Double => $this->doubleProgressionStrategy,
            ProgressionStrategyType::Linear => $this->linearProgressionStrategy,
            ProgressionStrategyType::FiveByFive => $this->fiveByFiveStrategy,
        };
    }
}
