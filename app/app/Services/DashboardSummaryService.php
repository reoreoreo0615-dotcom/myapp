<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\Workout;
use App\Repositories\BodyLogRepository;
use App\Repositories\WorkoutSetRepository;
use Illuminate\Support\Carbon;

/**
 * ダッシュボード(Issue #4)の4タイル分の表示データを組み立てる。
 *
 * 生データの取得は {@see WorkoutSetRepository} の責務。このクラスは
 * 週の境界の決定、連続週数の判定、「更新」判定などの表示ロジックを持つ。
 *
 * 判断した3点:
 *  1. 週の境界は月曜始まり(ISO 8601 に合わせる)。
 *  2. 連続トレーニング週数は、今週まだ記録が無くても即座には途切れさせない
 *     (今週はまだ終わっていないため)。先週まで遡って記録が無ければ0とする。
 *  3. 集計は workouts.finished_at の有無を問わない。進行中のワークアウトでも
 *     記録済みのセットは実績として扱う({@see WorkoutSetRepository} の
 *     他の集計メソッド(personalBest 等)も同様に finished_at を見ていないため、
 *     ダッシュボードだけ挙動を変えると数値の整合性が崩れる)。
 *
 * Issue #24: 停滞している種目の一覧({@see PlateauAnalysisService})と
 * 部位バランス({@see MuscleBalanceService})もここで組み立てる。
 */
class DashboardSummaryService
{
    /**
     * 部位バランスの集計対象期間(直近4週間。{@see MuscleBalanceService} の判断2)。
     */
    private const MUSCLE_BALANCE_PERIOD_WEEKS = 4;

    public function __construct(
        private readonly WorkoutSetRepository $workoutSetRepository,
        private readonly BodyLogRepository $bodyLogRepository,
        private readonly PlateauAnalysisService $plateauAnalysisService,
        private readonly MuscleBalanceService $muscleBalanceService,
    ) {}

    /**
     * @return array{
     *     hasRecords: bool,
     *     weeklyVolume: array{thisWeek: float, lastWeek: float, changePercent: float|null},
     *     streakWeeks: int,
     *     monthlyRecordUpdates: int,
     *     latestPersonalBest: array{exerciseName: string, isBodyweight: bool, weight: float, reps: int, date: string}|null,
     *     activeWorkoutId: int|null,
     *     routinesCount: int,
     *     bodyWeight: array{current: float, measuredOn: string, changeFromPrevious: float|null}|null,
     *     plateauExercises: array<int, array{exerciseId: int, exerciseName: string, isBodyweight: bool, status: 'stagnant'|'declining', sessionsWithoutUpdate: int, baseline: array{weight: float, reps: int}, suggestions: array<int, array<string, mixed>>}>,
     *     muscleBalance: array{sufficientData: bool, counts: array{push: int, pull: int, legs: int, core: int}, pushPullTotal: int, isImbalanced: bool, dominant: 'push'|'pull'|null, ratio: float|null},
     * }
     */
    public function build(int $userId): array
    {
        $now = now();
        $thisWeekStart = $now->clone()->startOfWeek(Carbon::MONDAY)->toDateString();
        $monthStart = $now->clone()->startOfMonth()->toDateString();

        $volume = $this->workoutSetRepository->weeklyVolume(
            $userId,
            $thisWeekStart,
            $now->clone()->startOfWeek(Carbon::MONDAY)->subWeek()->toDateString(),
        );
        $weekStarts = $this->workoutSetRepository->trainedWeekStarts($userId);
        $monthlyComparison = $this->workoutSetRepository->monthlyBestComparison($userId, $monthStart);
        $latestPersonalBest = $this->workoutSetRepository->latestPersonalBest($userId);
        $hasRecords = $this->workoutSetRepository->hasAnyRecordedSets($userId);

        return [
            'hasRecords' => $hasRecords,
            'weeklyVolume' => $this->buildWeeklyVolume($volume),
            'streakWeeks' => $this->calculateStreakWeeks($weekStarts, $thisWeekStart),
            'monthlyRecordUpdates' => $this->countMonthlyRecordUpdates($monthlyComparison),
            'latestPersonalBest' => $latestPersonalBest !== null ? [
                'exerciseName' => $latestPersonalBest['exercise_name'],
                'isBodyweight' => $latestPersonalBest['is_bodyweight'],
                'weight' => $latestPersonalBest['weight'],
                'reps' => $latestPersonalBest['reps'],
                'date' => $latestPersonalBest['performed_on'],
            ] : null,
            // Issue #19: ダッシュボードに「次にやること」を出すための素材。
            // 表示ラベル・優先順位の判断はフロント側(Dashboard.vue)に置く
            // (他の集計と同じく、このサービスは事実だけを返す)。
            'activeWorkoutId' => Workout::query()
                ->where('user_id', $userId)
                ->whereNull('finished_at')
                ->value('id'),
            'routinesCount' => Routine::query()
                ->where('user_id', $userId)
                ->count(),
            // Issue #21: 現在の体重と直近の変化。
            'bodyWeight' => $this->buildBodyWeightTile($userId),
            // Issue #24①: 停滞している種目の一覧。
            'plateauExercises' => $this->buildPlateauExercises($userId),
            // Issue #24②: 部位バランス(push/pull)。
            'muscleBalance' => $this->buildMuscleBalance($userId),
        ];
    }

