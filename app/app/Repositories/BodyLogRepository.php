<?php

namespace App\Repositories;

use App\Models\BodyLog;

/**
 * body_logs への DB アクセスを担当するクエリクラス(Issue #21)。
 */
class BodyLogRepository
{
    /**
     * 体重記録一覧(日付降順)。期間フィルタは画面の一覧・グラフ表示用。
     *
     * @return array<int, array{id: int, measured_on: string, weight_kg: float, body_fat_percentage: float|null, memo: string|null}>
     */
    public function listForUser(int $userId, ?string $sinceDate): array
    {
        return BodyLog::query()
            ->where('user_id', $userId)
            ->when(
                $sinceDate !== null,
                fn ($query) => $query->where('measured_on', '>=', $sinceDate),
            )
            ->orderByDesc('measured_on')
            ->get(['id', 'measured_on', 'weight_kg', 'body_fat_percentage', 'memo'])
            ->map(fn (BodyLog $log): array => [
                'id' => $log->id,
                'measured_on' => $log->measured_on->format('Y-m-d'),
                'weight_kg' => (float) $log->weight_kg,
                'body_fat_percentage' => $log->body_fat_percentage !== null ? (float) $log->body_fat_percentage : null,
                'memo' => $log->memo,
            ])
            ->all();
    }

    /**
     * 全期間・日付昇順の体重記録。
     *
     * 種目別履歴の体重比(Issue #21)は「ワークアウト日に最も近い過去の体重記録」を
     * 使うため、履歴側の期間フィルタとは独立に全期間分が必要
     * (フィルタで直近3ヶ月に絞っていても、直近過去の体重記録はそれより前かもしれない)。
     *
     * @return array<int, array{measured_on: string, weight_kg: float}>
     */
    public function allMeasurementsAscending(int $userId): array
    {
        return BodyLog::query()
            ->where('user_id', $userId)
            ->orderBy('measured_on')
            ->get(['measured_on', 'weight_kg'])
            ->map(fn (BodyLog $log): array => [
                'measured_on' => $log->measured_on->format('Y-m-d'),
                'weight_kg' => (float) $log->weight_kg,
            ])
            ->all();
    }

    /**
     * ダッシュボードの体重タイル用に、直近2件(新しい順)を返す。
     * 2件目は「直近の変化」の算出に使う。
     *
     * @return array<int, array{measured_on: string, weight_kg: float}>
     */
    public function latestTwoForUser(int $userId): array
    {
        return BodyLog::query()
            ->where('user_id', $userId)
            ->orderByDesc('measured_on')
            ->limit(2)
            ->get(['measured_on', 'weight_kg'])
            ->map(fn (BodyLog $log): array => [
                'measured_on' => $log->measured_on->format('Y-m-d'),
                'weight_kg' => (float) $log->weight_kg,
            ])
            ->all();
    }
}
