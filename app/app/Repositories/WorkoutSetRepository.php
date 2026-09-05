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

    /**
     * {@see lastWorkingSetsFor()} のバッチ版。
     *
     * 記録画面は5〜8種目を同時に表示するため、種目ごとに lastWorkingSetsFor() を
     * ループで呼ぶと N+1(10〜16クエリ)になる。このメソッドは種目数によらず
     * 常に2クエリで全種目分をまとめて取得する。
     *
     * 手順(2クエリ):
     *  1. 対象の種目群それぞれについて、is_warmup = false のセットを持つ
     *     ワークアウトのうち performed_on が最も新しいものの workout_id を
     *     特定する(DISTINCT (exercise_id, workout_id) の組だけを取得するため、
     *     行数はセット総数ではなく「種目×ワークアウト」の組数に収まる)。
     *  2. 特定した workout_id 群のセットをまとめて取得し、PHP側で
     *     「その種目にとって最後のワークアウトと一致する行」だけに絞り込む
     *     (同じ workout_id が複数種目にまたがる場合の取り違えを防ぐため)。
     *
     * `workout_sets(exercise_id, workout_id)` の複合インデックスが
     * 両クエリの絞り込みに効くようにしている。
     *
     * @param  array<int, int>  $exerciseIds
     * @return array<int, array<int, array{weight: float, reps: int, is_warmup: bool}>> exercise_id をキーにしたセット配列
     */
    public function lastWorkingSetsForMany(int $userId, array $exerciseIds): array
    {
        $exerciseIds = array_values(array_unique(array_map('intval', $exerciseIds)));

        if ($exerciseIds === []) {
            return [];
        }

        // クエリ1: 種目ごとの「最後のワークアウト」候補を DISTINCT (exercise_id, workout_id) で取得する。
        $candidates = DB::table('workout_sets')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->whereIn('workout_sets.exercise_id', $exerciseIds)
            ->where('workout_sets.is_warmup', false)
            ->where('workouts.user_id', $userId)
            ->select([
                'workout_sets.exercise_id',
                'workout_sets.workout_id',
                'workouts.performed_on',
                'workouts.id as workout_pk',
            ])
            ->distinct()
            ->get();

        $lastWorkoutByExercise = [];

        foreach ($candidates as $row) {
            $exerciseId = (int) $row->exercise_id;
            $current = $lastWorkoutByExercise[$exerciseId] ?? null;

            $isNewer = $current === null
                || $row->performed_on > $current['performed_on']
                || ($row->performed_on === $current['performed_on'] && (int) $row->workout_pk > $current['workout_pk']);

            if ($isNewer) {
                $lastWorkoutByExercise[$exerciseId] = [
                    'workout_id' => (int) $row->workout_id,
                    'performed_on' => $row->performed_on,
                    'workout_pk' => (int) $row->workout_pk,
                ];
            }
        }

        if ($lastWorkoutByExercise === []) {
            return [];
        }

        $workoutIds = array_values(array_unique(array_map(
            fn (array $entry): int => $entry['workout_id'],
            $lastWorkoutByExercise,
        )));

        // クエリ2: 特定した workout_id 群のセットをまとめて取得する。
        $sets = WorkoutSet::query()
            ->whereIn('workout_id', $workoutIds)
            ->whereIn('exercise_id', array_keys($lastWorkoutByExercise))
            ->where('is_warmup', false)
            ->get(['exercise_id', 'workout_id', 'weight', 'reps', 'is_warmup']);

        $result = [];

        foreach ($sets as $set) {
            $exerciseId = (int) $set->exercise_id;
            $expectedWorkoutId = $lastWorkoutByExercise[$exerciseId]['workout_id'] ?? null;

            // 同じ workout_id が複数種目にまたがるケースを取り違えないよう、
            // その種目にとって「最後のワークアウト」と一致する行だけを採用する。
            if ((int) $set->workout_id !== $expectedWorkoutId) {
                continue;
            }

            $result[$exerciseId][] = [
                'weight' => (float) $set->weight,
                'reps' => (int) $set->reps,
                'is_warmup' => (bool) $set->is_warmup,
            ];
        }

        return $result;
    }
}
