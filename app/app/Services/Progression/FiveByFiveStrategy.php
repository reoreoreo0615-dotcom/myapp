<?php

namespace App\Services\Progression;

/**
 * 5×5 プログレッション。
 *
 * 「5回×5セットを全て達成したら重量を上げる」の判定条件について:
 *
 * - 現在のスキーマには種目ごとの「目標セット数」が無い
 *   (`routine_exercises.target_sets` はルーティン×種目単位であり、
 *   メニューなしのワークアウトでは存在しないため、この戦略の入力
 *   {@see ProgressionContext} からは参照できない)。そのため必要セット数は
 *   このクラスの定数 REQUIRED_SETS = 5 として固定した(戦略名そのものが
 *   「5×5」であるため、可変にする実用上の動機が薄いと判断)。
 * - 1セットあたりの「達成」reps のしきい値は、既存の目標レップ上限
 *   (exercises.target_rep_max)を流用する。ダブルプログレッションで
 *   repMax が「重量を上げる引き金」として使われているのと同じ役割。
 *   5×5 用に運用する種目は target_rep_min = target_rep_max = 5 を
 *   想定しているが、この値の強制はバリデーション側の責務ではなく
 *   種目マスタ管理側の運用に委ねる。
 * - 前回の作業セットのうち、トップセット(最大重量)と同じ重量
 *   (decimal 由来の float 誤差を吸収するため小数第2位で比較)かつ
 *   reps >= repMax のセットを「達成」とみなし、その件数が
 *   REQUIRED_SETS 以上なら重量を上げる。1セットでも届かなければ、
 *   同じ重量・同じ目標レップで据え置く(type = 'reps' として扱う。
 *   ダブルプログレッションの「レップアップ」とは異なり目標レップ数自体は
 *   変わらないが、「重量は上がっていない」という表示上の意味は同じ)。
 */
final class FiveByFiveStrategy extends AbstractProgressionStrategy
{
    private const REQUIRED_SETS = 5;

    protected function calculate(array $topSet, ProgressionContext $context): ?ProgressionTarget
    {
        $topWeight = $this->roundWeight((float) $topSet['weight']);
        $achievedSets = 0;

        foreach ($context->lastWorkingSets as $set) {
            if ((bool) ($set['is_warmup'] ?? false)) {
                continue;
            }

            if ($this->roundWeight((float) $set['weight']) !== $topWeight) {
                continue;
            }

            if ($set['reps'] >= $context->repMax) {
                $achievedSets++;
            }
        }

        if ($achievedSets >= self::REQUIRED_SETS) {
            return new ProgressionTarget(
                weight: $this->roundWeight($topWeight + $context->weightIncrement),
                reps: $context->repMax,
                type: 'weight',
            );
        }

        return new ProgressionTarget(
            weight: $topWeight,
            reps: $context->repMax,
            type: 'reps',
        );
    }

    public function label(): string
    {
        return '5×5';
    }
}
