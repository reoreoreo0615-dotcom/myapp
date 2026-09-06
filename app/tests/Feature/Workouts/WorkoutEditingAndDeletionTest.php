<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Issue #23: 記録の信頼性(終了後の修正・過去日の記録・誤削除からの復元)。
 */
class WorkoutEditingAndDeletionTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // ① 終了済みワークアウトの編集モード
    // ------------------------------------------------------------------

    public function test_owner_can_enter_editing_mode_on_a_finished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($user)->patch(route('workouts.start-editing', $workout));

        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertNotNull($workout->fresh()->editing_started_at);
        // finished_at 自体は変化しない。
        $this->assertNotNull($workout->fresh()->finished_at);
    }

    public function test_cannot_start_editing_an_unfinished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->patch(route('workouts.start-editing', $workout));

        $response->assertForbidden();
        $this->assertNull($workout->fresh()->editing_started_at);
    }

    public function test_other_user_cannot_start_editing_the_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $owner->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($intruder)->patch(route('workouts.start-editing', $workout));

        $response->assertForbidden();
        $this->assertNull($workout->fresh()->editing_started_at);
    }

    public function test_entering_editing_mode_allows_adding_a_set_to_a_finished_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->actingAs($user)->patch(route('workouts.start-editing', $workout));

        $response = $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 60,
            'reps' => 8,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);
    }

    public function test_entering_editing_mode_allows_editing_and_deleting_an_existing_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8,
        ]);

        $this->actingAs($user)->patch(route('workouts.start-editing', $workout));

        $this->actingAs($user)->patch(route('workouts.sets.update', [$workout, $set]), [
            'weight' => 65.0,
            'reps' => 6,
        ])->assertRedirect();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'weight' => 65.0, 'reps' => 6]);

        $this->actingAs($user)->delete(route('workouts.sets.destroy', [$workout, $set]))
            ->assertRedirect();
        $this->assertSoftDeleted('workout_sets', ['id' => $set->id]);
    }

    /**
     * 最重要の再発防止テスト: 終了済みワークアウトを編集モードで修正しても
     * progression_snapshot(そのセッション時点の「前回」「今日の目標」)は
     * 一切変わらない。Issue #10 で凍結した値は履歴の記録であり、
     * 後から修正しても当時の目標は変わらない。
     */
    public function test_editing_a_finished_workout_does_not_change_the_progression_snapshot(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ]);

        $priorWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $priorWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 12, 'is_warmup' => false,
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-05',
            'started_at' => now()->subHour(),
        ]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 62.5, 'reps' => 8, 'is_warmup' => false,
        ]);
        // ensure() が実行され、progression_snapshot が凍結される。
        $this->actingAs($user)->get(route('workouts.show', $workout));
        $workout->update(['finished_at' => now()]);

        $snapshotBefore = $workout->fresh()->progression_snapshot;
        $this->assertNotNull($snapshotBefore);

        $this->actingAs($user)->patch(route('workouts.start-editing', $workout));

        // 記録を間違えて打ち直す(重量を書き換える)。
        $this->actingAs($user)->patch(route('workouts.sets.update', [$workout, $set]), [
            'weight' => 999.0,
            'reps' => 8,
        ])->assertRedirect();

        // さらに1セット追加する。
        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 70,
            'reps' => 8,
        ])->assertRedirect();

        $this->actingAs($user)->patch(route('workouts.end-editing', $workout));

        $snapshotAfter = $workout->fresh()->progression_snapshot;

        $this->assertSame($snapshotBefore, $snapshotAfter, 'progression_snapshot は修正後も一切変化してはいけない。');
        // finished_at 自体も変化しない。
        $this->assertNotNull($workout->fresh()->finished_at);
    }

    public function test_owner_can_end_editing_mode_returning_to_read_only(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'editing_started_at' => now(),
        ]);

        $response = $this->actingAs($user)->patch(route('workouts.end-editing', $workout));

        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertNull($workout->fresh()->editing_started_at);
    }

    public function test_ending_editing_mode_forbids_further_set_changes(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'editing_started_at' => now(),
        ]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->actingAs($user)->patch(route('workouts.end-editing', $workout));

        $response = $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 60,
            'reps' => 8,
        ]);

        $response->assertForbidden();
    }

    public function test_other_user_cannot_end_editing_the_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $owner->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'editing_started_at' => now(),
        ]);

        $response = $this->actingAs($intruder)->patch(route('workouts.end-editing', $workout));

        $response->assertForbidden();
        $this->assertNotNull($workout->fresh()->editing_started_at);
    }

    public function test_show_page_reflects_editing_mode_flags(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
            'editing_started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn ($page) => $page
            ->where('isFinished', true)
            ->where('isEditing', true)
            ->where('canEditSets', true)
        );
    }

    // ------------------------------------------------------------------
    // ② 過去の日付での記録
    // ------------------------------------------------------------------

    public function test_user_can_start_a_workout_with_a_past_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'routine_id' => null,
            'performed_on' => '2026-08-01',
        ]);

        $workout = Workout::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertSame('2026-08-01', $workout->performed_on->format('Y-m-d'));
    }

    public function test_future_performed_on_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'routine_id' => null,
            'performed_on' => now()->addDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('performed_on');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_performed_on_defaults_to_today_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('workouts.store'), ['routine_id' => null]);

        $workout = Workout::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(now()->toDateString(), $workout->performed_on->format('Y-m-d'));
    }

    /**
     * 最重要の再発防止テスト: 過去日のワークアウトの目標は、その日付より
     * 「後」の記録から算出してはいけない。今日時点の最新記録ではなく、
     * その日付時点で最後だった記録から目標を出す。
     */
    public function test_past_dated_workout_target_is_computed_from_records_before_that_date_only(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ]);

        // 8/1 時点で最後だった記録: 55kg x 10。
        // (どちらも finished_at 済みにしておく。未終了のワークアウトが1件でもあると
        // redirectToActiveWorkout() がそちらへ再誘導し、新規作成できなくなるため。)
        $earlyWorkout = Workout::factory()->create([
            'user_id' => $user->id, 'performed_on' => '2026-07-25', 'finished_at' => now(),
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $earlyWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 55.0, 'reps' => 10, 'is_warmup' => false,
        ]);

        // 8/1 より後(今日側)の記録: これを 8/1 のワークアウトの目標計算に使ってはいけない。
        $laterWorkout = Workout::factory()->create([
            'user_id' => $user->id, 'performed_on' => '2026-08-15', 'finished_at' => now(),
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $laterWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 90.0, 'reps' => 10, 'is_warmup' => false,
        ]);

        // 8/1 を指定して、今から(2026-09-06 時点で)過去日のワークアウトを追加入力する。
        $this->actingAs($user)->post(route('workouts.store'), [
            'routine_id' => null,
            'performed_on' => '2026-08-01',
        ]);
        $workout = Workout::where('user_id', $user->id)->where('performed_on', '2026-08-01')->firstOrFail();

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        // reps(10) < repMax(12) → レップアップ: 55kg のまま 11 回。90kg 側の記録は無視される。
        $response->assertInertia(fn ($page) => $page
            ->where('progression.'.$exercise->id.'.prev.0.weight', fn ($value) => (float) $value === 55.0)
            ->where('progression.'.$exercise->id.'.target.weight', fn ($value) => (float) $value === 55.0)
            ->where('progression.'.$exercise->id.'.target.reps', 11)
        );
    }

    // ------------------------------------------------------------------
    // ③ 誤削除からの復元(ワークアウト本体)
    // ------------------------------------------------------------------

    public function test_owner_can_delete_a_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $response = $this->actingAs($user)->delete(route('workouts.destroy', $workout));

        $response->assertRedirect(route('workouts.create'));
        $this->assertSoftDeleted('workouts', ['id' => $workout->id]);
    }

    public function test_other_user_cannot_delete_the_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id, 'started_at' => now()]);

        $response = $this->actingAs($intruder)->delete(route('workouts.destroy', $workout));

        $response->assertForbidden();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'deleted_at' => null]);
    }

    public function test_owner_can_restore_a_just_deleted_workout(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $workout->delete();

        $response = $this->actingAs($user)->patch(route('workouts.restore', $workout));

        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'deleted_at' => null]);
    }

    public function test_other_user_cannot_restore_the_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id, 'started_at' => now()]);
        $workout->delete();

        $response = $this->actingAs($intruder)->patch(route('workouts.restore', $workout));

        $response->assertForbidden();
        $this->assertSoftDeleted('workouts', ['id' => $workout->id]);
    }

    /**
     * 論理削除されたワークアウトは「進行中のワークアウト」として扱われては
     * いけない(新規開始をブロックしたり、そこへ再誘導したりしない)。
     */
    public function test_a_deleted_active_workout_does_not_block_starting_a_new_one(): void
    {
        $user = User::factory()->create();
        $deletedActiveWorkout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $deletedActiveWorkout->delete();

        $response = $this->actingAs($user)->post(route('workouts.store'), ['routine_id' => null]);

        $newWorkout = Workout::where('user_id', $user->id)->whereNull('finished_at')->firstOrFail();
        $response->assertRedirect(route('workouts.show', $newWorkout));
    }

    /**
     * セット0件で自動破棄されたワークアウトは物理削除であり、
     * ユーザーが明示的に削除したわけではないので復元対象にはならない。
     */
    /**
     * Issue #23③: routines を論理削除にしたことで生じる回帰の再発防止テスト。
     * ワークアウトが参照しているメニューが後から削除されても、記録画面が
     * クラッシュせず、そのメニューの種目構成(target_sets 等)を引き続き
     * 参照できること(Workout::routine() の withTrashed() の検証)。
     */
    public function test_show_page_still_works_after_its_routine_is_deleted(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'name' => '胸の日']);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $routine->routineExercises()->create([
            'exercise_id' => $exercise->id,
            'sort_order' => 0,
            'target_sets' => 3,
        ]);
        $workout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'performed_on' => now()->toDateString(),
            'started_at' => now(),
        ]);

        $routine->delete();

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('workout.routine_name', '胸の日')
            ->where('exercises.0.id', $exercise->id)
            ->where('exercises.0.target_sets', 3)
        );
    }

    public function test_a_workout_discarded_for_having_no_sets_cannot_be_restored(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);

        $this->actingAs($user)->patch(route('workouts.finish', $workout))
            ->assertRedirect(route('workouts.create'));

        $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);

        $this->actingAs($user)->patch(route('workouts.restore', $workout))->assertNotFound();
    }
}
