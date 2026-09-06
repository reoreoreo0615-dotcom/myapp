<?php

namespace App\Services;

use App\Repositories\WorkoutSetRepository;

/**
 * 種目メタ情報とセッション履歴(生の重量・reps)を突き合わせ、種目ごとの
 * 停滞判定({@see PlateauService})を行うための橋渡し役。
 *
 * {@see PlateauService} 自体は「指標(metric)がすでに計算済みの配列」しか
 * 受け取らない純粋なロジックのため、Issue #17 の決定
 * (通常種目=推定1RM、自重種目=reps)に基づく指標への変換はここで行う
 * ({@see ExerciseHistoryService} が推移グラフに対して行っているのと同じ変換)。
 *
 * Eloquent / DB には依存しない。実データの取得は
 * {@see WorkoutSetRepository::sessionTopSetsForExercises()} の責務。
 */
class PlateauAnalysisService
{
    public function __construct(
        private readonly ProgressionService $progressionService,
        private readonly PlateauService $plateauService,
    ) {}

    /**
     * @param  array<int, array{id: int, name: string, is_bodyweight: bool, weight_increment: float, target_rep_min: int, target_rep_max: int}>  $exercises
     * @param  array<int, array<int, array{performed_on: string, weight: float, reps: int}>>  $sessionsByExerciseId  exercise_id をキー、performed_on 昇順
     * @return array<int, array{exercise_id: int, exercise_name: string, is_bodyweight: bool, status: 'stagnant'|'declining', sessions_without_update: int, baseline: array{weight: float, reps: int}, suggestions: array<int, array<string, mixed>>}>
     */
    public function analyze(array $exercises, array $sessionsByExerciseId): array
    {
        $results = [];

        foreach ($exercises as $exercise) {
            $sessions = $sessionsByExerciseId[$exercise['id']] ?? [];

            if ($sessions === []) {
                continue;
            }

            $metricSessions = array_map(
                fn (array $session): array => [
                    'weight' => $session['weight'],
                    'reps' => $session['reps'],
                    'metric' => $exercise['is_bodyweight']
                        ? (float) $session['reps']
                        : $this->progressionService->estimateOneRepMax($session['weight'], $session['reps']),
                ],
                $sessions,
            );

            $detection = $this->plateauService->detect(
                $metricSessions,
                (float) $exercise['weight_increment'],
                $exercise['target_rep_min'],
                $exercise['target_rep_max'],
            );

            if ($detection === null) {
                continue;
            }

            $results[] = [
                'exercise_id' => $exercise['id'],
                'exercise_name' => $exercise['name'],
                'is_bodyweight' => $exercise['is_bodyweight'],
                ...$detection,
            ];
        }

        return $results;
    }
}
