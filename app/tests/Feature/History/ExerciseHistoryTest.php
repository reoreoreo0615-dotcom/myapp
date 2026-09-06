<?php

namespace Tests\Feature\History;

use App\Models\BodyLog;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\ProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #11(種目別履歴・推定1RM推移グラフ)+ Issue #17(自重種目の1RM問題)。
 *
 * 集計(グラフ・自己ベスト)は WorkoutSetRepository が SQL 側で行う前提のため、
 * ここでは HTTP 経由でその結果が正しく組み立てられているかを検証する。
 */
class ExerciseHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * assertInertia() は JSON を経由するため、小数点以下が0の float は
     * int にコード落ちすることがある(例: 60.0 → 60)。既存テスト
     * (WorkoutRecordingTest)と同じく、比較前に float へ揃えてから判定する。
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
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_index_without_exercise_selected_lists_exercises_and_no_history(): void
    {
        $user = User::factory()->create();
        Exercise::factory()->create(['user_id' => null, 'name' => 'ベンチプレス']);

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->where('history', null)
            ->has('exercises')
        );
    }

    public function test_selecting_another_users_custom_exercise_is_not_found(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $othersExercise = Exercise::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($user)
            ->get(route('history.index', ['exercise_id' => $othersExercise->id]))
            ->assertNotFound();
    }

    public function test_user_can_select_their_own_custom_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [
            ['weight' => 60.0, 'reps' => 8],
        ]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->where('history.exercise.id', $exercise->id)
        );
    }

    // ------------------------------------------------------------------
    // 0件・1件でも壊れないこと
    // ------------------------------------------------------------------

    public function test_exercise_with_zero_records_returns_empty_chart_and_null_personal_best(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->where('history.chart.points', [])
            ->where('history.sets', [])
            ->where('history.personalBest', null)
        );
    }

    public function test_exercise_with_a_single_record_renders_a_single_point(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [
            ['weight' => 60.0, 'reps' => 8],
        ]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->component('History/Index')
            ->has('history.chart.points', 1)
            ->where('history.chart.points.0.value', $this->sameNumber((new ProgressionService)->estimateOneRepMax(60.0, 8)))
        );
    }

    // ------------------------------------------------------------------
    // 通常種目: 推定1RM推移(のこぎり状を含む)
    // ------------------------------------------------------------------

    public function test_normal_exercise_uses_estimated_one_rep_max_as_the_metric(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // W1: 60x10、W2: 重量アップで62.5x8(ダブルプログレッションで一時的に1RMが下がる)。
        $this->createWorkoutWithSets($user, $exercise, '2026-08-01', [
            ['weight' => 60.0, 'reps' => 10],
        ]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-08', [
            ['weight' => 62.5, 'reps' => 8],
        ]);

        $service = new ProgressionService;
        $expectedWeek1 = $service->estimateOneRepMax(60.0, 10);
        $expectedWeek2 = $service->estimateOneRepMax(62.5, 8);

        // Epley 式の性質上、この2値でのこぎり状(一時的な下降)になることを前提にしている。
        $this->assertLessThan($expectedWeek1, $expectedWeek2);

        $response = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => 'all',
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.metric', '1rm')
            ->where('history.chart.metric_label', '推定1RM (kg)')
            ->where('history.chart.points.0.value', $this->sameNumber($expectedWeek1))
            ->where('history.chart.points.1.value', $this->sameNumber($expectedWeek2))
        );
    }

    public function test_top_set_per_workout_is_selected_by_highest_weight_then_highest_reps(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 同じワークアウト内でウォームアップ→本セット→ドロップセットのように重量が変動する場合、
        // 最大重量のセットがトップセットとして使われるべき(最後のセットではない)。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [
            ['weight' => 40.0, 'reps' => 12],
            ['weight' => 70.0, 'reps' => 5],
            ['weight' => 50.0, 'reps' => 8],
        ]);

        $expected = (new ProgressionService)->estimateOneRepMax(70.0, 5);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->has('history.chart.points', 1)
            ->where('history.chart.points.0.value', $this->sameNumber($expected))
            ->where('history.chart.points.0.weight', $this->sameNumber(70.0))
            ->where('history.chart.points.0.reps', 5)
        );
    }

    // ------------------------------------------------------------------
    // Issue #17: 自重種目はレップ数を主軸にする
    // ------------------------------------------------------------------

    public function test_bodyweight_exercise_uses_reps_as_the_metric_instead_of_one_rep_max(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        $this->createWorkoutWithSets($user, $exercise, '2026-08-01', [
            ['weight' => 0.0, 'reps' => 8],
        ]);
        $this->createWorkoutWithSets($user, $exercise, '2026-08-08', [
            ['weight' => 0.0, 'reps' => 10],
        ]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.metric', 'reps')
            ->where('history.chart.metric_label', 'レップ数 (回)')
            ->where('history.chart.points.0.value', $this->sameNumber(8.0))
            ->where('history.chart.points.1.value', $this->sameNumber(10.0))
            ->where('history.personalBest.metric', 'reps')
            ->where('history.personalBest.max_reps', 10)
        );
    }

    public function test_weighted_bodyweight_exercise_keeps_reps_as_the_metric_but_carries_the_added_weight(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        // 加重懸垂: 5kg 吊るして8回。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [
            ['weight' => 5.0, 'reps' => 8],
        ]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.metric', 'reps')
            ->where('history.chart.points.0.value', $this->sameNumber(8.0))
            ->where('history.chart.points.0.weight', $this->sameNumber(5.0))
        );
    }

    // ------------------------------------------------------------------
    // ウォームアップ除外
    // ------------------------------------------------------------------

    public function test_warmup_sets_are_excluded_from_chart_and_personal_best_but_listed_in_all_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->warmup()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 999.0,
            'reps' => 20,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'set_number' => 2,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->has('history.chart.points', 1)
            ->where('history.chart.points.0.weight', $this->sameNumber(60.0))
            ->where('history.personalBest.max_weight', $this->sameNumber(60.0))
            ->has('history.sets', 2)
        );
    }

    // ------------------------------------------------------------------
    // 自己ベスト(SQL集計)
    // ------------------------------------------------------------------

    public function test_personal_best_matches_progression_service_formula(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        // 最大重量のセットと、最大推定1RMのセットが別々になるケース
        // (低重量高レップの方が推定1RMが高くなることがある)。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [
            ['weight' => 100.0, 'reps' => 1],  // 1RM = 100.0、重量は最大
            ['weight' => 60.0, 'reps' => 15],  // 1RM = 60 * (1 + 15/30) = 90.0
        ]);

        $service = new ProgressionService;
        $expectedMaxOneRepMax = max(
            $service->estimateOneRepMax(100.0, 1),
            $service->estimateOneRepMax(60.0, 15),
        );

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.personalBest.max_weight', $this->sameNumber(100.0))
            ->where('history.personalBest.max_estimated_1rm', $this->sameNumber($expectedMaxOneRepMax))
        );
    }

    public function test_personal_best_is_not_affected_by_the_period_filter(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets(
            $user,
            $exercise,
            now()->subMonths(10)->toDateString(),
            [['weight' => 120.0, 'reps' => 1]],
        );
        $this->createWorkoutWithSets(
            $user,
            $exercise,
            now()->toDateString(),
            [['weight' => 60.0, 'reps' => 5]],
        );

        $response = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => '3m',
        ]));

        $response->assertInertia(fn ($page) => $page
            // グラフは直近3ヶ月分の1点のみ。
            ->has('history.chart.points', 1)
            // だが自己ベストは期間フィルタの対象外で、10ヶ月前の120kgを拾う。
            ->where('history.personalBest.max_weight', $this->sameNumber(120.0))
        );
    }

    // ------------------------------------------------------------------
    // 停滞判定(Issue #24①)
    // ------------------------------------------------------------------

    public function test_plateau_is_null_when_not_enough_sessions_exist(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(1)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page->where('history.plateau', null));
    }

    public function test_plateau_is_detected_for_a_stagnant_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'is_bodyweight' => false,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ]);

        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(10)->toDateString(), [['weight' => 50.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(8)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(6)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(4)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(2)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.plateau.status', 'stagnant')
            ->where('history.plateau.sessions_without_update', 3)
        );
    }

    public function test_plateau_detection_ignores_the_period_filter(): void
    {
        // 期間フィルタを絞っても、停滞判定は全期間のセッション履歴を見る
        // (baseline が視界から消えて誤判定しないようにするため)。
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, now()->subMonths(10)->toDateString(), [['weight' => 50.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subMonths(9)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(6)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(4)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(2)->toDateString(), [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => '3m',
        ]));

        $response->assertInertia(fn ($page) => $page
            // グラフは3ヶ月分(3点)だけだが、停滞判定は10ヶ月前のセッションも見て成立する。
            ->has('history.chart.points', 3)
            ->where('history.plateau.status', 'stagnant')
        );
    }

    public function test_plateau_for_bodyweight_exercise_uses_reps(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'is_bodyweight' => true,
            'weight_increment' => 1.25,
            'target_rep_min' => 6,
            'target_rep_max' => 15,
        ]);

        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(10)->toDateString(), [['weight' => 0.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(8)->toDateString(), [['weight' => 0.0, 'reps' => 12]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(6)->toDateString(), [['weight' => 0.0, 'reps' => 12]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(4)->toDateString(), [['weight' => 0.0, 'reps' => 12]]);
        $this->createWorkoutWithSets($user, $exercise, now()->subWeeks(2)->toDateString(), [['weight' => 0.0, 'reps' => 12]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.plateau.status', 'stagnant')
            ->where('history.plateau.baseline.reps', 12)
        );
    }

    // ------------------------------------------------------------------
    // 期間フィルタ
    // ------------------------------------------------------------------

    public function test_period_filter_excludes_records_outside_the_window(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets(
            $user,
            $exercise,
            now()->subMonths(2)->toDateString(),
            [['weight' => 60.0, 'reps' => 8]],
        );
        $this->createWorkoutWithSets(
            $user,
            $exercise,
            now()->subMonths(4)->toDateString(),
            [['weight' => 55.0, 'reps' => 8]],
        );

        $threeMonths = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => '3m',
        ]));
        $threeMonths->assertInertia(fn ($page) => $page->has('history.chart.points', 1));

        $all = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => 'all',
        ]));
        $all->assertInertia(fn ($page) => $page->has('history.chart.points', 2));
    }

    public function test_invalid_period_falls_back_to_all(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets(
            $user,
            $exercise,
            now()->subMonths(9)->toDateString(),
            [['weight' => 60.0, 'reps' => 8]],
        );

        $response = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => 'bogus',
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('period', 'all')
            ->has('history.chart.points', 1)
        );
    }

    // ------------------------------------------------------------------
    // 日付降順の全セット一覧
    // ------------------------------------------------------------------

    public function test_all_sets_are_listed_in_descending_date_order(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, '2026-08-01', [['weight' => 55.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.sets.0.date', '2026-09-01')
            ->where('history.sets.1.date', '2026-08-01')
        );
    }

    // ------------------------------------------------------------------
    // 体重比(Issue #21)
    // ------------------------------------------------------------------

    public function test_bodyweight_ratio_is_null_when_no_body_log_exists(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 68.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.points.0.bodyweight_ratio', null)
            ->where('history.chart.points.0.body_weight_kg', null)
            ->where('history.latestBodyweightRatio', null)
        );
    }

    public function test_bodyweight_ratio_uses_the_nearest_past_body_log(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-08-25', 'weight_kg' => 68.0]);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-05', 'weight_kg' => 66.0]);

        // 2026-09-01のワークアウトに一番近い過去の体重は8/25の68.0kg(9/5はまだ先)。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 80.0, 'reps' => 5]]);

        $expectedOneRepMax = (new ProgressionService)->estimateOneRepMax(80.0, 5);
        $expectedRatio = round($expectedOneRepMax / 68.0, 2);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.points.0.body_weight_kg', $this->sameNumber(68.0))
            ->where('history.chart.points.0.bodyweight_ratio', $this->sameNumber($expectedRatio))
            ->where('history.latestBodyweightRatio.ratio', $this->sameNumber($expectedRatio))
            ->where('history.latestBodyweightRatio.body_weight_kg', $this->sameNumber(68.0))
        );
    }

    public function test_bodyweight_ratio_uses_same_day_body_log_when_available(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-09-01', 'weight_kg' => 70.0]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 70.0, 'reps' => 1]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.points.0.body_weight_kg', $this->sameNumber(70.0))
            ->where('history.chart.points.0.bodyweight_ratio', $this->sameNumber(1.0))
        );
    }

    /**
     * 体重の記録が無い期間は体重比を出さない(受入条件)。記録がある期間だけ
     * 値が入り、それより前の期間は null のままであること。
     */
    public function test_bodyweight_ratio_is_null_before_the_first_body_log_but_present_after(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, '2026-08-01', [['weight' => 60.0, 'reps' => 8]]);
        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-08-15', 'weight_kg' => 65.0]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 65.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', [
            'exercise_id' => $exercise->id,
            'period' => 'all',
        ]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.points.0.bodyweight_ratio', null)
            ->where('history.chart.points.1.bodyweight_ratio', function ($value) {
                return $value !== null;
            })
        );
    }

    /**
     * 自重種目: 体重記録がある場合のみ、「体重+加重」の概算1RM(係数1.0)を
     * 補助情報として併記する。
     */
    public function test_bodyweight_exercise_carries_an_auxiliary_estimated_one_rep_max_when_body_weight_is_known(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => true]);

        BodyLog::factory()->create(['user_id' => $user->id, 'measured_on' => '2026-08-25', 'weight_kg' => 70.0]);
        // 加重懸垂: 5kg 吊るして8回。実質的な負荷 = 70 + 5 = 75kg。
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 5.0, 'reps' => 8]]);

        $expectedEffectiveOneRepMax = (new ProgressionService)->estimateOneRepMax(75.0, 8);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.metric', 'reps') // Issue #17 の方針は維持(主軸はレップ数のまま)
            ->where(
                'history.chart.points.0.estimated_one_rep_max_with_bodyweight',
                $this->sameNumber($expectedEffectiveOneRepMax)
            )
            ->where(
                'history.chart.points.0.bodyweight_ratio',
                $this->sameNumber(round($expectedEffectiveOneRepMax / 70.0, 2))
            )
        );
    }

    public function test_bodyweight_ratio_does_not_leak_another_users_body_log(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'is_bodyweight' => false]);

        BodyLog::factory()->create(['user_id' => $otherUser->id, 'measured_on' => '2026-08-25', 'weight_kg' => 999.0]);
        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 60.0, 'reps' => 8]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->where('history.chart.points.0.body_weight_kg', null)
            ->where('history.chart.points.0.bodyweight_ratio', null)
        );
    }

    // ------------------------------------------------------------------
    // 他ユーザーのデータが混入しないこと
    // ------------------------------------------------------------------

    public function test_another_users_records_on_the_same_default_exercise_do_not_leak_in(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'is_bodyweight' => false]);

        $this->createWorkoutWithSets($user, $exercise, '2026-09-01', [['weight' => 60.0, 'reps' => 8]]);
        $this->createWorkoutWithSets($otherUser, $exercise, '2026-09-02', [['weight' => 999.0, 'reps' => 1]]);

        $response = $this->actingAs($user)->get(route('history.index', ['exercise_id' => $exercise->id]));

        $response->assertInertia(fn ($page) => $page
            ->has('history.chart.points', 1)
            ->where('history.chart.points.0.weight', $this->sameNumber(60.0))
            ->where('history.personalBest.max_weight', $this->sameNumber(60.0))
            ->has('history.sets', 1)
        );
    }
}
