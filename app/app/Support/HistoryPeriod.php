<?php

namespace App\Support;

/**
 * 期間フィルタ('3m' / '6m' / 'all')の共通ロジック。
 *
 * 種目別履歴(Issue #11 / HistoryController)と体重記録(Issue #21 /
 * BodyLogController)の両方で同じ3択・同じ「不正な値は 'all' にフォールバック」
 * という挙動を使うため、ここに集約する。
 */
class HistoryPeriod
{
    /**
     * 期間の選択肢とその月数。'all' は下限日付なし(月数 null)。
     *
     * @var array<string, int|null>
     */
    private const MONTHS = [
        '3m' => 3,
        '6m' => 6,
        'all' => null,
    ];

    /**
     * @return array<string, int|null>
     */
    public static function options(): array
    {
        return self::MONTHS;
    }

    /**
     * 未知の値は 'all' にフォールバックする。
     */
    public static function normalize(?string $period): string
    {
        return array_key_exists($period ?? '', self::MONTHS) ? $period : 'all';
    }

    /**
     * @param  string  $period  {@see normalize()} 済みの値であること
     */
    public static function sinceDate(string $period): ?string
    {
        $months = self::MONTHS[$period] ?? null;

        return $months !== null ? now()->subMonths($months)->toDateString() : null;
    }
}
