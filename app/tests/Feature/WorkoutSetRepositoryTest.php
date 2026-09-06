<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Repositories\WorkoutSetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkoutSetRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutSetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new WorkoutSetRepository;
    }

    public function test_returns_empty_array_when_no_records_exist(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertSame([], $result);
    }

    public function test_returns_sets_from_the_most_recent_workout_only(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $olderWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-01',
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $olderWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 55.0,
            'reps' => 10,
            'is_warmup' => false,
        ]);

        $newerWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        $newerSet = WorkoutSet::factory()->create([
            'workout_id' => $newerWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame([
            'weight' => (float) $newerSet->weight,
            'reps' => $newerSet->reps,
            'is_warmup' => false,
        ], $result[0]);
    }

    public function test_excludes_warmup_sets_from_the_most_recent_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        WorkoutSet::factory()->warmup()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'weight' => 40.0,
            'reps' => 10,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame(60.0, $result[0]['weight']);
        $this->assertFalse($result[0]['is_warmup']);
    }

    public function test_does_not_leak_another_users_data_even_if_more_recent(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        $targetSet = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $otherWorkout = Workout::factory()->create([
            'user_id' => $otherUser->id,
            'performed_on' => '2026-09-05',
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $otherWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 999.0,
            'reps' => 1,
            'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame([
            'weight' => (float) $targetSet->weight,
            'reps' => $targetSet->reps,
            'is_warmup' => false,
        ], $result[0]);
    }

    public function test_does_not_include_sets_of_other_exercises_in_the_same_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $otherExercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        $targetSet = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $otherExercise->id,
            'weight' => 100.0,
            'reps' => 5,
            'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame([
            'weight' => (float) $targetSet->weight,
            'reps' => $targetSet->reps,
            'is_warmup' => false,
        ], $result[0]);
    }

    public function test_workout_with_only_warmup_sets_is_not_treated_as_the_most_recent(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        // 直近(9/3)はウォームアップのみ。実務上「最後に行った」とはみなされず、
        // ワーキングセットが存在する 9/1 まで遡って参照されるべき。
        $olderWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-01',
        ]);
        $olderSet = WorkoutSet::factory()->create([
            'workout_id' => $olderWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $newerWarmupOnlyWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        WorkoutSet::factory()->warmup()->create([
            'workout_id' => $newerWarmupOnlyWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 40.0,
            'reps' => 10,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame([
            'weight' => (float) $olderSet->weight,
            'reps' => $olderSet->reps,
            'is_warmup' => false,
        ], $result[0]);
    }

    public function test_prefers_the_workout_with_higher_id_when_performed_on_the_same_day(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $firstWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $firstWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 55.0,
            'reps' => 10,
            'is_warmup' => false,
        ]);

        $secondWorkout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        $this->assertGreaterThan($firstWorkout->id, $secondWorkout->id);

        $secondSet = WorkoutSet::factory()->create([
            'workout_id' => $secondWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 60.0,
            'reps' => 8,
            'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertCount(1, $result);
        $this->assertSame([
            'weight' => (float) $secondSet->weight,
            'reps' => $secondSet->reps,
            'is_warmup' => false,
        ], $result[0]);
    }

    public function test_issues_at_most_two_queries(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create([
            'user_id' => $user->id,
            'performed_on' => '2026-09-03',
        ]);
        WorkoutSet::factory()->count(3)->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'is_warmup' => false,
        ]);

        DB::enableQueryLog();

        $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $queryCount, 'lastWorkingSetsFor() should issue at most 2 queries (N+1 対策).');
    }

    // ------------------------------------------------------------------
    // lastWorkingSetsForMany()
    // ------------------------------------------------------------------

    public function test_many_returns_empty_array_for_empty_exercise_list(): void
    {
        $user = User::factory()->create();

        $result = $this->repository->lastWorkingSetsForMany($user->id, []);

        $this->assertSame([], $result);
    }

    public function test_many_returns_last_working_sets_per_exercise(): void
    {
        $user = User::factory()->create();
        $bench = Exercise::factory()->create(['user_id' => $user->id]);
        $squat = Exercise::factory()->create(['user_id' => $user->id]);

        // ベンチプレス: 9/1 (古い) と 9/3 (新しい)
        $benchOld = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $benchOld->id, 'exercise_id' => $bench->id,
            'weight' => 55.0, 'reps' => 10, 'is_warmup' => false,
        ]);
        $benchNew = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);
        $benchNewSet = WorkoutSet::factory()->create([
            'workout_id' => $benchNew->id, 'exercise_id' => $bench->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        // スクワット: 9/2 のみ
        $squatWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-02']);
        $squatSet = WorkoutSet::factory()->create([
            'workout_id' => $squatWorkout->id, 'exercise_id' => $squat->id,
            'weight' => 80.0, 'reps' => 5, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$bench->id, $squat->id]);

        $this->assertSame([
            'weight' => (float) $benchNewSet->weight,
            'reps' => $benchNewSet->reps,
            'is_warmup' => false,
        ], $result[$bench->id][0]);
        $this->assertSame([
            'weight' => (float) $squatSet->weight,
            'reps' => $squatSet->reps,
            'is_warmup' => false,
        ], $result[$squat->id][0]);
    }

    public function test_many_does_not_mix_up_sets_when_exercises_share_the_same_last_workout(): void
    {
        $user = User::factory()->create();
        $bench = Exercise::factory()->create(['user_id' => $user->id]);
        $squat = Exercise::factory()->create(['user_id' => $user->id]);

        // 同じワークアウトの中で両種目を実施している場合、取り違えないこと。
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);
        $benchSet = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $bench->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);
        $squatSet = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $squat->id,
            'weight' => 100.0, 'reps' => 5, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$bench->id, $squat->id]);

        $this->assertCount(1, $result[$bench->id]);
        $this->assertSame(60.0, $result[$bench->id][0]['weight']);
        $this->assertCount(1, $result[$squat->id]);
        $this->assertSame(100.0, $result[$squat->id][0]['weight']);
    }

    public function test_many_excludes_warmup_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);
        WorkoutSet::factory()->warmup()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 20.0, 'reps' => 10,
        ]);
        $workingSet = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id]);

        $this->assertCount(1, $result[$exercise->id]);
        $this->assertSame((float) $workingSet->weight, $result[$exercise->id][0]['weight']);
    }

    public function test_many_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $otherWorkout = Workout::factory()->create(['user_id' => $otherUser->id, 'performed_on' => '2026-09-05']);
        WorkoutSet::factory()->create([
            'workout_id' => $otherWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 999.0, 'reps' => 1, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id]);

        $this->assertArrayNotHasKey($exercise->id, $result);
    }

    public function test_many_omits_exercises_with_no_history(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id]);

        $this->assertSame([], $result);
    }

    /**
     * 受入条件: 種目数によらずクエリ数が増えないこと。3種目でも8種目でも
     * 常に2クエリで済むことを実測して確認する(N+1対策)。
     */
    public function test_many_issues_exactly_two_queries_regardless_of_exercise_count(): void
    {
        $user = User::factory()->create();

        $makeHistory = function () use ($user): Exercise {
            $exercise = Exercise::factory()->create(['user_id' => $user->id]);
            $workout = Workout::factory()->create([
                'user_id' => $user->id,
                'performed_on' => '2026-09-01',
            ]);
            WorkoutSet::factory()->count(3)->create([
                'workout_id' => $workout->id,
                'exercise_id' => $exercise->id,
                'is_warmup' => false,
            ]);

            return $exercise;
        };

        $threeExerciseIds = collect(range(1, 3))->map(fn () => $makeHistory()->id)->all();
        $eightExerciseIds = collect(range(1, 8))->map(fn () => $makeHistory()->id)->all();

        DB::enableQueryLog();
        $this->repository->lastWorkingSetsForMany($user->id, $threeExerciseIds);
        $queryCountForThree = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->repository->lastWorkingSetsForMany($user->id, $eightExerciseIds);
        $queryCountForEight = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queryCountForThree, '3種目でも2クエリのはず。');
        $this->assertSame(2, $queryCountForEight, '8種目でも2クエリのはず(N+1になっていないこと)。');
        $this->assertSame($queryCountForThree, $queryCountForEight, '種目数が増えてもクエリ数は変わらないこと。');
    }

    // ------------------------------------------------------------------
    // Issue #23③: 論理削除の除外
    //
    // このクラスの集計・履歴系メソッドは DB::table() を使っており、
    // Eloquent と違って論理削除を自動で除外しない。activeWorkoutSetsQuery()
    // 経由になっている全メソッドについて、(a) セット自身の論理削除、
    // (b) 親ワークアウトの論理削除、の両方が正しく除外されることを確認する。
    // ------------------------------------------------------------------

    public function test_many_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id]);

        $this->assertArrayNotHasKey($exercise->id, $result);
    }

    public function test_many_excludes_sets_belonging_to_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-03']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);
        $workout->delete();

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id]);

        $this->assertArrayNotHasKey($exercise->id, $result);
    }

    public function test_last_working_sets_for_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertSame([], $result);
    }

    public function test_last_working_sets_for_excludes_sets_of_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $workout->delete();

        $result = $this->repository->lastWorkingSetsFor($user->id, $exercise->id);

        $this->assertSame([], $result);
    }

    /**
     * Issue #23②: 過去日のワークアウトの目標は、その日付「より後」の記録から
     * 算出してはいけない。$onOrBeforeDate 以降(より新しい日付)の記録は
     * 候補から除外されることを確認する。
     */
    public function test_many_excludes_workouts_after_the_given_cutoff_date(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $before = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        $beforeSet = WorkoutSet::factory()->create([
            'workout_id' => $before->id, 'exercise_id' => $exercise->id,
            'weight' => 55.0, 'reps' => 10, 'is_warmup' => false,
        ]);

        // カットオフより後(未来)の記録。過去日のワークアウトの目標算出には使われてはいけない。
        $after = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-10']);
        WorkoutSet::factory()->create([
            'workout_id' => $after->id, 'exercise_id' => $exercise->id,
            'weight' => 999.0, 'reps' => 1, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id], '2026-09-05');

        $this->assertSame((float) $beforeSet->weight, $result[$exercise->id][0]['weight']);
    }

    public function test_many_excludes_the_given_workout_id_itself(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-05']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        $result = $this->repository->lastWorkingSetsForMany($user->id, [$exercise->id], '2026-09-05', $workout->id);

        $this->assertArrayNotHasKey($exercise->id, $result);
    }

    public function test_personal_best_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 100.0, 'reps' => 5, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->personalBest($user->id, $exercise->id);

        $this->assertNull($result['max_weight']);
    }

    public function test_personal_best_excludes_sets_of_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 100.0, 'reps' => 5, 'is_warmup' => false,
        ]);
        $workout->delete();

        $result = $this->repository->personalBest($user->id, $exercise->id);

        $this->assertNull($result['max_weight']);
    }

    public function test_has_any_recorded_sets_ignores_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $set->delete();

        $this->assertFalse($this->repository->hasAnyRecordedSets($user->id));
    }

    public function test_has_any_recorded_sets_ignores_sets_of_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $workout->delete();

        $this->assertFalse($this->repository->hasAnyRecordedSets($user->id));
    }

    public function test_weekly_volume_ignores_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => now()->toDateString()]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 100.0, 'reps' => 10, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->weeklyVolume(
            $user->id,
            now()->startOfWeek()->toDateString(),
            now()->startOfWeek()->subWeek()->toDateString(),
        );

        $this->assertSame(0.0, $result['this_week']);
    }

    public function test_history_all_sets_ignores_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);
        $set->delete();

        $result = $this->repository->historyAllSets($user->id, $exercise->id, null);

        $this->assertSame([], $result);
    }

    public function test_history_top_sets_per_workout_ignores_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $workout->delete();

        $result = $this->repository->historyTopSetsPerWorkout($user->id, $exercise->id, null);

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // sessionTopSetsForExercises() (Issue #24①)
    // ------------------------------------------------------------------

    private function createWorkoutWithTopSet(User $user, Exercise $exercise, string $performedOn, float $weight, int $reps): void
    {
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => $performedOn]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => $weight, 'reps' => $reps, 'is_warmup' => false,
        ]);
    }

    public function test_session_top_sets_returns_sessions_in_ascending_order_per_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->createWorkoutWithTopSet($user, $exercise, '2026-09-05', 62.5, 8);
        $this->createWorkoutWithTopSet($user, $exercise, '2026-09-01', 60.0, 8);

        $result = $this->repository->sessionTopSetsForExercises([$exercise->id], $user->id);

        $this->assertSame([
            ['performed_on' => '2026-09-01', 'weight' => 60.0, 'reps' => 8],
            ['performed_on' => '2026-09-05', 'weight' => 62.5, 'reps' => 8],
        ], $result[$exercise->id]);
    }

    public function test_session_top_sets_excludes_warmup_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 100.0, 'reps' => 1, 'is_warmup' => true,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'weight' => 60.0, 'reps' => 8, 'is_warmup' => false,
        ]);

        $result = $this->repository->sessionTopSetsForExercises([$exercise->id], $user->id);

        $this->assertSame([
            ['performed_on' => '2026-09-01', 'weight' => 60.0, 'reps' => 8],
        ], $result[$exercise->id]);
    }

    public function test_session_top_sets_picks_the_max_weight_set_within_a_session(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'set_number' => 1,
            'weight' => 60.0, 'reps' => 10, 'is_warmup' => false,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'set_number' => 2,
            'weight' => 62.5, 'reps' => 6, 'is_warmup' => false,
        ]);

        $result = $this->repository->sessionTopSetsForExercises([$exercise->id], $user->id);

        $this->assertSame([
            ['performed_on' => '2026-09-01', 'weight' => 62.5, 'reps' => 6],
        ], $result[$exercise->id]);
    }

    public function test_session_top_sets_with_empty_exercise_ids_returns_all_the_users_exercises(): void
    {
        $user = User::factory()->create();
        $exerciseA = Exercise::factory()->create(['user_id' => $user->id]);
        $exerciseB = Exercise::factory()->create(['user_id' => $user->id]);

        $this->createWorkoutWithTopSet($user, $exerciseA, '2026-09-01', 60.0, 8);
        $this->createWorkoutWithTopSet($user, $exerciseB, '2026-09-01', 40.0, 12);

        $result = $this->repository->sessionTopSetsForExercises([], $user->id);

        $this->assertArrayHasKey($exerciseA->id, $result);
        $this->assertArrayHasKey($exerciseB->id, $result);
    }

    public function test_session_top_sets_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $this->createWorkoutWithTopSet($otherUser, $exercise, '2026-09-01', 100.0, 5);

        $result = $this->repository->sessionTopSetsForExercises([$exercise->id], $user->id);

        $this->assertSame([], $result);
    }

    public function test_session_top_sets_respects_on_or_before_date_and_excluded_workout_id(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->createWorkoutWithTopSet($user, $exercise, '2026-09-01', 60.0, 8);
        $futureWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-10']);
        WorkoutSet::factory()->create([
            'workout_id' => $futureWorkout->id, 'exercise_id' => $exercise->id,
            'weight' => 999.0, 'reps' => 1, 'is_warmup' => false,
        ]);

        $result = $this->repository->sessionTopSetsForExercises(
            [$exercise->id],
            $user->id,
            onOrBeforeDate: '2026-09-05',
            excludeWorkoutId: $futureWorkout->id,
        );

        $this->assertSame([
            ['performed_on' => '2026-09-01', 'weight' => 60.0, 'reps' => 8],
        ], $result[$exercise->id]);
    }

    public function test_session_top_sets_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->sessionTopSetsForExercises([$exercise->id], $user->id);

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // movementTypeSetCounts() (Issue #24②)
    // ------------------------------------------------------------------

    public function test_movement_type_set_counts_groups_sets_by_exercise_movement_type(): void
    {
        $user = User::factory()->create();
        $pushExercise = Exercise::factory()->create(['user_id' => $user->id, 'movement_type' => 'push']);
        $pullExercise = Exercise::factory()->create(['user_id' => $user->id, 'movement_type' => 'pull']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => now()->toDateString()]);

        WorkoutSet::factory()->count(3)->create([
            'workout_id' => $workout->id, 'exercise_id' => $pushExercise->id, 'is_warmup' => false,
        ]);
        WorkoutSet::factory()->count(2)->create([
            'workout_id' => $workout->id, 'exercise_id' => $pullExercise->id, 'is_warmup' => false,
        ]);

        $result = $this->repository->movementTypeSetCounts($user->id, now()->subWeeks(4)->toDateString());

        $this->assertSame(3, $result['push']);
        $this->assertSame(2, $result['pull']);
        $this->assertArrayNotHasKey('legs', $result);
    }

    public function test_movement_type_set_counts_excludes_warmup_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'movement_type' => 'push']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => now()->toDateString()]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => true,
        ]);

        $result = $this->repository->movementTypeSetCounts($user->id, now()->subWeeks(4)->toDateString());

        $this->assertArrayNotHasKey('push', $result);
    }

    public function test_movement_type_set_counts_excludes_sets_before_the_since_date(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'movement_type' => 'push']);
        $oldWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => now()->subWeeks(10)->toDateString()]);
        WorkoutSet::factory()->create([
            'workout_id' => $oldWorkout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);

        $result = $this->repository->movementTypeSetCounts($user->id, now()->subWeeks(4)->toDateString());

        $this->assertArrayNotHasKey('push', $result);
    }

    public function test_movement_type_set_counts_ignores_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'movement_type' => 'push']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => now()->toDateString()]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);
        $set->delete();

        $result = $this->repository->movementTypeSetCounts($user->id, now()->subWeeks(4)->toDateString());

        $this->assertArrayNotHasKey('push', $result);
    }

    public function test_movement_type_set_counts_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'movement_type' => 'push']);
        $workout = Workout::factory()->create(['user_id' => $otherUser->id, 'performed_on' => now()->toDateString()]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);

        $result = $this->repository->movementTypeSetCounts($user->id, now()->subWeeks(4)->toDateString());

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // exportRows() (Issue #26①)
    // ------------------------------------------------------------------

    public function test_export_rows_returns_rows_in_performed_on_order(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'ベンチプレス']);
        $workoutLater = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-05']);
        $workoutEarlier = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $workoutLater->id, 'exercise_id' => $exercise->id,
            'set_number' => 1, 'weight' => 60, 'reps' => 8, 'is_warmup' => false,
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $workoutEarlier->id, 'exercise_id' => $exercise->id,
            'set_number' => 1, 'weight' => 55, 'reps' => 10, 'is_warmup' => false,
        ]);

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-09-01', $rows[0]->performed_on);
        $this->assertSame('2026-09-05', $rows[1]->performed_on);
        $this->assertSame('ベンチプレス', $rows[0]->exercise_name);
    }

    public function test_export_rows_respects_from_and_to_date_filters(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $this->createWorkoutWithTopSet($user, $exercise, '2026-08-01', 50, 10);
        $this->createWorkoutWithTopSet($user, $exercise, '2026-09-01', 55, 10);
        $this->createWorkoutWithTopSet($user, $exercise, '2026-10-01', 60, 10);

        $rows = $this->repository->exportRows($user->id, '2026-08-15', '2026-09-15')->all();

        $this->assertCount(1, $rows);
        $this->assertSame('2026-09-01', $rows[0]->performed_on);
    }

    public function test_export_rows_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $workout = Workout::factory()->create(['user_id' => $otherUser->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'is_warmup' => false,
        ]);

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertSame([], $rows);
    }

    public function test_export_rows_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);
        $set->delete();

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertSame([], $rows);
    }

    public function test_export_rows_excludes_sets_of_a_soft_deleted_workout(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
        ]);
        $workout->delete();

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertSame([], $rows);
    }

    public function test_export_rows_includes_rpe_and_memo(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'rpe' => 8.5, 'memo' => '調子が良かった', 'is_warmup' => true,
        ]);

        $rows = $this->repository->exportRows($user->id, null, null)->all();

        $this->assertCount(1, $rows);
        $this->assertSame('8.5', $rows[0]->rpe);
        $this->assertSame('調子が良かった', $rows[0]->memo);
        $this->assertSame(1, (int) $rows[0]->is_warmup);
    }
}
