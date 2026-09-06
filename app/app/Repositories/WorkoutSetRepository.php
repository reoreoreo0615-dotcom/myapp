<?php

namespace App\Repositories;

use App\Models\WorkoutSet;
use App\Services\DashboardSummaryService;
use App\Services\ExerciseHistoryService;
use App\Services\MuscleBalanceService;
use App\Services\ProgressionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * workout_sets への DB アクセスを担当するクエリクラス。
 *
 * {@see ProgressionService} は Eloquent に依存しない純粋なロジックのため、
 * 「前回のトップセット」に必要な生データの取得はこちらの責務とする。
 *
 * Issue #23③: workouts / workout_sets は SoftDeletes 対応した。Eloquent の
 * クエリ(`WorkoutSet::query()` 等)は論理削除済みの行を自動で除外するが、
 * このクラスの集計・履歴系メソッドはパフォーマンスのため `DB::table()` を
 * 使っており、Eloquent と違って論理削除を自動で除外しない。
 * そのため `workout_sets` と `workouts` を JOIN する生クエリは必ず
 * {@see activeWorkoutSetsQuery()} 経由で組み立て、両テーブルの
 * deleted_at IS NULL を条件に含める。
 */
class WorkoutSetRepository
{
    /**
     * `workout_sets` と `workouts` を JOIN した生クエリの共通の起点。
     *
     * 論理削除された workout_sets 自身、および論理削除された workouts に
     * ぶら下がる workout_sets(cascadeOnDelete は物理FKのため、workouts を
     * 論理削除しても配下の workout_sets 行自体は残る)の両方を除外する。
     * これを経由せずに `DB::table('workout_sets')` を直接 join することは
     * しないこと。
     */
    private function activeWorkoutSetsQuery(): Builder
    {
        return DB::table('workout_sets')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->whereNull('workout_sets.deleted_at')
            ->whereNull('workouts.deleted_at');
    }

    /**
     * 指定ユーザーが最後にその種目を行ったワークアウトの、
     * ウォームアップを除いたセット一覧を返す。
     *
     * 手順(2クエリ):
     *  1. is_warmup = false のセットを持つ、そのユーザー・その種目の
     *     ワークアウトのうち、performed_on が最も新しいものの workout_id を特定する。
     *  2. その workout_id × exercise_id の is_warmup = false セットを取得する。
     *
     * `workout_sets(exercise_id, workout_id)` の複合インデックスが
     * 両クエリの絞り込みに効くようにしている。
     *
     * @param  string|null  $onOrBeforeDate  指定すると、この日付(performed_on)以前の
     *                                       ワークアウトだけを対象にする(Issue #23②:
     *                                       過去日のワークアウトの目標を、その日付より
     *                                       後の記録から算出しないため)。null なら無制限。
     * @param  int|null  $excludeWorkoutId  指定すると、この workout_id 自身は候補から除く。
     * @return array<int, array{weight: float, reps: int, is_warmup: bool}>
     */
    public function lastWorkingSetsFor(int $userId, int $exerciseId, ?string $onOrBeforeDate = null, ?int $excludeWorkoutId = null): array
    {
        $lastWorkoutId = $this->activeWorkoutSetsQuery()
            ->where('workout_sets.exercise_id', $exerciseId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->when(
                $onOrBeforeDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '<=', $onOrBeforeDate),
            )
            ->when(
                $excludeWorkoutId !== null,
                fn ($query) => $query->where('workouts.id', '!=', $excludeWorkoutId),
            )
            ->orderByDesc('workouts.performed_on')
            ->orderByDesc('workouts.id')
            ->limit(1)
            ->value('workout_sets.workout_id');

        if ($lastWorkoutId === null) {
            return [];
        }

        return WorkoutSet::query()
            ->where('exercise_id', $exerciseId)
            ->where('workout_id', $lastWorkoutId)
            ->where('is_warmup', false)
            ->get(['weight', 'reps', 'is_warmup'])
            ->map(fn (WorkoutSet $set): array => [
                'weight' => (float) $set->weight,
                'reps' => (int) $set->reps,
                'is_warmup' => (bool) $set->is_warmup,
            ])
            ->all();
    }