    /**
     * @return array<int, array{exerciseId: int, exerciseName: string, isBodyweight: bool, status: 'stagnant'|'declining', sessionsWithoutUpdate: int, baseline: array{weight: float, reps: int}, suggestions: array<int, array<string, mixed>>}>
     */
    private function buildPlateauExercises(int $userId): array
    {
        // 空配列 = 全種目(WorkoutSetRepository::sessionTopSetsForExercises() の規約)。
        $sessionsByExerciseId = $this->workoutSetRepository->sessionTopSetsForExercises([], $userId);

        if ($sessionsByExerciseId === []) {
            return [];
        }

        $exercises = Exercise::query()
            ->whereIn('id', array_keys($sessionsByExerciseId))
            ->get(['id', 'name', 'is_bodyweight', 'weight_increment', 'target_rep_min', 'target_rep_max'])
            ->map(fn (Exercise $exercise): array => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'is_bodyweight' => $exercise->is_bodyweight,
                'weight_increment' => (float) $exercise->weight_increment,
                'target_rep_min' => $exercise->target_rep_min,
                'target_rep_max' => $exercise->target_rep_max,
            ])
            ->all();

        $analyzed = $this->plateauAnalysisService->analyze($exercises, $sessionsByExerciseId);

        return array_map(fn (array $row): array => [
            'exerciseId' => $row['exercise_id'],
            'exerciseName' => $row['exercise_name'],
            'isBodyweight' => $row['is_bodyweight'],
            'status' => $row['status'],
            'sessionsWithoutUpdate' => $row['sessions_without_update'],
            'baseline' => $row['baseline'],
            'suggestions' => $row['suggestions'],
        ], $analyzed);
    }

    /**
     * @return array{sufficientData: bool, counts: array{push: int, pull: int, legs: int, core: int}, pushPullTotal: int, isImbalanced: bool, dominant: 'push'|'pull'|null, ratio: float|null}
     */
    private function buildMuscleBalance(int $userId): array
    {
        $sinceDate = now()->subWeeks(self::MUSCLE_BALANCE_PERIOD_WEEKS)->toDateString();
        $counts = $this->workoutSetRepository->movementTypeSetCounts($userId, $sinceDate);
        $result = $this->muscleBalanceService->analyze($counts);

        return [
            'sufficientData' => $result['sufficient_data'],
            'counts' => $result['counts'],
            'pushPullTotal' => $result['push_pull_total'],
            'isImbalanced' => $result['is_imbalanced'],
            'dominant' => $result['dominant'],
            'ratio' => $result['ratio'],
        ];
    }

    /**
     * @return array{current: float, measuredOn: string, changeFromPrevious: float|null}|null
     */
    private function buildBodyWeightTile(int $userId): ?array
    {
        $latestTwo = $this->bodyLogRepository->latestTwoForUser($userId);

        if ($latestTwo === []) {
            return null;
        }

        return [
            'current' => $latestTwo[0]['weight_kg'],
            'measuredOn' => $latestTwo[0]['measured_on'],
            'changeFromPrevious' => isset($latestTwo[1])
                ? round($latestTwo[0]['weight_kg'] - $latestTwo[1]['weight_kg'], 2)
                : null,
        ];
    }

    /**
     * @param  array{this_week: float, last_week: float}  $volume
     * @return array{thisWeek: float, lastWeek: float, changePercent: float|null}
     */
    private function buildWeeklyVolume(array $volume): array
    {
        // 変化率を null にするケースは2つある。フロント側は null のとき
        // 「先週比」の数値を出さない。
        //
        // 1. 先週の記録が0 … ゼロ除算になるため定義できない
        // 2. 今週の記録がまだ0 … 週の途中で「-100%」と出すのは誤解を招く。
        //    トレーニングしていないだけなのに「先週から100%減った」と読めてしまう。
        //    週が終わるまで比較は成立しないので、比率ではなく
        //    「今週まだ記録なし」という事実だけを伝える。
        $changePercent = ($volume['last_week'] > 0.0 && $volume['this_week'] > 0.0)
            ? round((($volume['this_week'] - $volume['last_week']) / $volume['last_week']) * 100, 1)
            : null;

        return [
            'thisWeek' => $volume['this_week'],
            'lastWeek' => $volume['last_week'],
            'changePercent' => $changePercent,
        ];
    }

    /**
     * 判断2の実装: 今週分の記録がまだ無くても、それだけを理由に連続を
     * 途切れさせない(今週はまだ終わっていないため)。今週の記録が無ければ
     * 先週を起点に数え始め、先週も無ければ0を返す。
     *
     * @param  array<int, string>  $weekStartsDesc  記録がある週の開始日(月曜)。降順。
     */
    private function calculateStreakWeeks(array $weekStartsDesc, string $thisWeekStart): int
    {
        $weekSet = array_flip($weekStartsDesc);

        $cursor = Carbon::parse($thisWeekStart);

        if (! isset($weekSet[$cursor->toDateString()])) {
            $cursor = $cursor->subWeek();
        }

        $streak = 0;

        while (isset($weekSet[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subWeek();
        }

        return $streak;
    }

    /**
     * 判断: 「更新」という言葉の意味上、今月より前に自己ベストが存在し、
     * かつ今月の自己ベストがそれを上回った種目だけを「更新」として数える。
     * 今月が初回の種目(今月より前の記録が無い)は「更新」に含めない
     * (比較対象が無いものを「更新した」とは言えないため)。
     *
     * @param  array<int, array{exercise_id: int, is_bodyweight: bool, best_before: float|null, best_this_month: float|null}>  $rows
     */
    private function countMonthlyRecordUpdates(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if ($row['best_before'] === null || $row['best_this_month'] === null) {
                continue;
            }

            if ($row['best_this_month'] > $row['best_before']) {
                $count++;
            }
        }

        return $count;
    }
}
