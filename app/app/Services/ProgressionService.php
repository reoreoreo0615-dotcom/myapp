<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * 漸進的過負荷(ダブルプログレッション)ロジックと推定1RM計算。
 *
 * 意図的に Eloquent / DB に依存しない。すべての値は引数で受け取り、
 * 戻り値は素の配列・スカラのみ。
 *
 * このクラスに DB アクセスを追加しないこと。テスタビリティのための
 * 境界であり、実データの取得は App\Repositories\WorkoutSetRepository の責務。
 */
class ProgressionService
{
    /**
     * 前回のトップセットから、今日のワークアウトで提示する目標を計算する(ダブルプログレッション)。
     *
     * - 前回の記録が無い($lastWeight または $lastReps が null)場合は null を返す(提示なし)。
     * - 前回のレップ数が目標上限(rep_max)以上なら、重量を increment 分増やし
     *   レップを目標下限(rep_min)にリセットする(重量アップ)。
     * - そうでなければ、重量は据え置きでレップを 1 回増やす(レップアップ)。
     *
     * @return array{weight: float, reps: int, type: 'weight'|'reps'}|null
     */
    public function nextTarget(
        ?float $lastWeight,
        ?int $lastReps,
        float $increment,
        int $repMin,
        int $repMax,
    ): ?array {
        if ($lastWeight === null || $lastReps === null) {
            return null;
        }

        if ($lastReps >= $repMax) {
            return [
                'weight' => $this->roundWeight($lastWeight + $increment),
                'reps' => $repMin,
                'type' => 'weight',
            ];
        }

        return [
            'weight' => $this->roundWeight($lastWeight),
            'reps' => $lastReps + 1,
            'type' => 'reps',
        ];
    }

    /**
     * Epley 式による推定1RM。 1RM = weight * (1 + reps / 30)
     *
     * - reps が 1 のときは、定義上その重量自体が 1RM のため weight をそのまま返す
     *   (Epley 式をそのまま適用すると reps=1 でも weight より大きい値になってしまうため)。
     * - weight が 0(自重種目で加重なしのセット)のときは 0.0 を返す。
     * - reps が 0 以下のときは InvalidArgumentException を投げる。
     *
     * 戻り値は小数第1位まで四捨五入する。
     */
    public function estimateOneRepMax(float $weight, int $reps): float
    {
        if ($reps <= 0) {
            throw new InvalidArgumentException('reps must be greater than 0.');
        }

        if ($weight === 0.0) {
            return 0.0;
        }

        if ($reps === 1) {
            return round($weight, 1);
        }

        return round($weight * (1 + $reps / 30), 1);
    }

    /**
     * セット配列からトップセットを選ぶ。
     *
     * トップセットの定義: 最大重量のセット。同重量が複数あれば最大レップのもの。
     * 「最後のセット」ではない。is_warmup === true のセットは必ず除外する。
     *
     * @param  array<int, array{weight: float, reps: int, is_warmup: bool}>  $sets
     * @return array{weight: float, reps: int, is_warmup: bool}|null
     */
    public function pickTopSet(array $sets): ?array
    {
        $workingSets = array_values(array_filter(
            $sets,
            fn (array $set): bool => ! (bool) ($set['is_warmup'] ?? false),
        ));

        if ($workingSets === []) {
            return null;
        }

        usort($workingSets, function (array $a, array $b): int {
            // decimal(5,2) 由来の float 誤差を吸収するため、比較前に丸める。
            $weightComparison = round((float) $b['weight'], 2) <=> round((float) $a['weight'], 2);

            if ($weightComparison !== 0) {
                return $weightComparison;
            }

            return $b['reps'] <=> $a['reps'];
        });

        return $workingSets[0];
    }

    /**
     * decimal(5,2) の重量として意味のある精度に丸める。
     *
     * float 演算そのものの丸め誤差(0.1 + 0.2 のような)を DB に保存する前に
     * 吸収するための防御的な丸め。
     */
    private function roundWeight(float $weight): float
    {
        return round($weight, 2);
    }
}
