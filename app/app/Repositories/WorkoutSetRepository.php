<?php

namespace App\Repositories;

use App\Models\WorkoutSet;
use App\Services\ProgressionService;
use Illuminate\Support\Facades\DB;

/**
 * workout_sets への DB アクセスを担当するクエリクラス。
 *
 * {@see ProgressionService} は Eloquent に依存しない純粋なロジックのため、
 * 「前回のトップセット」に必要な生データの取得はこちらの責務とする。
 */
class WorkoutSetRepository
{
    /**
     * 指定ユーザーが最後にその種目を行ったワークアウトの、
     * ウォームアップを除いたセット一覧を返す。
     *
     * 手順(2クエリ):
     *  1. is_warmup = false のセットを持つ、そのユーザー・その種目の
     *     ワークアウトのうち、performed_on が最も新しいものの workout_id を特定する。
     *  2. その workout_id × exercise_id の is_warmup = false セットを取得する。
     *
     * `workout_sets(exercise_id, workout_id)` の複合インデックスが
     * 両クエリの絞り込みに効くようにしている。
     *
     * @return array<int, array{weight: float, reps: int, is_warmup: bool}>
     */
    public function lastWorkingSetsFor(int $userId, int $exerciseId): array
    {
        $lastWorkoutId = DB::table('workout_sets')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workout_sets.exercise_id', $exerciseId)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->orderByDesc('workouts.performed_on')
            ->orderByDesc('workouts.id')
            ->limit(1)
            ->value('workout_sets.workout_id');

        if ($lastWorkoutId === null) {
            return [];
        }

        return WorkoutSet::query()
            ->where('exercise_id', $exerciseId)
            ->where('workout_id', $lastWorkoutId)
            ->where('is_warmup', false)
            ->get(['weight', 'reps', 'is_warmup'])
            ->map(fn (WorkoutSet $set): array => [
                'weight' => (float) $set->weight,
                'reps' => (int) $set->reps,
                'is_warmup' => (bool) $set->is_warmup,
            ])
            ->all();
    }
}
