<?php

namespace App\Services;

use App\Models\Exercise;
use App\Repositories\WorkoutSetRepository;

/**
 * 停滞(プラトー)検知と、次の一手(デロード / レップレンジ変更)の提案に
 * 必要な数値を算出する。
 *
 * 意図的に Eloquent / DB に依存しない。すべての値は引数で受け取り、
 * 戻り値は素の配列・スカラのみ({@see ProgressionService} と同じ境界)。
 * 実データ(種目ごとのセッション履歴)の取得は
 * {@see WorkoutSetRepository} の責務、指標(推定1RM /
 * レップ数)への変換と種目メタ情報との突き合わせは {@see PlateauAnalysisService}
 * の責務とする。
 *
 * 判定ロジック(Issue #24 で確定した判断):
 *
 * 1. N は「回数ベース(直近3セッション)」を採用した。
 *    仕様書には「3週間」とあったが、週1回しかやらない種目と週3回やる種目とで
 *    「3週間」の意味(試行回数)が大きく変わってしまう。回数ベースなら
 *    種目の実施頻度によらず「同じ試行回数だけ更新が無い」という一定の基準になる。
 * 2. 「更新がない(横ばい)」と「下がっている」は区別する。
 *    直近 N セッション「すべての」隣接セッション間が厳密に下降し続けている
 *    (session[i] < session[i-1] が全区間で成立する)場合だけ「declining」とする。
 *    横ばいが混じる場合(例: 直近3回が 60→60→55 のように、最後の1回だけ
 *    落ちた)は「stagnant」として扱う。1回だけの不調を「下降傾向」と
 *    断定しないための条件(体調不良の1回と、複数回にわたる本当の下降トレンドは
 *    違う、という要求に対応)。
 * 3. 判定に必要な最低セッション数は N+1。
 *    「それ以前の最大値(baseline)」を確定できるだけの記録が無い場合は
 *    判定しない(記録が少ない期間で誤判定しないため)。
 */
class PlateauService
{
    /**
     * 停滞判定に使うセッション数(判断1: 回数ベース)。
     */
    public const SESSIONS_REQUIRED = 3;

    /**
     * デロード提案で重量を落とす割合。
     */
    private const DELOAD_WEIGHT_RATIO = 0.9;

    /**
     * デロード提案(自重種目・reps基準)でレップ数を落とす割合。
     */
    private const DELOAD_REPS_RATIO = 0.9;

    /**
     * デロードを試す想定セッション数(提案文言用の情報)。
     */
    private const DELOAD_SESSIONS = 3;

    /**
     * レップレンジ変更提案でレンジを下げる幅。
     */
    private const REP_RANGE_MIN_SHIFT = 3;

    private const REP_RANGE_MAX_SHIFT = 4;

    /**
     * @param  array<int, array{weight: float, reps: int, metric: float}>  $sessions  performed_on 昇順。
     *                                                                                各要素はそのセッションのトップセット
     *                                                                                (ウォームアップ除く、呼び出し側で確定済み)。
     *                                                                                metric は通常種目=推定1RM、自重種目=reps(Issue #17)。
     * @param  float  $weightIncrement  デロード提案の丸め幅に使う({@see Exercise::$weight_increment})。
     * @return array{
     *     status: 'stagnant'|'declining',
     *     sessions_without_update: int,
     *     baseline: array{weight: float, reps: int},
     *     suggestions: array<int, array<string, mixed>>,
     * }|null
     */
    public function detect(
        array $sessions,
        float $weightIncrement,
        int $repMin,
        int $repMax,
    ): ?array {
        $count = count($sessions);

        if ($count < self::SESSIONS_REQUIRED + 1) {
            return null;
        }

        $sessions = array_values($sessions);
        $recent = array_slice($sessions, -self::SESSIONS_REQUIRED);
        $prior = array_slice($sessions, 0, $count - self::SESSIONS_REQUIRED);

        $baseline = $this->maxByMetric($prior);
        $maxRecentMetric = max(array_map(fn (array $s): float => $s['metric'], $recent));

        // decimal(5,2) 由来の float 誤差を吸収してから比較する。
        if (round($maxRecentMetric, 2) > round($baseline['metric'], 2)) {
            return null;
        }

        $status = $this->isDeclining($recent) ? 'declining' : 'stagnant';

        return [
            'status' => $status,
            'sessions_without_update' => self::SESSIONS_REQUIRED,
            'baseline' => [
                'weight' => $baseline['weight'],
                'reps' => $baseline['reps'],
            ],
            'suggestions' => $this->buildSuggestions($baseline, $weightIncrement, $repMin, $repMax),
        ];
    }

