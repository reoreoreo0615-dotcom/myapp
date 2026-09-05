<?php

namespace Tests\Feature\Dashboard;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Issue #4(ダッシュボード)。
 *
 * 「今日」を固定して週・月の境界をテストごとに確定させるため、
 * travelTo() で基準日を 2026-09-03(木)に固定する。この日を含む週(月曜始まり)の
 * 月曜日は 2026-08-31。先週は 2026-08-24、先々週は 2026-08-17、3週前は 2026-08-10。
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-09-03 10:00:00'; // 木曜日。週(月曜始まり)の月曜日は 2026-08-31。

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse(self::TODAY));
    }

    /**
     * assertInertia() は JSON を経由するため、小数点以下が0の float は
     * int にコード落ちすることがある。既存テスト(ExerciseHistoryTest)と
     * 同じく、比較前に float へ揃えてから判定する。
     */
    private function sameNumber(float $expected): \Closure
    {
        return fn ($actual): bool => (float) $actual === $expected;
    }

    private function createWorkoutWithSets(User $user, Exercise $exercise, string $performedOn, array $sets): Workout
    {
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => $performedOn,
        ]);

        foreach ($sets as $i => $set) {
            WorkoutSet::factory()->create(array_merge([
                'workout_id' => $workout->id,
                'exercise_id' => $exercise->id,
                'set_number' => $i + 1,
                'is_warmup' => false,
            ], $set));
        }

        return $workout;
    }

    // ------------------------------------------------------------------
    // 認可 / 基本表示
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_zero_records_shows_empty_state(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('summary.hasRecords', false)
            ->where('summary.streakWeeks', 0)
            ->where('summary.monthlyRecordUpdates', 0)
            ->where('summary.latestPersonalBest', null)
        );
    }

    public function test_workout_with_no_sets_does_not_count_as_a_record(): void
    {
        $user = User::factory()->create();
        // ワークアウトを作成しただけでセットが無いケース。
        Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.hasRecords', false));
    }

    // ------------------------------------------------------------------
    // 今週の総ボリューム
    // ------------------------------------------------------------------

    public function test_weekly_volume_sums_weight_times_reps_excluding_warmups(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $workout = $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 60.0, 'reps' => 10], // 600
            ['weight' => 50.0, 'reps' => 8],  // 400
        ]);
        // ウォームアップは volume に含めない。
        WorkoutSet::factory()->warmup()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'set_number' => 3,
            'weight' => 999.0,
            'reps' => 99,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.hasRecords', true)
            ->where('summary.weeklyVolume.thisWeek', $this->sameNumber(1000.0))
        );
    }

    public function test_weekly_volume_compares_against_last_week_and_computes_change_percent(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 先週(2026-08-24週): 1000kg。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-26', [
            ['weight' => 50.0, 'reps' => 20],
        ]);
        // 今週(2026-08-31週): 1500kg。 +50%。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 75.0, 'reps' => 20],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.weeklyVolume.thisWeek', $this->sameNumber(1500.0))
            ->where('summary.weeklyVolume.lastWeek', $this->sameNumber(1000.0))
            ->where('summary.weeklyVolume.changePercent', $this->sameNumber(50.0))
        );
    }

    public function test_weekly_volume_change_percent_is_null_when_last_week_has_no_records(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 60.0, 'reps' => 10],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.weeklyVolume.lastWeek', $this->sameNumber(0.0))
            ->where('summary.weeklyVolume.changePercent', null)
        );
    }

    // ------------------------------------------------------------------
    // 連続トレーニング週数
    // ------------------------------------------------------------------

    public function test_streak_counts_consecutive_weeks_including_the_current_incomplete_week(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 今週(8/31週)・先週(8/24週)・先々週(8/17週)の3週連続。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-02', [['weight' => 60.0, 'reps' => 5]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-27', [['weight' => 60.0, 'reps' => 5]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-19', [['weight' => 60.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.streakWeeks', 3));
    }

    public function test_streak_is_not_broken_by_the_current_week_having_no_records_yet(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 今週(8/31週)はまだ記録が無い。先週・先々週は記録あり。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-27', [['weight' => 60.0, 'reps' => 5]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-19', [['weight' => 60.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.streakWeeks', 2));
    }

    public function test_streak_resets_to_zero_when_both_this_week_and_last_week_have_no_records(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 3週間前だけ記録がある(先週・今週は無い) -> 連続は途切れている。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-12', [['weight' => 60.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.streakWeeks', 0));
    }

    public function test_streak_stops_at_a_gap_week(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 今週は記録あり。先週(8/24週)は抜けている。先々週(8/17週)は記録あり。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-02', [['weight' => 60.0, 'reps' => 5]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-19', [['weight' => 60.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        // 先週が抜けているため、今週の1週間だけがカウントされる。
        $response->assertInertia(fn ($page) => $page->where('summary.streakWeeks', 1));
    }

    // ------------------------------------------------------------------
    // 今月の記録更新
    // ------------------------------------------------------------------

    public function test_monthly_record_updates_counts_exercises_whose_this_month_best_beats_prior_best(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 8月(今月より前): 推定1RM = 60 * (1 + 8/30) = 76.0
        $this->createWorkoutWithSets($user, $exercise, '2026-08-15', [
            ['weight' => 60.0, 'reps' => 8],
        ]);
        // 9月(今月): 推定1RM = 70 * (1 + 8/30) = 88.7 > 76.0 -> 更新。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 70.0, 'reps' => 8],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.monthlyRecordUpdates', 1));
    }

    public function test_monthly_record_updates_excludes_exercises_with_no_prior_history(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 今月が初回の種目(今月より前の記録が無い) -> 「更新」ではない。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 60.0, 'reps' => 8],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.monthlyRecordUpdates', 0));
    }

    public function test_monthly_record_updates_excludes_exercises_that_did_not_improve(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 8月: 100kg x 5(高い1RM)。9月: 60kg x 5(低い1RM) -> 更新ではない。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-15', [
            ['weight' => 100.0, 'reps' => 5],
        ]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 60.0, 'reps' => 5],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.monthlyRecordUpdates', 0));
    }

    public function test_monthly_record_updates_uses_reps_as_the_metric_for_bodyweight_exercises(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        // 自重種目は Issue #17 の決定通り、1RM ではなくレップ数で判定する。
        // 8月: 8回。9月: 10回(改善) -> 更新。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-15', [
            ['weight' => 0.0, 'reps' => 8],
        ]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 0.0, 'reps' => 10],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.monthlyRecordUpdates', 1));
    }

    public function test_monthly_record_updates_counts_multiple_exercises_independently(): void
    {
        $user = User::factory()->create();
        $improvedExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $flatExercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $improvedExercise, '2026-08-15', [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $improvedExercise, '2026-09-03', [['weight' => 70.0, 'reps' => 8]]);

        $this->createWorkoutWithSets($user, $flatExercise, '2026-08-15', [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $flatExercise, '2026-09-03', [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.monthlyRecordUpdates', 1));
    }

    // ------------------------------------------------------------------
    // 直近の自己ベスト
    // ------------------------------------------------------------------

    public function test_latest_personal_best_picks_the_most_recently_set_exercise_max(): void
    {
        $user = User::factory()->create();
        $bench = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'ベンチプレス', 'is_bodyweight' => false]);
        $deadlift = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'デッドリフト', 'is_bodyweight' => false]);

        // ベンチプレスの自己ベストは古い(8/1)。
        $this->createWorkoutWithSets($user, $bench, '2026-08-01', [['weight' => 80.0, 'reps' => 3]]);
        // デッドリフトの自己ベストは新しい(9/3、今日)。
        $this->createWorkoutWithSets($user, $deadlift, '2026-09-03', [['weight' => 120.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.latestPersonalBest.exerciseName', 'デッドリフト')
            ->where('summary.latestPersonalBest.weight', $this->sameNumber(120.0))
            ->where('summary.latestPersonalBest.reps', 5)
            ->where('summary.latestPersonalBest.date', '2026-09-03')
            ->where('summary.latestPersonalBest.isBodyweight', false)
        );
    }

    public function test_latest_personal_best_uses_reps_for_bodyweight_exercises(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => '懸垂', 'is_bodyweight' => true]);

        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [
            ['weight' => 0.0, 'reps' => 12],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.latestPersonalBest.isBodyweight', true)
            ->where('summary.latestPersonalBest.reps', 12)
        );
    }

    public function test_latest_personal_best_ignores_a_lower_weight_set_on_a_later_date(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 自己ベストは8/1の120kg。9/3にもっと軽い100kgをやっても自己ベストは更新されない。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-01', [['weight' => 120.0, 'reps' => 5]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [['weight' => 100.0, 'reps' => 5]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.latestPersonalBest.weight', $this->sameNumber(120.0))
            ->where('summary.latestPersonalBest.date', '2026-08-01')
        );
    }

    // ------------------------------------------------------------------
    // 他ユーザーのデータが混入しないこと
    // ------------------------------------------------------------------

    public function test_another_users_data_does_not_leak_into_any_tile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'name' => '共通種目', 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, '2026-09-03', [['weight' => 60.0, 'reps' => 8]]);

        // 他ユーザーの記録(自分より重い・自分より新しい)がダッシュボードに影響しないこと。
        $this->createWorkoutWithSets($otherUser, $exercise, '2026-09-03', [['weight' => 999.0, 'reps' => 20]]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('summary.weeklyVolume.thisWeek', $this->sameNumber(480.0)) // 60 * 8
            ->where('summary.latestPersonalBest.weight', $this->sameNumber(60.0))
        );
    }

    public function test_other_users_with_no_workouts_do_not_affect_the_empty_state(): void
    {
        $user = User::factory()->create();
        User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->where('summary.hasRecords', false));
    }

    // ------------------------------------------------------------------
    // N+1 対策(クエリ数がデータ量に比例して増えないこと)
    // ------------------------------------------------------------------

    public function test_query_count_does_not_grow_with_the_number_of_exercises_and_sets(): void
    {
        $smallUser = User::factory()->create();
        $smallExercise = Exercise::factory()->create(['user_id' => $smallUser->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets($smallUser, $smallExercise, '2026-09-03', [
            ['weight' => 60.0, 'reps' => 8],
        ]);

        $largeUser = User::factory()->create();
        foreach (range(1, 8) as $i) {
            $exercise = Exercise::factory()->create(['user_id' => $largeUser->id, 'is_bodyweight' => false]);
            foreach (range(1, 5) as $month) {
                $this->createWorkoutWithSets(
                    $largeUser,
                    $exercise,
                    now()->subMonths($month)->toDateString(),
                    [
                        ['weight' => 40.0 + $i, 'reps' => 8],
                        ['weight' => 45.0 + $i, 'reps' => 6],
                    ],
                );
            }
        }

        DB::enableQueryLog();
        $this->actingAs($smallUser)->get(route('dashboard'))->assertOk();
        $smallQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->actingAs($largeUser)->get(route('dashboard'))->assertOk();
        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $smallQueryCount,
            $largeQueryCount,
            "種目/セット数が少ない場合({$smallQueryCount}クエリ)と多い場合({$largeQueryCount}クエリ)でクエリ数が変わってはいけない(N+1)。"
        );
    }
}
