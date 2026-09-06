<?php

namespace App\Services;

use App\Repositories\WorkoutSetRepository;

/**
 * 部位バランス(push/pull)の可視化ロジック(Issue #24②)。
 *
 * Eloquent / DB に依存しない。集計(movement_type 別セット数)の取得は
 * {@see WorkoutSetRepository::movementTypeSetCounts()} の責務。
 *
 * 判断(Issue #24 で確定):
 *
 * 1. 指標は**セット数**を採用した(総挙上重量ではない)。
 *    総挙上重量だと、種目間で扱う重量レンジが大きく異なる(特に脚の種目は
 *    重量が大きくなりがち)ため、脚の貢献度が過大に見えてしまい、
 *    本題である「押す/引くの偏り」がぼやける。セット数なら「その動作
 *    パターンにどれだけ頻度を割いたか」を種目間で公平に比較できる。
 * 2. 対象期間は直近4週間(仕様書が例示していた期間をそのまま採用)。
 * 3. 判定には push + pull の合計セット数が {@see MIN_TOTAL_SETS} 件以上
 *    必要(数セットで比率を語らないため)。
 * 4. 偏りの閾値は、push/pull の多い方 ÷ 少ない方が {@see IMBALANCE_RATIO_THRESHOLD}
 *    (1.5)を超えたら警告する。「概ね1:1が理想、1:1.5程度までは許容範囲」
 *    という一般的なトレーニング指導の目安に合わせた。
 */
class MuscleBalanceService
{
    /**
     * 判定に必要な push + pull の最低合計セット数(判断3)。
     */
    public const MIN_TOTAL_SETS = 10;

    /**
     * 偏り警告の閾値(判断4)。多い方 ÷ 少ない方がこの値を超えたら警告する。
     */
    public const IMBALANCE_RATIO_THRESHOLD = 1.5;

    /**
     * @param  array<string, int>  $setCountsByMovementType  movement_type(push/pull/legs/core)をキーにしたセット数。
     *                                                       記録が無い movement_type のキーは無くてよい。
     * @return array{
     *     sufficient_data: bool,
     *     counts: array{push: int, pull: int, legs: int, core: int},
     *     push_pull_total: int,
     *     is_imbalanced: bool,
     *     dominant: 'push'|'pull'|null,
     *     ratio: float|null,
     * }
     */
    public function analyze(array $setCountsByMovementType): array
    {
        $counts = [
            'push' => $setCountsByMovementType['push'] ?? 0,
            'pull' => $setCountsByMovementType['pull'] ?? 0,
            'legs' => $setCountsByMovementType['legs'] ?? 0,
            'core' => $setCountsByMovementType['core'] ?? 0,
        ];

        $pushPullTotal = $counts['push'] + $counts['pull'];
        $sufficientData = $pushPullTotal >= self::MIN_TOTAL_SETS;

        if (! $sufficientData) {
            return [
                'sufficient_data' => false,
                'counts' => $counts,
                'push_pull_total' => $pushPullTotal,
                'is_imbalanced' => false,
                'dominant' => null,
                'ratio' => null,
            ];
        }

        $larger = max($counts['push'], $counts['pull']);
        $smaller = min($counts['push'], $counts['pull']);

        // 一方が0件(pushしかやっていない等)はゼロ除算を避けつつ、
        // 明確な偏りとして扱う(ratio は「非常に大きい」ことだけ分かればよく、
        // 画面側には null を返して比率の数値表示はさせない)。
        $isZeroDivide = $smaller === 0 && $larger > 0;
        $ratio = $isZeroDivide ? null : ($larger > 0 ? round($larger / $smaller, 2) : 1.0);
        $isImbalanced = $isZeroDivide || ($ratio !== null && $ratio > self::IMBALANCE_RATIO_THRESHOLD);

        return [
            'sufficient_data' => true,
            'counts' => $counts,
            'push_pull_total' => $pushPullTotal,
            'is_imbalanced' => $isImbalanced,
            'dominant' => $isImbalanced ? ($counts['push'] > $counts['pull'] ? 'push' : 'pull') : null,
            'ratio' => $ratio,
        ];
    }
}
