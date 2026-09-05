<?php

namespace App\Services;

use App\Repositories\BodyLogRepository;
use App\Repositories\WorkoutSetRepository;

/**
 * 種目別履歴画面(Issue #11)向けに、推移グラフと自己ベストの表示データを
 * 組み立てる。
 *
 * Issue #17 の決定: 自重種目(is_bodyweight = true)は推定1RMが常に0(or
 * ほぼ無意味)になるため、縦軸をトップセットのレップ数に切り替える。
 *
 *     is_bodyweight = false → metric '1rm',  縦軸「推定1RM (kg)」
 *     is_bodyweight = true  → metric 'reps', 縦軸「レップ数 (回)」
 *
 * {@see ProgressionService::estimateOneRepMax()} 自体は変更しない
 * (既存テストを守るため)。切り替えはこのクラスの責務とする。
 *
 * Issue #21: 体重記録と組み合わせた相対筋力(体重比)の算出もここで行う。
 *
 * 意図的に DB アクセスを持たない。生データの取得は
 * {@see WorkoutSetRepository} / {@see BodyLogRepository} の責務。
 */
class ExerciseHistoryService
{
    public function __construct(
        private readonly ProgressionService $progressionService,
    ) {}

    /**
     * 推移グラフのデータを組み立てる。
     *
     * 加重ありの自重種目(懸垂に5kg吊るす等)は reps を主軸にしつつ、
     * 各点に weight も持たせる(フロント側でツールチップ/ラベルとして併記する)。
     *
     * 体重比(Issue #21): 各ワークアウト日に「その日以前で最も新しい体重記録」を
     * 対応させ、記録が無い期間は null のまま埋めない(0や推測値で埋めない)。
     *
     *  - 通常種目: bodyweight_ratio = 推定1RM ÷ 体重
     *  - 自重種目: 「体重 + 加重」を実質的な負荷とみなした概算1RM
     *    (estimated_one_rep_max_with_bodyweight)を補助情報として出し、
     *    bodyweight_ratio はその概算1RM ÷ 体重。
     *    種目ごとの体にかかる割合(懸垂はほぼ全体重、腕立ては約65%等)の
     *    係数は導入しない(Issue #21の決定。既定の自重種目はほぼ全体重が
     *    かかる前提で係数1.0として扱う、という単純化)。
     *
     * @param  array<int, array{workout_id: int, performed_on: string, weight: float, reps: int}>  $topSetsPerWorkout  performed_on 昇順、ワークアウトごとのトップセット1件ずつ
     * @param  array<int, array{measured_on: string, weight_kg: float}>  $bodyLogs  measured_on 昇順、全期間
     * @return array{metric: '1rm'|'reps', metric_label: string, points: array<int, array{date: string, value: float, weight: float, reps: int, body_weight_kg: float|null, bodyweight_ratio: float|null, estimated_one_rep_max_with_bodyweight: float|null}>}
     */
    public function buildChart(bool $isBodyweight, array $topSetsPerWorkout, array $bodyLogs = []): array
    {
        $bodyWeightAtEachDate = $this->nearestPastBodyWeights(
            array_column($topSetsPerWorkout, 'performed_on'),
            $bodyLogs,
        );

        $points = [];

        foreach ($topSetsPerWorkout as $index => $row) {
            $bodyWeight = $bodyWeightAtEachDate[$index];

            $value = $isBodyweight
                ? (float) $row['reps']
                : $this->progressionService->estimateOneRepMax($row['weight'], $row['reps']);

            $estimatedOneRepMaxWithBodyweight = null;
            $bodyweightRatio = null;

            if ($bodyWeight !== null) {
                if ($isBodyweight) {
                    // 「体重 + 加重」を実質的な負荷とみなした概算1RM。
                    $estimatedOneRepMaxWithBodyweight = $this->progressionService->estimateOneRepMax(
                        $bodyWeight + $row['weight'],
                        $row['reps'],
                    );
                    $bodyweightRatio = round($estimatedOneRepMaxWithBodyweight / $bodyWeight, 2);
                } else {
                    $bodyweightRatio = round($value / $bodyWeight, 2);
                }
            }

            $points[] = [
                'date' => $row['performed_on'],
                'value' => $value,
                'weight' => $row['weight'],
                'reps' => $row['reps'],
                'body_weight_kg' => $bodyWeight,
                'bodyweight_ratio' => $bodyweightRatio,
                'estimated_one_rep_max_with_bodyweight' => $estimatedOneRepMaxWithBodyweight,
            ];
        }

        return [
            'metric' => $isBodyweight ? 'reps' : '1rm',
            'metric_label' => $isBodyweight ? 'レップ数 (回)' : '推定1RM (kg)',
            'points' => $points,
        ];
    }

