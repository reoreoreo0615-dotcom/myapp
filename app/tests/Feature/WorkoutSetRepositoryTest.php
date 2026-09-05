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
}
