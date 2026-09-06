<?php

namespace Tests\Unit;

use App\Services\PlateauAnalysisService;
use App\Services\PlateauService;
use App\Services\ProgressionService;
use PHPUnit\Framework\TestCase;

/**
 * PlateauAnalysisService は Eloquent / DB に依存しない(生の配列だけを扱う)ため、
 * ExerciseHistoryServiceTest 相当の純粋ユニットテストとして書く。RefreshDatabase は使わない。
 */
class PlateauAnalysisServiceTest extends TestCase
{
    private PlateauAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PlateauAnalysisService(new ProgressionService, new PlateauService);
    }

    private function exercise(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'name' => 'ベンチプレス',
            'is_bodyweight' => false,
            'weight_increment' => 2.5,
            'target_rep_min' => 8,
            'target_rep_max' => 12,
        ], $overrides);
    }

    private function session(string $date, float $weight, int $reps): array
    {
        return ['performed_on' => $date, 'weight' => $weight, 'reps' => $reps];
    }

    public function test_exercise_with_no_sessions_is_skipped(): void
    {
        $result = $this->service->analyze([$this->exercise()], []);

        $this->assertSame([], $result);
    }

    public function test_exercise_still_progressing_is_not_included(): void
    {
        // 通常種目: 推定1RM が毎回更新されている → 停滞なし。
        $sessions = [
            $this->session('2026-08-01', 50.0, 8),
            $this->session('2026-08-08', 55.0, 8),
            $this->session('2026-08-15', 60.0, 8),
            $this->session('2026-08-22', 65.0, 8),
        ];

        $result = $this->service->analyze([$this->exercise()], [1 => $sessions]);

        $this->assertSame([], $result);
    }

    public function test_plateaued_normal_exercise_uses_estimated_one_rep_max_as_the_metric(): void
    {
        // 60x8 の推定1RM(Epley式)= 60 * (1 + 8/30) = 76.0 で4回連続横ばい。
        $sessions = [
            $this->session('2026-08-01', 50.0, 8),
            $this->session('2026-08-08', 60.0, 8),
            $this->session('2026-08-15', 60.0, 8),
            $this->session('2026-08-22', 60.0, 8),
            $this->session('2026-08-29', 60.0, 8),
        ];

        $result = $this->service->analyze([$this->exercise()], [1 => $sessions]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['exercise_id']);
        $this->assertSame('ベンチプレス', $result[0]['exercise_name']);
        $this->assertFalse($result[0]['is_bodyweight']);
        $this->assertSame('stagnant', $result[0]['status']);
        $this->assertSame(3, $result[0]['sessions_without_update']);
    }

    public function test_bodyweight_exercise_uses_reps_as_the_metric_not_estimated_one_rep_max(): void
    {
        // Issue #17 の決定: 自重種目は reps が指標。weight=0 でも推定1RMの
        // ゼロ扱いに巻き込まれず、reps の横ばいで正しく停滞判定される。
        $sessions = [
            $this->session('2026-08-01', 0.0, 8),
            $this->session('2026-08-08', 0.0, 12),
            $this->session('2026-08-15', 0.0, 12),
            $this->session('2026-08-22', 0.0, 12),
            $this->session('2026-08-29', 0.0, 12),
        ];

        $exercise = $this->exercise(['is_bodyweight' => true, 'name' => '懸垂']);

        $result = $this->service->analyze([$exercise], [1 => $sessions]);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]['is_bodyweight']);
        $this->assertSame('stagnant', $result[0]['status']);
        $this->assertSame(12, $result[0]['baseline']['reps']);
    }

    public function test_multiple_exercises_are_analyzed_independently(): void
    {
        $plateauedSessions = [
            $this->session('2026-08-01', 50.0, 8),
            $this->session('2026-08-08', 60.0, 8),
            $this->session('2026-08-15', 60.0, 8),
            $this->session('2026-08-22', 60.0, 8),
            $this->session('2026-08-29', 60.0, 8),
        ];
        $progressingSessions = [
            $this->session('2026-08-01', 50.0, 8),
            $this->session('2026-08-08', 55.0, 8),
            $this->session('2026-08-15', 60.0, 8),
            $this->session('2026-08-22', 65.0, 8),
        ];

        $result = $this->service->analyze(
            [$this->exercise(['id' => 1, 'name' => 'ベンチプレス']), $this->exercise(['id' => 2, 'name' => 'スクワット'])],
            [1 => $plateauedSessions, 2 => $progressingSessions],
        );

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['exercise_id']);
    }
}