    /**
     * 自己ベストの表示データを組み立てる。
     *
     * $raw はウォームアップを除いた全期間の集計値
     * ({@see WorkoutSetRepository::personalBest()})。
     * 記録が1件も無い場合は null を返す。
     *
     * @param  array{max_weight: float|null, max_reps: int|null, max_estimated_1rm: float|null}  $raw
     * @return array{metric: '1rm', max_weight: float, max_estimated_1rm: float}|array{metric: 'reps', max_weight: float, max_reps: int}|null
     */
    public function buildPersonalBest(bool $isBodyweight, array $raw): ?array
    {
        if ($raw['max_weight'] === null) {
            return null;
        }

        if ($isBodyweight) {
            return [
                'metric' => 'reps',
                'max_weight' => $raw['max_weight'],
                'max_reps' => $raw['max_reps'] ?? 0,
            ];
        }

        return [
            'metric' => '1rm',
            'max_weight' => $raw['max_weight'],
            'max_estimated_1rm' => $raw['max_estimated_1rm'] ?? 0.0,
        ];
    }

    /**
     * グラフの点のうち、体重比が出ている最新のものを1件返す(画面上部の
     * 「現在の体重比」表示用)。体重記録が無い期間しか無ければ null。
     *
     * 自重種目の場合は、併せて「体重+加重」の概算1RM(係数1.0)も返す
     * (画面側で補助情報として併記するため)。通常種目では常に null。
     *
     * @param  array<int, array{date: string, bodyweight_ratio: float|null, body_weight_kg: float|null, estimated_one_rep_max_with_bodyweight: float|null}>  $points  date 昇順
     * @return array{date: string, ratio: float, body_weight_kg: float, estimated_one_rep_max_with_bodyweight: float|null}|null
     */
    public function latestBodyweightRatio(array $points): ?array
    {
        for ($i = count($points) - 1; $i >= 0; $i--) {
            if ($points[$i]['bodyweight_ratio'] !== null) {
                return [
                    'date' => $points[$i]['date'],
                    'ratio' => $points[$i]['bodyweight_ratio'],
                    'body_weight_kg' => $points[$i]['body_weight_kg'],
                    'estimated_one_rep_max_with_bodyweight' => $points[$i]['estimated_one_rep_max_with_bodyweight'],
                ];
            }
        }

        return null;
    }

    /**
     * $dates の各要素について、「その日以前で最も新しい体重記録の体重」を
     * 対応する位置に埋めた配列を返す(見つからなければ null)。
     *
     * $dates・$bodyLogs はともに日付昇順であることを前提に、2本のポインタで
     * 線形時間(O(件数の合計))で処理する(ワークアウト日ごとに体重記録全件を
     * 走査するようなことはしない)。
     *
     * @param  array<int, string>  $dates  'Y-m-d'、昇順
     * @param  array<int, array{measured_on: string, weight_kg: float}>  $bodyLogs  measured_on 昇順
     * @return array<int, float|null> $dates と同じ添字・同じ順序
     */
    private function nearestPastBodyWeights(array $dates, array $bodyLogs): array
    {
        $result = [];
        $bodyLogIndex = 0;
        $currentWeight = null;
        $bodyLogCount = count($bodyLogs);

        foreach (array_values($dates) as $i => $date) {
            while ($bodyLogIndex < $bodyLogCount && $bodyLogs[$bodyLogIndex]['measured_on'] <= $date) {
                $currentWeight = $bodyLogs[$bodyLogIndex]['weight_kg'];
                $bodyLogIndex++;
            }

            $result[$i] = $currentWeight;
        }

        return $result;
    }
}
