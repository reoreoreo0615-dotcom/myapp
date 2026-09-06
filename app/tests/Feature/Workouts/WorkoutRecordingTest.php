<?php

namespace Tests\Feature\Workouts;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkoutRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function createRoutineWithExercises(User $user, int $count): Routine
    {
        $routine = Routine::factory()->create(['user_id' => $user->id]);

        for ($i = 0; $i < $count; $i++) {
            $exercise = Exercise::factory()->create([
                'user_id' => null,
                'weight_increment' => 2.5,
                'target_rep_min' => 8,
                'target_rep_max' => 12,
            ]);

            $routine->routineExercises()->create([
                'exercise_id' => $exercise->id,
                'sort_order' => $i * 10,
                'target_sets' => 3,
            ]);
        }

        return $routine;
    }

    // ------------------------------------------------------------------
    // 認可 / ガード
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('workouts.create'))->assertRedirect(route('login'));
        $this->post(route('workouts.store'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_view_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('workouts.show', $workout))->assertForbidden();
    }

    public function test_user_cannot_record_a_set_on_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->actingAs($intruder)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 60,
            'reps' => 8,
        ])->assertForbidden();

        $this->assertDatabaseCount('workout_sets', 0);
    }

    // ------------------------------------------------------------------
    // ワークアウトの開始
    // ------------------------------------------------------------------

    public function test_user_can_start_a_workout_from_a_routine(): void
    {
        $user = User::factory()->create();
        $routine = $this->createRoutineWithExercises($user, 2);

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'routine_id' => $routine->id,
        ]);

        $workout = Workout::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertSame($routine->id, $workout->routine_id);
        $this->assertNotNull($workout->started_at);
    }

    public function test_user_can_start_a_workout_without_a_routine(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('workouts.store'), [
            'routine_id' => null,
        ]);

        $workout = Workout::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('workouts.show', $workout));
        $this->assertNull($workout->routine_id);
    }

    public function test_user_cannot_start_a_workout_from_another_users_routine(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->post(route('workouts.store'), [
            'routine_id' => $routine->id,
        ])->assertSessionHasErrors('routine_id');
    }

    // ------------------------------------------------------------------
    // 記録画面の表示内容
    // ------------------------------------------------------------------

    public function test_show_page_includes_previous_sets_and_target_for_each_exercise(): void
    {
        $user = User::factory()->create();
        $routine = $this->createRoutineWithExercises($user, 1);
        $exercise = $routine->exercises()->first();

        $priorWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-01',
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $priorWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 12,
            'is_warmup' => false,
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Workouts/Show')
            ->where('exercises.0.id', $exercise->id)
            ->where('progression.'.$exercise->id.'.prev.0.weight', fn ($value) => (float) $value === 60.0)
            ->where('progression.'.$exercise->id.'.prev.0.reps', 12)
            // reps(12) >= repMax(12) → 重量アップ: 62.5 x 8
            ->where('progression.'.$exercise->id.'.target.weight', 62.5)
            ->where('progression.'.$exercise->id.'.target.reps', 8)
            ->where('progression.'.$exercise->id.'.target.type', 'weight')
        );
    }

    public function test_show_page_query_count_does_not_scale_with_number_of_exercises(): void
    {
        $user = User::factory()->create();

        $smallRoutine = $this->createRoutineWithExercises($user, 2);
        $smallWorkout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $smallRoutine->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $largeRoutine = $this->createRoutineWithExercises($user, 8);
        $largeWorkout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $largeRoutine->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $this->actingAs($user);

        DB::enableQueryLog();
        $this->get(route('workouts.show', $smallWorkout))->assertOk();
        $smallQueryCount = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->get(route('workouts.show', $largeWorkout))->assertOk();
        $largeQueryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $smallQueryCount,
            $largeQueryCount,
            "2種目({$smallQueryCount}クエリ)と8種目({$largeQueryCount}クエリ)でクエリ数が変わってはいけない(N+1)。"
        );
    }

    // ------------------------------------------------------------------
    // 停滞している種目の表示(Issue #24①)
    // ------------------------------------------------------------------

    public function test_show_page_includes_plateau_status_for_a_stagnant_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ]);

        foreach ([
            ['2026-08-01', 50.0],
            ['2026-08-08', 60.0],
            ['2026-08-15', 60.0],
            ['2026-08-22', 60.0],
            ['2026-08-29', 60.0],
        ] as [$date, $weight]) {
            $priorWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => $date]);
            WorkoutSet::factory()->create([
                'workout_id' => $priorWorkout->id, 'exercise_id' => $exercise->id,
                'weight' => $weight, 'reps' => 8, 'is_warmup' => false,
            ]);
        }

        $workout = Workout::create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn ($page) => $page
            ->where('plateau.'.$exercise->id.'.status', 'stagnant')
            ->where('plateau.'.$exercise->id.'.exercise_id', $exercise->id)
        );
    }

    public function test_show_page_omits_plateau_for_an_exercise_that_is_still_progressing(): void
    {
        $user = User::factory()->create();
        $routine = $this->createRoutineWithExercises($user, 1);
        $exercise = $routine->exercises()->first();

        $priorWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $priorWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $routine->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        $response->assertInertia(fn ($page) => $page->where('plateau', []));
    }

    // ------------------------------------------------------------------
    // セットの記録(タップ1回で1セット)
    // ------------------------------------------------------------------

    public function test_recording_a_set_creates_it_with_sequential_set_numbers(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 62.5,
            'reps' => 8,
        ])->assertRedirect();

        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 62.5,
            'reps' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'set_number' => 1, 'is_warmup' => false,
        ]);
        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'set_number' => 2,
        ]);
        $this->assertDatabaseCount('workout_sets', 2);
    }

    public function test_recording_a_set_can_be_marked_as_warmup(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 40,
            'reps' => 10,
            'is_warmup' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_sets', [
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => true,
        ]);
    }

    /**
     * 受入条件: ウォームアップは集計・ナビ計算から除外される。
     * ウォームアップだけを記録したワークアウトは「最後に行った」とはみなされず、
     * それより前の(ワーキングセットを含む)ワークアウトが前回として扱われることを確認する。
     */
    public function test_warmup_sets_are_excluded_from_progression(): void
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
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-05',
            'started_at' => now(),
        ]);

        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 20,
            'reps' => 15,
            'is_warmup' => true,
        ])->assertRedirect();

        $response = $this->actingAs($user)->get(route('workouts.show', $workout));

        // ウォームアップだけの今日のセットは無視され、9/1 の 60x8 がまだ「前回」として出る。
        $response->assertInertia(fn ($page) => $page
            ->where('progression.'.$exercise->id.'.prev.0.weight', fn ($value) => (float) $value === 60.0)
            ->where('progression.'.$exercise->id.'.prev.0.reps', 8)
        );
    }

    /**
     * 記録画面の核心的な正しさ: セッション中に自分自身が記録したセットが
     * 「前回」「今日の目標」を汚染し、暴走しないこと。
     */
    public function test_target_does_not_shift_after_recording_a_set_in_the_same_session(): void
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
            'started_at' => now(),
        ]);

        $this->actingAs($user)->get(route('workouts.show', $workout))->assertInertia(fn ($page) => $page
            ->where('progression.'.$exercise->id.'.target.weight', 62.5)
            ->where('progression.'.$exercise->id.'.target.reps', 8)
        );

        // 目標どおり 62.5x8 を記録する。もし前回参照が自分自身に汚染されるなら、
        // 次の目標は 65kg にエスカレートしてしまうはず。
        $this->actingAs($user)->post(route('workouts.sets.store', $workout), [
            'exercise_id' => $exercise->id,
            'weight' => 62.5,
            'reps' => 8,
        ])->assertRedirect();

        $this->actingAs($user)->get(route('workouts.show', $workout))->assertInertia(fn ($page) => $page
            ->where('progression.'.$exercise->id.'.prev.0.weight', fn ($value) => (float) $value === 60.0)
            ->where('progression.'.$exercise->id.'.target.weight', 62.5)
            ->where('progression.'.$exercise->id.'.target.reps', 8)
        );
    }

    // ------------------------------------------------------------------
    // 二重送信防止
    // ------------------------------------------------------------------

    public function test_duplicate_client_request_id_does_not_create_a_second_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $payload = [
            'exercise_id' => $exercise->id,
            'weight' => 62.5,
            'reps' => 8,
            'client_request_id' => 'idem-key-1',
        ];

        $this->actingAs($user)->post(route('workouts.sets.store', $workout), $payload)->assertRedirect();
        // 連打を再現: 同じ client_request_id で再送する。
        $this->actingAs($user)->post(route('workouts.sets.store', $workout), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('workouts.sets.store', $workout), $payload)->assertRedirect();

        $this->assertDatabaseCount('workout_sets', 1);
    }

    // ------------------------------------------------------------------
    // セットの編集・削除
    // ------------------------------------------------------------------

    public function test_owner_can_edit_a_recorded_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8,
        ]);

        $this->actingAs($user)->patch(route('workouts.sets.update', [$workout, $set]), [
            'weight' => 65.0,
            'reps' => 6,
        ])->assertRedirect();

        $this->assertDatabaseHas('workout_sets', [
            'id' => $set->id, 'weight' => 65.0, 'reps' => 6,
        ]);
    }

    /**
     * Issue #23③: deleting a set is now a soft delete (so it can be
     * restored immediately after via the flash "undo" link), not a hard
     * delete.
     */
    public function test_owner_can_delete_a_recorded_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($user)->delete(route('workouts.sets.destroy', [$workout, $set]))->assertRedirect();

        $this->assertSoftDeleted('workout_sets', ['id' => $set->id]);
    }

    /**
     * Issue #23③: hitting the "元に戻す" undo link restores the set.
     */
    public function test_owner_can_restore_a_just_deleted_set(): void
    {
        $user = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);
        $set->delete();

        $response = $this->actingAs($user)->patch(route('workouts.sets.restore', [$workout, $set]));

        $response->assertRedirect();
        $this->assertDatabaseHas('workout_sets', ['id' => $set->id, 'deleted_at' => null]);
    }

    public function test_user_cannot_edit_a_set_belonging_to_another_users_workout(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $workout = Workout::factory()->create(['user_id' => $owner->id]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);

        $this->actingAs($intruder)->patch(route('workouts.sets.update', [$workout, $set]), [
            'weight' => 100, 'reps' => 1,
        ])->assertForbidden();
    }
}