    /**
     * 直近 N セッションの隣接するすべてのペアが厳密に下降しているときだけ true。
     * 横ばいの区間が1つでも混じれば(=下降が連続していなければ)false になる。
     * これにより、直近1回だけの不調(それ以前は横ばい)を「下降傾向」と
     * 断定しないようにしている。
     *
     * @param  array<int, array{weight: float, reps: int, metric: float}>  $recent
     */
    private function isDeclining(array $recent): bool
    {
        for ($i = 1; $i < count($recent); $i++) {
            if (round($recent[$i]['metric'], 2) >= round($recent[$i - 1]['metric'], 2)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{weight: float, reps: int, metric: float}>  $sessions
     * @return array{weight: float, reps: int, metric: float}
     */
    private function maxByMetric(array $sessions): array
    {
        $best = $sessions[0];

        foreach ($sessions as $session) {
            if (round($session['metric'], 2) > round($best['metric'], 2)) {
                $best = $session;
            }
        }

        return $best;
    }

    /**
     * 判断: 提案は2種類出す。
     *  - デロード(重量が乗っている種目は重量を10%落とす。加重0の自重種目は
     *    reps を10%落とす。「押し付けない」ため、実行するかは呼び出し側=画面の
     *    表現に委ねる。ここでは数値の算出のみ行う)
     *  - レップレンジ変更(現在の目標レンジを下げる。例 8-12 → 5-8)
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSuggestions(array $baseline, float $weightIncrement, int $repMin, int $repMax): array
    {
        $deload = $baseline['weight'] > 0.0
            ? $this->weightDeloadSuggestion($baseline['weight'], $weightIncrement)
            : $this->repsDeloadSuggestion($baseline['reps']);

        return [
            $deload,
            $this->repRangeSuggestion($repMin, $repMax),
        ];
    }

    private function weightDeloadSuggestion(float $currentWeight, float $weightIncrement): array
    {
        $increment = $weightIncrement > 0.0 ? $weightIncrement : 0.5;

        $raw = $currentWeight * self::DELOAD_WEIGHT_RATIO;
        $deloadWeight = round($raw / $increment) * $increment;

        // 丸めた結果、現在の重量以上になってしまう(軽量種目で increment が
        // 粗いなど)場合は、最低でも1刻み分は下げる。
        if ($deloadWeight >= $currentWeight) {
            $deloadWeight = $currentWeight - $increment;
        }

        $deloadWeight = round(max($deloadWeight, 0.0), 2);

        return [
            'type' => 'deload_weight',
            'current_weight' => round($currentWeight, 2),
            'deload_weight' => $deloadWeight,
            'sessions' => self::DELOAD_SESSIONS,
        ];
    }

    private function repsDeloadSuggestion(int $currentReps): array
    {
        $deloadReps = (int) round($currentReps * self::DELOAD_REPS_RATIO);

        if ($deloadReps >= $currentReps) {
            $deloadReps = $currentReps - 1;
        }

        $deloadReps = max($deloadReps, 1);

        return [
            'type' => 'deload_reps',
            'current_reps' => $currentReps,
            'deload_reps' => $deloadReps,
            'sessions' => self::DELOAD_SESSIONS,
        ];
    }

    private function repRangeSuggestion(int $repMin, int $repMax): array
    {
        $suggestedMin = max(1, $repMin - self::REP_RANGE_MIN_SHIFT);
        $suggestedMax = max($suggestedMin + 1, $repMax - self::REP_RANGE_MAX_SHIFT);

        return [
            'type' => 'rep_range',
            'current_rep_min' => $repMin,
            'current_rep_max' => $repMax,
            'suggested_rep_min' => $suggestedMin,
            'suggested_rep_max' => $suggestedMax,
        ];
    }
}
