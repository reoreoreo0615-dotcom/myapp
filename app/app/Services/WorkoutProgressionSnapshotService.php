<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Workout;
use App\Repositories\WorkoutSetRepository;

/**
 * ワークアウト開始時点(=まだ自分自身のセットが記録されていない時点)の
 * 「前回のセット」と「今日の目標」を種目ごとに一度だけ確定し、
 * workouts.progression_snapshot に凍結して保存する。
 *
 * なぜ必要か:
 * WorkoutSetRepository::lastWorkingSetsForMany() は「そのユーザーがその種目を
 * 最後に行ったワークアウト」を performed_on / id の降順で選ぶ。進行中の
 * ワークアウト自身にセットを記録すると、それ自身が「最後のワークアウト」に
 * なってしまうため、記録画面をリロードするたびに素朴に呼び出すと
 * 「前回」「今日の目標」が自分自身の直前の入力値に汚染され、
 * ダブルプログレッションが暴走する(例: 62.5kg を記録した直後に
 * 「前回 62.5kg」「今日の目標 65kg」に化けてしまう)。
 *
 * そのため、種目ごとに「この種目についてまだ何も記録していない」時点で
 * 一度だけ WorkoutSetRepository::lastWorkingSetsForMany() の結果を確定させ、
 * ワークアウトの行に固定する。以降は同じセッション内で何度リロードしても
 * その凍結値を返す。
 */
class WorkoutProgressionSnapshotService
{
    public function __construct(
        private readonly WorkoutSetRepository $workoutSetRepository,
        private readonly ProgressionService $progressionService,
    ) {}

    /**
     * 渡された種目のうち、まだスナップショットが無いものだけを確定させ、
     * 渡された種目全件分のスナップショットを返す。
     *
     * @param  array<int, Exercise>  $exercises  exercise_id をキーにした Exercise
     * @return array<int, array{prev: array<int, array{weight: float, reps: int, is_warmup: bool}>, target: array{weight: float, reps: int, type: string}|null}>
     */
    public function ensure(Workout $workout, array $exercises): array
    {
        $snapshot = $workout->progression_snapshot ?? [];

        $missingIds = array_values(array_diff(array_keys($exercises), array_keys($snapshot)));

        if ($missingIds !== []) {
            // Issue #23②: 過去日のワークアウトは、その日付「より後」の記録から
            // 目標を算出してはいけない。この workout 自身の performed_on 以前
            // (同日の自分より前に作られたワークアウトは含む。id が自分より
            // 若い = 必ずこの workout の作成より前に存在していたワークアウト
            // であるため、通常の「今日」のケースの挙動は変えない)に限定する。
            $lastWorkingSets = $this->workoutSetRepository->lastWorkingSetsForMany(
                $workout->user_id,
                $missingIds,
                $workout->performed_on->format('Y-m-d'),
                $workout->id,
            );

            foreach ($missingIds as $exerciseId) {
                $exercise = $exercises[$exerciseId];
                $prevSets = $lastWorkingSets[$exerciseId] ?? [];
                $topSet = $this->progressionService->pickTopSet($prevSets);

                $target = $topSet === null ? null : $this->progressionService->nextTarget(
                    $topSet['weight'],
                    $topSet['reps'],
                    (float) $exercise->weight_increment,
                    $exercise->target_rep_min,
                    $exercise->target_rep_max,
                );

                $snapshot[$exerciseId] = [
                    'prev' => $prevSets,
                    'target' => $target,
                ];
            }

            $workout->update(['progression_snapshot' => $snapshot]);
        }

        return array_intersect_key($snapshot, $exercises);
    }
}