    /**
     * {@see lastWorkingSetsFor()} のバッチ版。
     *
     * 記録画面は5〜8種目を同時に表示するため、種目ごとに lastWorkingSetsFor() を
     * ループで呼ぶと N+1(10〜16クエリ)になる。このメソッドは種目数によらず
     * 常に2クエリで全種目分をまとめて取得する。
     *
     * 手順(2クエリ):
     *  1. 対象の種目群それぞれについて、is_warmup = false のセットを持つ
     *     ワークアウトのうち performed_on が最も新しいものの workout_id を
     *     特定する(DISTINCT (exercise_id, workout_id) の組だけを取得するため、
     *     行数はセット総数ではなく「種目×ワークアウト」の組数に収まる)。
     *  2. 特定した workout_id 群のセットをまとめて取得し、PHP側で
     *     「その種目にとって最後のワークアウトと一致する行」だけに絞り込む
     *     (同じ workout_id が複数種目にまたがる場合の取り違えを防ぐため)。
     *
     * `workout_sets(exercise_id, workout_id)` の複合インデックスが
     * 両クエリの絞り込みに効くようにしている。
     *
     * @param  array<int, int>  $exerciseIds
     * @param  string|null  $onOrBeforeDate  {@see lastWorkingSetsFor()} と同じ意味
     *                                       (Issue #23②: 過去日のワークアウトの目標を、
     *                                       その日付より後の記録から算出しないため)。
     * @param  int|null  $excludeWorkoutId  {@see lastWorkingSetsFor()} と同じ意味。
     * @return array<int, array<int, array{weight: float, reps: int, is_warmup: bool}>> exercise_id をキーにしたセット配列
     */
    public function lastWorkingSetsForMany(int $userId, array $exerciseIds, ?string $onOrBeforeDate = null, ?int $excludeWorkoutId = null): array
    {
        $exerciseIds = array_values(array_unique(array_map('intval', $exerciseIds)));

        if ($exerciseIds === []) {
            return [];
        }

        // クエリ1: 種目ごとの「最後のワークアウト」候補を DISTINCT (exercise_id, workout_id) で取得する。
        $candidates = $this->activeWorkoutSetsQuery()
            ->whereIn('workout_sets.exercise_id', $exerciseIds)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->when(
                $onOrBeforeDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '<=', $onOrBeforeDate),
            )
            ->when(
                $excludeWorkoutId !== null,
                fn ($query) => $query->where('workouts.id', '!=', $excludeWorkoutId),
            )
            ->select([
                'workout_sets.exercise_id',
                'workout_sets.workout_id',
                'workouts.performed_on',
                'workouts.id as workout_pk',
            ])
            ->distinct()
            ->get();

        $lastWorkoutByExercise = [];

        foreach ($candidates as $row) {
            $exerciseId = (int) $row->exercise_id;
            $current = $lastWorkoutByExercise[$exerciseId] ?? null;

            $isNewer = $current === null
                || $row->performed_on > $current['performed_on']
                || ($row->performed_on === $current['performed_on'] && (int) $row->workout_pk > $current['workout_pk']);

            if ($isNewer) {
                $lastWorkoutByExercise[$exerciseId] = [
                    'workout_id' => (int) $row->workout_id,
                    'performed_on' => $row->performed_on,
                    'workout_pk' => (int) $row->workout_pk,
                ];
            }
        }

        if ($lastWorkoutByExercise === []) {
            return [];
        }

        $workoutIds = array_values(array_unique(array_map(
            fn (array $entry): int => $entry['workout_id'],
            $lastWorkoutByExercise,
        )));

        // クエリ2: 特定した workout_id 群のセットをまとめて取得する。
        $sets = WorkoutSet::query()
            ->whereIn('workout_id', $workoutIds)
            ->whereIn('exercise_id', array_keys($lastWorkoutByExercise))
            ->where('is_warmup', false)
            ->get(['exercise_id', 'workout_id', 'weight', 'reps', 'is_warmup']);

        $result = [];

