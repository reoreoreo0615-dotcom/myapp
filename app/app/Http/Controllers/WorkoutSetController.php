<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkoutSetRequest;
use App\Http\Requests\UpdateWorkoutSetRequest;
use App\Models\Exercise;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\WorkoutProgressionSnapshotService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class WorkoutSetController extends Controller
{
    public function __construct(
        private readonly WorkoutProgressionSnapshotService $snapshotService,
    ) {}

    /**
     * Record one set. "タップ1回で1セット": the caller (front end) is
     * expected to already have the target values preset in the request.
     *
     * 二重送信対策は二段構え:
     *  1. client_request_id(冪等キー)で、同じ送信が既に処理済みなら
     *     何もせず成功として返す。
     *  2. 万一ほぼ同時に2つのリクエストがこのチェックをすり抜けても、
     *     client_request_id のユニーク制約が2件目の INSERT を弾く
     *     (Duplicate entry はエラーにせず無視する)。
     */
    public function store(StoreWorkoutSetRequest $request, Workout $workout): RedirectResponse
    {
        $data = $request->validated();

        if (! empty($data['client_request_id'])) {
            $alreadyRecorded = WorkoutSet::query()
                ->where('workout_id', $workout->id)
                ->where('client_request_id', $data['client_request_id'])
                ->exists();

            if ($alreadyRecorded) {
                return Redirect::back();
            }
        }

        // このワークアウト自身のセットで「前回」「今日の目標」が汚染される前に
        // スナップショットを確定させる(通常は記録画面の表示時点で既に確定済み)。
        $exercise = Exercise::findOrFail($data['exercise_id']);
        $this->snapshotService->ensure($workout, [$exercise->id => $exercise]);

        $setNumber = (int) (WorkoutSet::query()
            ->where('workout_id', $workout->id)
            ->where('exercise_id', $exercise->id)
            ->max('set_number')) + 1;

        try {
            WorkoutSet::create([
                'workout_id' => $workout->id,
                'exercise_id' => $exercise->id,
                'set_number' => $setNumber,
                'weight' => $data['weight'],
                'reps' => $data['reps'],
                'rpe' => $data['rpe'] ?? null,
                'is_warmup' => $data['is_warmup'] ?? false,
                'client_request_id' => $data['client_request_id'] ?? null,
            ]);
        } catch (QueryException $e) {
            if (! $this->isDuplicateEntry($e)) {
                throw $e;
            }

            // client_request_id のユニーク制約に競合した = ほぼ同時の多重送信。
            // 既に1件目が保存されているので、ここでは無視して成功扱いにする。
        }

        return Redirect::back();
    }

    /**
     * Correct a previously recorded set's weight/reps/is_warmup.
     */
    public function update(UpdateWorkoutSetRequest $request, Workout $workout, WorkoutSet $workoutSet): RedirectResponse
    {
        abort_unless($workoutSet->workout_id === $workout->id, 404);

        $workoutSet->update($request->validated());

        return Redirect::back();
    }

    /**
     * Delete a previously recorded set (soft delete, Issue #23③). The flash
     * "undo" link lets the front end offer an immediate "元に戻す".
     */
    public function destroy(Workout $workout, WorkoutSet $workoutSet): RedirectResponse
    {
        $this->authorize('update', $workout);
        abort_unless($workoutSet->workout_id === $workout->id, 404);

        $workoutSet->delete();

        return Redirect::back()
            ->with('success', 'セットを削除しました。')
            ->with('undo', route('workouts.sets.restore', [$workout, $workoutSet]));
    }

    /**
     * Restore a set that was just soft-deleted ("元に戻す"). Gated by the
     * same "update" ability as add/edit/delete (so it also requires the
     * workout's explicit edit mode if it's already finished).
     */
    public function restore(Workout $workout, WorkoutSet $workoutSet): RedirectResponse
    {
        $this->authorize('update', $workout);
        abort_unless($workoutSet->workout_id === $workout->id, 404);

        $workoutSet->restore();

        return Redirect::back()->with('success', 'セットを元に戻しました。');
    }

    private function isDuplicateEntry(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
