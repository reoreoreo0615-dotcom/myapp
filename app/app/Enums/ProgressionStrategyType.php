<?php

namespace App\Enums;

use App\Services\Progression\ProgressionStrategy;
use App\Services\Progression\ProgressionStrategyFactory;

/**
 * exercises.progression_strategy の取りうる値。
 *
 * この enum 自体は「どの値が存在するか」だけを表す。実際の目標値の計算
 * ロジックは {@see ProgressionStrategyFactory} が
 * この値を見て対応する {@see ProgressionStrategy}
 * 実装に振り分ける(分岐は Factory 1箇所に閉じる)。
 */
enum ProgressionStrategyType: string
{
    case Double = 'double';
    case Linear = 'linear';
    case FiveByFive = 'five_by_five';
}