        foreach ($sets as $set) {
            $exerciseId = (int) $set->exercise_id;
            $expectedWorkoutId = $lastWorkoutByExercise[$exerciseId]['workout_id'] ?? null;

            // 同じ workout_id が複数種目にまたがるケースを取り違えないよう、
            // その種目にとって「最後のワークアウト」と一致する行だけを採用する。
            if ((int) $set->workout_id !== $expectedWorkoutId) {
                continue;
            }

            $result[$exerciseId][] = [
                'weight' => (float) $set->weight,
                'reps' => (int) $set->reps,
                'is_warmup' => (bool) $set->is_warmup,
            ];
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // 種目別履歴(Issue #11 / #17)
    // ------------------------------------------------------------------

    /**
     * 種目別履歴の推移グラフ用データ。ワークアウトごとの「トップセット」
     * (最大重量、同重量なら最大レップ。{@see ProgressionService::pickTopSet()}
     * と同じ定義)を、SQL の window 関数で1件ずつ選び出す。全セットを
     * PHP に持ってきてから絞り込むことはしない。
     *
     * is_warmup = false のセットのみを対象にする(グラフの注意点)。
     *
     * @param  string|null  $sinceDate  'Y-m-d'。null なら期間の下限なし(全期間)。
     * @return array<int, array{workout_id: int, performed_on: string, weight: float, reps: int}> performed_on 昇順
     */
    public function historyTopSetsPerWorkout(int $userId, int $exerciseId, ?string $sinceDate): array
    {
        $ranked = $this->activeWorkoutSetsQuery()
            ->where('workout_sets.exercise_id', $exerciseId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->when(
                $sinceDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '>=', $sinceDate),
            )
            ->select([
                'workouts.id as workout_id',
                'workouts.performed_on',
                'workout_sets.weight',
                'workout_sets.reps',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY workouts.id ORDER BY workout_sets.weight DESC, workout_sets.reps DESC) AS rn'
            );

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->orderBy('performed_on')
            ->orderBy('workout_id')
            ->get(['workout_id', 'performed_on', 'weight', 'reps'])
            ->map(fn ($row): array => [
                'workout_id' => (int) $row->workout_id,
                'performed_on' => (string) $row->performed_on,
                'weight' => (float) $row->weight,
                'reps' => (int) $row->reps,
            ])
            ->all();
    }

    /**
     * 種目別履歴の全セット一覧(ウォームアップ含む、日付降順)。
     *
     * 一覧表示は「その日その種目で何をやったか」を漏れなく見せる目的のため、
     * 集計(グラフ・自己ベスト)とは異なりウォームアップも含める。
     *
     * @param  string|null  $sinceDate  'Y-m-d'。null なら期間の下限なし。
     * @return array<int, array{id: int, date: string, weight: float, reps: int, rpe: float|null, is_warmup: bool}>
     */
    public function historyAllSets(int $userId, int $exerciseId, ?string $sinceDate): array
    {
        return $this->activeWorkoutSetsQuery()
            ->where('workout_sets.exercise_id', $exerciseId)
            ->where('workouts.user_id', $userId)
            ->when(
                $sinceDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '>=', $sinceDate),
            )
            ->orderByDesc('workouts.performed_on')
            ->orderByDesc('workouts.id')
            ->orderByDesc('workout_sets.set_number')
            ->get([
                'workout_sets.id',
                'workouts.performed_on',
                'workout_sets.weight',
                'workout_sets.reps',
                'workout_sets.rpe',
                'workout_sets.is_warmup',
            ])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'date' => (string) $row->performed_on,
                'weight' => (float) $row->weight,
                'reps' => (int) $row->reps,
                'rpe' => $row->rpe !== null ? (float) $row->rpe : null,
                'is_warmup' => (bool) $row->is_warmup,
            ])
            ->all();
    }

    /**
     * 種目の自己ベスト(最大重量・最大推定1RM・最大レップ数)を SQL 側の
     * MAX() 集計で求める。全セットを PHP に取得してから比較することはしない。
     *
     * 期間フィルタの対象外(常に全期間)。ウォームアップは除外する。
     *
     * 推定1RM の式は {@see ProgressionService::estimateOneRepMax()}
     * と同じもの(Epley 式、weight=0 は 0、reps=1 は weight そのまま)を
     * SQL 式として複製している。MAX() 集計のために SQL 側で計算する必要があり、
     * かつ ProgressionService 自体は変更しない方針(Issue #17)のための複製。
     * 式を変更する場合は両方を合わせて直すこと
     * (tests/Feature/History/ExerciseHistoryTest.php に整合性の確認テストがある)。
     *
     * @return array{max_weight: float|null, max_reps: int|null, max_estimated_1rm: float|null}
     */
    public function personalBest(int $userId, int $exerciseId): array
    {
        $row = $this->activeWorkoutSetsQuery()
            ->where('workout_sets.exercise_id', $exerciseId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->selectRaw('MAX(workout_sets.weight) as max_weight')
            ->selectRaw('MAX(workout_sets.reps) as max_reps')
            ->selectRaw(
                'MAX(CASE '
                .'WHEN workout_sets.weight = 0 THEN 0 '
                .'WHEN workout_sets.reps <= 1 THEN ROUND(workout_sets.weight, 1) '
                .'ELSE ROUND(workout_sets.weight * (1 + workout_sets.reps / 30), 1) '
                .'END) as max_estimated_1rm'
            )
            ->first();

        return [
            'max_weight' => $row?->max_weight !== null ? (float) $row->max_weight : null,
            'max_reps' => $row?->max_reps !== null ? (int) $row->max_reps : null,
            'max_estimated_1rm' => $row?->max_estimated_1rm !== null ? (float) $row->max_estimated_1rm : null,
        ];
    }

    // ------------------------------------------------------------------
    // 停滞検知・部位バランス(Issue #24)
    // ------------------------------------------------------------------

    /**
     * 指定した種目群(または全種目)について、セッション(ワークアウト)ごとの
     * トップセット(ウォームアップ除く)を performed_on 昇順で返す。
     * {@see PlateauService} の停滞判定に使う全期間のセッション履歴。
     *
     * 種目数に関わらず1クエリで完結させる(ダッシュボードで全種目分をまとめて
     * 洗い出す用途、記録画面で表示中の種目分をまとめて取得する用途の両方で使う)。
     *
     * @param  array<int, int>  $exerciseIds  空配列なら、そのユーザーが記録したことのある全種目が対象
     * @param  string|null  $onOrBeforeDate  指定すると、この日付(performed_on)以前のワークアウトだけを対象にする
     *                                       (Issue #23②と同じ理由: 過去日のワークアウトを開いたとき、
     *                                       その日付より後の記録から停滞を判定しないため)
     * @param  int|null  $excludeWorkoutId  指定すると、この workout_id 自身は候補から除く
     *                                      (記録中のワークアウト自身のセットで汚染しないため)
     * @return array<int, array<int, array{performed_on: string, weight: float, reps: int}>> exercise_id をキーにした、performed_on 昇順のセッション配列
     */
    public function sessionTopSetsForExercises(
        array $exerciseIds,
        int $userId,
        ?string $onOrBeforeDate = null,
        ?int $excludeWorkoutId = null,
    ): array {
        $ranked = $this->activeWorkoutSetsQuery()
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->when(
                $exerciseIds !== [],
                fn ($query) => $query->whereIn('workout_sets.exercise_id', $exerciseIds),
            )
            ->when(
                $onOrBeforeDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '<=', $onOrBeforeDate),
            )
            ->when(
                $excludeWorkoutId !== null,
                fn ($query) => $query->where('workouts.id', '!=', $excludeWorkoutId),
            )
            ->select([
                'workout_sets.exercise_id',
                'workouts.id as workout_id',
                'workouts.performed_on',
                'workout_sets.weight',
                'workout_sets.reps',
            ])
            ->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY workout_sets.exercise_id, workouts.id ORDER BY workout_sets.weight DESC, workout_sets.reps DESC) AS rn'
            );

        $rows = DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->orderBy('exercise_id')
            ->orderBy('performed_on')
            ->orderBy('workout_id')
            ->get(['exercise_id', 'workout_id', 'performed_on', 'weight', 'reps']);

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->exercise_id][] = [
                'performed_on' => (string) $row->performed_on,
                'weight' => (float) $row->weight,
                'reps' => (int) $row->reps,
            ];
        }

        return $result;
    }

    /**
     * 部位バランス(Issue #24②)向けに、期間内の movement_type 別セット数を集計する。
     * 指標にセット数を採用した理由は {@see MuscleBalanceService} のコメント参照。
     *
     * is_warmup = false のみ、workouts.performed_on >= $sinceDate。
     *
     * @return array<string, int> movement_type の値(push/pull/legs/core)をキーにしたセット数。記録が無いキーは含まれない。
     */
    public function movementTypeSetCounts(int $userId, string $sinceDate): array
    {
        return $this->activeWorkoutSetsQuery()
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.performed_on', '>=', $sinceDate)
            ->groupBy('exercises.movement_type')
            ->selectRaw('exercises.movement_type, COUNT(*) as set_count')
            ->pluck('set_count', 'movement_type')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    // ------------------------------------------------------------------
    // ダッシュボード(Issue #4)
    // ------------------------------------------------------------------

    /**
     * ユーザーが workout_sets を1件でも記録しているか(ウォームアップ除く)。
     *
     * ダッシュボードの「記録0件」判定に使う。ワークアウトを作成しただけで
     * セットが無い場合は「記録がある」に含めない。
     */
    public function hasAnyRecordedSets(int $userId): bool
    {
        return $this->activeWorkoutSetsQuery()
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->exists();
    }

    /**
     * 今週・先週の総ボリューム(SUM(weight * reps))を1クエリで集計する。
     *
     * ウォームアップは除外する。週の境界(月曜始まりかどうか)は
     * 呼び出し元({@see DashboardSummaryService})が算出した
     * $thisWeekStart / $lastWeekStart をそのまま条件として使うだけで、
     * このメソッド自体は週の定義を知らない。
     *
     * @return array{this_week: float, last_week: float}
     */
    public function weeklyVolume(int $userId, string $thisWeekStart, string $lastWeekStart): array
    {
        $row = $this->activeWorkoutSetsQuery()
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.performed_on', '>=', $lastWeekStart)
            ->selectRaw(
                'SUM(CASE WHEN workouts.performed_on >= ? THEN workout_sets.weight * workout_sets.reps ELSE 0 END) as this_week',
                [$thisWeekStart],
            )
            ->selectRaw(
                'SUM(CASE WHEN workouts.performed_on < ? THEN workout_sets.weight * workout_sets.reps ELSE 0 END) as last_week',
                [$thisWeekStart],
            )
            ->first();

        return [
            'this_week' => $row?->this_week !== null ? (float) $row->this_week : 0.0,
            'last_week' => $row?->last_week !== null ? (float) $row->last_week : 0.0,
        ];
    }

    /**
     * ユーザーが記録した週(月曜始まり)の開始日一覧を降順で返す。
     *
     * 「記録がある週」の定義は、その週にウォームアップを除く workout_sets が
     * 1件以上存在すること。
     *
     * `DATE_SUB(performed_on, INTERVAL WEEKDAY(performed_on) DAY)` は
     * その日を含む週の月曜日を返す(MySQL の WEEKDAY() は月曜=0、日曜=6)。
     *
     * @return array<int, string> 'Y-m-d' 形式、降順
     */
    public function trainedWeekStarts(int $userId): array
    {
        return $this->activeWorkoutSetsQuery()
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->distinct()
            ->orderByDesc('week_start')
            ->selectRaw('DATE_SUB(workouts.performed_on, INTERVAL WEEKDAY(workouts.performed_on) DAY) as week_start')
            ->pluck('week_start')
            ->map(fn ($date): string => (string) $date)
            ->all();
    }

    /**
     * 「今月の記録更新」判定用に、種目ごとの「今月より前の自己ベスト」と
     * 「今月の自己ベスト」を1クエリで集計する。
     *
     * 指標は種目種別で切り替える(Issue #17 の決定と同じ規約。
     * {@see ExerciseHistoryService}):
     *   - 通常種目 → 推定1RM(Epley式。式は {@see personalBest()} と同じものを複製)
     *   - 自重種目 → レップ数
     *
     * 返る行数はユーザーが記録したことのある種目数分(通常は数十件以下)に
     * 収まる。この小さな結果セットを PHP 側でループして「更新」判定するだけで、
     * workout_sets の全件を PHP に持ってくるわけではない。
     *
     * @return array<int, array{exercise_id: int, is_bodyweight: bool, best_before: float|null, best_this_month: float|null}>
     */
    public function monthlyBestComparison(int $userId, string $monthStart): array
    {
        $metricExpr = 'CASE '
            .'WHEN exercises.is_bodyweight THEN workout_sets.reps '
            .'WHEN workout_sets.weight = 0 THEN 0 '
            .'WHEN workout_sets.reps <= 1 THEN ROUND(workout_sets.weight, 1) '
            .'ELSE ROUND(workout_sets.weight * (1 + workout_sets.reps / 30), 1) '
            .'END';

        return $this->activeWorkoutSetsQuery()
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->groupBy('workout_sets.exercise_id', 'exercises.is_bodyweight')
            ->selectRaw('workout_sets.exercise_id')
            ->selectRaw('exercises.is_bodyweight')
            ->selectRaw("MAX(CASE WHEN workouts.performed_on < ? THEN ({$metricExpr}) ELSE NULL END) as best_before", [$monthStart])
            ->selectRaw("MAX(CASE WHEN workouts.performed_on >= ? THEN ({$metricExpr}) ELSE NULL END) as best_this_month", [$monthStart])
            ->get()
            ->map(fn ($row): array => [
                'exercise_id' => (int) $row->exercise_id,
                'is_bodyweight' => (bool) $row->is_bodyweight,
                'best_before' => $row->best_before !== null ? (float) $row->best_before : null,
                'best_this_month' => $row->best_this_month !== null ? (float) $row->best_this_month : null,
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // CSVエクスポート(Issue #26①)
    // ------------------------------------------------------------------

    /**
     * ワークアウト記録の CSV エクスポート用に、セット単位の行を
     * performed_on 昇順(同日はワークアウトID→種目ID→セット番号の順)で返す。
     *
     * 論理削除済みの workout_sets / workouts は {@see activeWorkoutSetsQuery()}
     * により除外される。件数が多くてもメモリを使い切らないよう、配列にまとめず
     * `cursor()`(LazyCollection、PDO 側で1行ずつ読み出す)を返す。呼び出し側も
     * 全件を配列化せずそのままイテレートすること。
     *
     * @return LazyCollection<int, object{performed_on: string, exercise_name: string, set_number: int, weight: string, reps: int, rpe: string|null, is_warmup: int, memo: string|null}>
     */
    public function exportRows(int $userId, ?string $fromDate, ?string $toDate): LazyCollection
    {
        return $this->activeWorkoutSetsQuery()
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $userId)
            ->when(
                $fromDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '>=', $fromDate),
            )
            ->when(
                $toDate !== null,
                fn ($query) => $query->where('workouts.performed_on', '<=', $toDate),
            )
            ->orderBy('workouts.performed_on')
            ->orderBy('workouts.id')
            ->orderBy('workout_sets.exercise_id')
            ->orderBy('workout_sets.set_number')
            ->select([
                'workouts.performed_on',
                'exercises.name as exercise_name',
                'workout_sets.set_number',
                'workout_sets.weight',
                'workout_sets.reps',
                'workout_sets.rpe',
                'workout_sets.is_warmup',
                'workout_sets.memo',
            ])
            ->cursor();
    }

    /**
     * 全種目を通じて、現在の自己ベスト(種目ごとの最大値)のうち
     * 最も新しく達成されたものを1件返す(ダッシュボードの「直近の自己ベスト」)。
     *
     * 指標は種目種別で切り替える({@see monthlyBestComparison()} と同じ規約:
     * 通常種目は重量、自重種目はレップ数)。種目ごとに「現在の自己ベストの
     * セット」を window 関数で1件選び(同値なら最新日を優先)、その中から
     * 日付が最も新しいものを選ぶ。全セットを PHP に持ってくることはしない。
     *
     * @return array{exercise_id: int, exercise_name: string, is_bodyweight: bool, weight: float, reps: int, performed_on: string}|null
     */
    public function latestPersonalBest(int $userId): ?array
    {
        $metricExpr = 'CASE WHEN exercises.is_bodyweight THEN workout_sets.reps ELSE workout_sets.weight END';

        $perExerciseBest = $this->activeWorkoutSetsQuery()
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $userId)
            ->where('workout_sets.is_warmup', false)
            ->select([
                'workout_sets.exercise_id',
                'exercises.name as exercise_name',
                'exercises.is_bodyweight',
                'workouts.performed_on',
                'workouts.id as workout_pk',
                'workout_sets.id as set_pk',
                'workout_sets.weight',
                'workout_sets.reps',
            ])
            ->selectRaw(
                "ROW_NUMBER() OVER (PARTITION BY workout_sets.exercise_id ORDER BY ({$metricExpr}) DESC, workouts.performed_on DESC, workouts.id DESC, workout_sets.id DESC) as rn"
            );

        $row = DB::query()
            ->fromSub($perExerciseBest, 'per_exercise_best')
            ->where('rn', 1)
            ->orderByDesc('performed_on')
            ->orderByDesc('workout_pk')
            ->orderByDesc('set_pk')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'exercise_id' => (int) $row->exercise_id,
            'exercise_name' => (string) $row->exercise_name,
            'is_bodyweight' => (bool) $row->is_bodyweight,
            'weight' => (float) $row->weight,
            'reps' => (int) $row->reps,
            'performed_on' => (string) $row->performed_on,
        ];
    }
}
