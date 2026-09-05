<?php

namespace App\Services;

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
 * 意図的に DB アクセスを持たない。生データの取得は
 * {@see WorkoutSetRepository} の責務。
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
     * @param  array<int, array{workout_id: int, performed_on: string, weight: float, reps: int}>  $topSetsPerWorkout  performed_on 昇順、ワークアウトごとのトップセット1件ずつ
     * @return array{metric: '1rm'|'reps', metric_label: string, points: array<int, array{date: string, value: float, weight: float, reps: int}>}
     */
    public function buildChart(bool $isBodyweight, array $topSetsPerWorkout): array
    {
        $points = array_map(
            fn (array $row): array => [
                'date' => $row['performed_on'],
                'value' => $isBodyweight
                    ? (float) $row['reps']
                    : $this->progressionService->estimateOneRepMax($row['weight'], $row['reps']),
                'weight' => $row['weight'],
                'reps' => $row['reps'],
            ],
            $topSetsPerWorkout,
        );

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
}
