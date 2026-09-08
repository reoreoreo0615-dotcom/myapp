<?php

namespace Tests\Feature\Admin;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExerciseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'テストベンチプレス',
            'muscle_group' => 'chest',
            'movement_type' => 'push',
            'equipment' => 'barbell',
            'is_bodyweight' => false,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
            'sort_order' => 10,
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // 認可
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/exercises')->assertRedirect('/login');
    }

    public function test_non_admin_receives_a_403(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/exercises')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // 一覧
    // ------------------------------------------------------------------

    public function test_admin_can_view_the_exercise_list_with_usage_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null, 'name' => 'スクワット']);
        $workout = Workout::factory()->create();
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/exercises');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Exercises/Index')
            ->where('exercises.0.id', $exercise->id)
            ->where('exercises.0.workout_sets_count', 1)
        );
    }

    public function test_index_does_not_include_users_custom_exercises(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        Exercise::factory()->create(['user_id' => $owner->id, 'name' => '独自種目']);

        $response = $this->actingAs($admin)->get('/admin/exercises');

        $response->assertInertia(fn ($page) => $page
            ->where('exercises', fn ($exercises) => collect($exercises)->pluck('name')->doesntContain('独自種目'))
        );
    }

    // ------------------------------------------------------------------
    // 作成
    // ------------------------------------------------------------------

    public function test_admin_can_create_an_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload());

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'name' => 'テストベンチプレス',
            'user_id' => null,
            'weight_increment' => 2.5,
        ]);
    }

    public function test_creating_an_exercise_rejects_target_rep_min_greater_than_max(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload([
            'target_rep_min' => 15,
            'target_rep_max' => 10,
        ]));

        $response->assertSessionHasErrors('target_rep_max');
        $this->assertDatabaseMissing('exercises', ['name' => 'テストベンチプレス']);
    }

    public function test_creating_an_exercise_rejects_zero_weight_increment(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload([
            'weight_increment' => 0,
        ]));

        $response->assertSessionHasErrors('weight_increment');
        $this->assertDatabaseMissing('exercises', ['name' => 'テストベンチプレス']);
    }

    public function test_creating_an_exercise_rejects_a_name_already_used_by_a_default_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Exercise::factory()->create(['user_id' => null, 'name' => 'テストベンチプレス']);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload());

        $response->assertSessionHasErrors('name');
    }

    public function test_creating_an_exercise_allows_a_name_already_used_by_a_users_custom_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        Exercise::factory()->create(['user_id' => $owner->id, 'name' => 'テストベンチプレス']);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload());

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertDatabaseHas('exercises', ['name' => 'テストベンチプレス', 'user_id' => null]);
    }

    public function test_creating_an_exercise_rejects_an_invalid_enum_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload([
            'muscle_group' => 'not-a-real-muscle-group',
        ]));

        $response->assertSessionHasErrors('muscle_group');
    }

    // ------------------------------------------------------------------
    // 漸進法(Issue #27)
    // ------------------------------------------------------------------

    public function test_creating_an_exercise_without_progression_strategy_defaults_to_double(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/exercises', $this->validPayload());

        $this->assertDatabaseHas('exercises', [
            'name' => 'テストベンチプレス',
            'progression_strategy' => 'double',
        ]);
    }

    public function test_admin_can_create_an_exercise_with_linear_progression(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload([
            'progression_strategy' => 'linear',
        ]));

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertDatabaseHas('exercises', [
            'name' => 'テストベンチプレス',
            'progression_strategy' => 'linear',
        ]);
    }

    public function test_creating_an_exercise_rejects_an_invalid_progression_strategy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post('/admin/exercises', $this->validPayload([
            'progression_strategy' => 'not-a-real-strategy',
        ]));

        $response->assertSessionHasErrors('progression_strategy');
    }

    public function test_admin_can_change_an_exercises_progression_strategy(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($admin)->patch(
            "/admin/exercises/{$exercise->id}",
            $this->validPayload(['name' => $exercise->name, 'progression_strategy' => 'five_by_five'])
        );

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertSame('five_by_five', $exercise->fresh()->progression_strategy->value);
    }

    public function test_index_exposes_the_progression_strategy_of_each_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Exercise::factory()->linearProgression()->create(['user_id' => null, 'name' => 'テスト種目']);

        $response = $this->actingAs($admin)->get('/admin/exercises');

        $response->assertInertia(fn ($page) => $page
            ->where('exercises.0.progression_strategy', 'linear')
            ->has('progressionStrategies')
        );
    }

    // ------------------------------------------------------------------
    // 更新
    // ------------------------------------------------------------------

    public function test_admin_can_update_an_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null, 'weight_increment' => 2.5]);

        $response = $this->actingAs($admin)->patch(
            "/admin/exercises/{$exercise->id}",
            $this->validPayload(['name' => $exercise->name, 'weight_increment' => 5.0])
        );

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertSame(5.0, (float) $exercise->fresh()->weight_increment);
    }

    public function test_updating_an_exercise_rejects_target_rep_min_greater_than_max(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($admin)->patch(
            "/admin/exercises/{$exercise->id}",
            $this->validPayload([
                'name' => $exercise->name,
                'target_rep_min' => 20,
                'target_rep_max' => 5,
            ])
        );

        $response->assertSessionHasErrors('target_rep_max');
    }

    public function test_admin_cannot_update_a_users_custom_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $customExercise = Exercise::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->patch(
            "/admin/exercises/{$customExercise->id}",
            $this->validPayload(['name' => $customExercise->name])
        );

        $response->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 削除
    // ------------------------------------------------------------------

    public function test_admin_can_delete_an_unused_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $response = $this->actingAs($admin)->delete("/admin/exercises/{$exercise->id}");

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertDatabaseMissing('exercises', ['id' => $exercise->id]);
    }

    /**
     * workout_sets.exercise_id は RESTRICT のため、記録に使われている種目を
     * 削除しようとすると DB レベルで拒否される。500 にせず、具体的な件数
     * 付きのメッセージで案内できることを確認する。
     */
    public function test_deleting_an_exercise_used_in_records_is_blocked_with_a_count_message(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $exercise = Exercise::factory()->create(['user_id' => null]);
        $workout = Workout::factory()->create();
        WorkoutSet::factory()->count(3)->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/exercises/{$exercise->id}");

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'この種目は3件の記録で使われているため削除できません。');
        $this->assertDatabaseHas('exercises', ['id' => $exercise->id]);
    }

    public function test_admin_cannot_delete_a_users_custom_exercise(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $customExercise = Exercise::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($admin)->delete("/admin/exercises/{$customExercise->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('exercises', ['id' => $customExercise->id]);
    }

    // ------------------------------------------------------------------
    // 並び替え
    // ------------------------------------------------------------------

    public function test_admin_can_reorder_exercises_within_a_muscle_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Exercise::factory()->create(['user_id' => null, 'muscle_group' => 'chest', 'sort_order' => 10]);
        $second = Exercise::factory()->create(['user_id' => null, 'muscle_group' => 'chest', 'sort_order' => 20]);
        $third = Exercise::factory()->create(['user_id' => null, 'muscle_group' => 'chest', 'sort_order' => 30]);

        $response = $this->actingAs($admin)->patch('/admin/exercises/reorder', [
            'muscle_group' => 'chest',
            'order' => [$third->id, $first->id, $second->id],
        ]);

        $response->assertRedirect(route('admin.exercises.index'));
        $this->assertSame(10, $third->fresh()->sort_order);
        $this->assertSame(20, $first->fresh()->sort_order);
        $this->assertSame(30, $second->fresh()->sort_order);
    }

    public function test_reorder_rejects_an_order_that_omits_an_exercise_from_the_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $first = Exercise::factory()->create(['user_id' => null, 'muscle_group' => 'chest', 'sort_order' => 10]);
        $second = Exercise::factory()->create(['user_id' => null, 'muscle_group' => 'chest', 'sort_order' => 20]);

        $response = $this->actingAs($admin)->patch('/admin/exercises/reorder', [
            'muscle_group' => 'chest',
            'order' => [$first->id],
        ]);

        $response->assertSessionHasErrors('order');
        $this->assertSame(10, $first->fresh()->sort_order);
        $this->assertSame(20, $second->fresh()->sort_order);
    }

    // ------------------------------------------------------------------
    // このIssueの存在意義: weight_increment の編集が ProgressionService に反映される
    // ------------------------------------------------------------------

    public function test_editing_weight_increment_changes_the_next_target_shown_in_a_new_workout(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $trainee = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => null,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ]);

        // 前回: 100kg x 12(reps >= repMax なので次回は重量アップ)。
        $priorWorkout = Workout::factory()->create([
            'user_id' => $trainee->id,
            'performed_on' => '2026-09-01',
            'finished_at' => '2026-09-01 12:00:00',
        ]);
        WorkoutSet::factory()->create([
            'workout_id' => $priorWorkout->id,
            'exercise_id' => $exercise->id,
            'weight' => 100.0,
            'reps' => 12,
            'is_warmup' => false,
        ]);

        // 管理者が刻み幅を 2.5kg → 5.0kg に変更する。
        $updateResponse = $this->actingAs($admin)->patch("/admin/exercises/{$exercise->id}", [
            'name' => $exercise->name,
            'muscle_group' => $exercise->muscle_group->value,
            'movement_type' => $exercise->movement_type->value,
            'equipment' => $exercise->equipment->value,
            'is_bodyweight' => $exercise->is_bodyweight,
            'weight_increment' => 5.0,
            'target_rep_min' => $exercise->target_rep_min,
            'target_rep_max' => $exercise->target_rep_max,
            'sort_order' => $exercise->sort_order,
        ]);
        $updateResponse->assertRedirect(route('admin.exercises.index'));

        // 新しいワークアウトを開始すると、今日の目標が新しい刻み幅で算出される。
        $newWorkout = Workout::create([
            'user_id' => $trainee->id,
            'routine_id' => null,
            'performed_on' => '2026-09-06',
            'started_at' => now(),
        ]);

        $response = $this->actingAs($trainee)->get(route('workouts.show', $newWorkout));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('progression.'.$exercise->id.'.target.weight', fn ($value) => (float) $value === 105.0)
            ->where('progression.'.$exercise->id.'.target.reps', 8)
            ->where('progression.'.$exercise->id.'.target.type', 'weight')
        );
    }
}
