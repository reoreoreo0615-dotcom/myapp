<?php

namespace Tests\Feature\Export;

use App\Models\BodyLog;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * データのCSVエクスポート(Issue #26①)。
 */
class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private const UTF8_BOM = "\xEF\xBB\xBF";

    // ------------------------------------------------------------------
    // ワークアウト記録の CSV
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login_for_workout_export(): void
    {
        $this->get(route('export.workouts'))->assertRedirect(route('login'));
    }

    public function test_workout_csv_has_utf8_bom_and_header_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('export.workouts'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringStartsWith(self::UTF8_BOM, $content);
        $this->assertStringContainsString(
            "日付,種目,セット番号,重量,回数,RPE,ウォームアップ,メモ\n",
            $content,
        );
    }

    public function test_workout_csv_contains_the_users_sets(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'ベンチプレス']);
        $workout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
            'set_number' => 1,
            'weight' => 60,
            'reps' => 8,
            'rpe' => 8.5,
            'is_warmup' => false,
            'memo' => '好調',
        ]);

        $content = $this->actingAs($user)
            ->get(route('export.workouts'))
            ->streamedContent();

        $this->assertStringContainsString('2026-09-01,ベンチプレス,1,60.00,8,8.5,0,好調', $content);
    }

    public function test_workout_csv_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => null, 'name' => 'スクワット']);
        $workout = Workout::factory()->create(['user_id' => $otherUser->id]);
        WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);

        $content = $this->actingAs($user)
            ->get(route('export.workouts'))
            ->streamedContent();

        $this->assertStringNotContainsString('スクワット', $content);
    }

    public function test_workout_csv_excludes_a_soft_deleted_set(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => 'デッドリフト']);
        $workout = Workout::factory()->create(['user_id' => $user->id]);
        $set = WorkoutSet::factory()->create([
            'workout_id' => $workout->id,
            'exercise_id' => $exercise->id,
        ]);
        $set->delete();

        $content = $this->actingAs($user)
            ->get(route('export.workouts'))
            ->streamedContent();

        $this->assertStringNotContainsString('デッドリフト', $content);
    }

    public function test_workout_csv_respects_from_and_to_filters(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create(['user_id' => $user->id, 'name' => '種目A']);
        $insideWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-09-01']);
        $outsideWorkout = Workout::factory()->create(['user_id' => $user->id, 'performed_on' => '2026-01-01']);
        WorkoutSet::factory()->create(['workout_id' => $insideWorkout->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);
        WorkoutSet::factory()->create(['workout_id' => $outsideWorkout->id, 'exercise_id' => $exercise->id, 'set_number' => 1]);

        $content = $this->actingAs($user)
            ->get(route('export.workouts', ['from' => '2026-08-01', 'to' => '2026-09-30']))
            ->streamedContent();

        $this->assertStringContainsString('2026-09-01', $content);
        $this->assertStringNotContainsString('2026-01-01', $content);
    }

    public function test_workout_csv_rejects_an_invalid_date(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('export.workouts', ['from' => 'not-a-date']));

        $response->assertSessionHasErrors('from');
    }

    // ------------------------------------------------------------------
    // 体重記録の CSV
    // ------------------------------------------------------------------

    public function test_guest_is_redirected_to_login_for_body_log_export(): void
    {
        $this->get(route('export.body-logs'))->assertRedirect(route('login'));
    }

    public function test_body_log_csv_has_utf8_bom_and_header_row(): void
    {
        $user = User::factory()->create();

        $content = $this->actingAs($user)
            ->get(route('export.body-logs'))
            ->streamedContent();

        $this->assertStringStartsWith(self::UTF8_BOM, $content);
        $this->assertStringContainsString("日付,体重,体脂肪率,メモ\n", $content);
    }

    public function test_body_log_csv_contains_the_users_logs(): void
    {
        $user = User::factory()->create();
        BodyLog::factory()->create([
            'user_id' => $user->id,
            'measured_on' => '2026-09-01',
            'weight_kg' => 68.5,
            'body_fat_percentage' => 15.5,
            'memo' => '朝食前',
        ]);

        $content = $this->actingAs($user)
            ->get(route('export.body-logs'))
            ->streamedContent();

        $this->assertStringContainsString('2026-09-01,68.50,15.5,朝食前', $content);
    }

    public function test_body_log_csv_does_not_leak_another_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        BodyLog::factory()->create(['user_id' => $otherUser->id, 'weight_kg' => 99.9]);

        $content = $this->actingAs($user)
            ->get(route('export.body-logs'))
            ->streamedContent();

        $this->assertStringNotContainsString('99.9', $content);
    }
}
