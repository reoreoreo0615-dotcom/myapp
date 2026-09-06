<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

class WorkoutPolicy
{
    /**
     * Determine whether the user can view (open the record screen for) the workout.
     */
    public function view(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    /**
     * Determine whether the user can add/edit/delete sets on this workout.
     *
     * 終了済み(finished_at が確定済み)のワークアウトは記録画面上では
     * 閲覧専用になるため、所有者であってもセットの追加・編集・削除は不可。
     *
     * Issue #23①: ただし「記録を修正する」で明示的な編集モードに入っている間
     * (editing_started_at が非null)は、終了済みでも追加・編集・削除を許可する。
     * finished_at 自体やそのセッション時点の progression_snapshot は
     * この間も一切変化しない。
     */
    public function update(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id
            && ($workout->finished_at === null || $workout->editing_started_at !== null);
    }

    /**
     * Determine whether the user can finish (confirm finished_at on) this
     * workout. Owner-only; finishing an already-finished workout is handled
     * as an idempotent no-op by the controller rather than being forbidden
     * here, so a duplicate tap/retry doesn't surface an error.
     */
    public function finish(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    /**
     * Determine whether the user can enter the explicit "修正する" edit mode
     * on an already-finished workout. Only makes sense once finished (an
     * unfinished workout is already editable via {@see update()}).
     */
    public function startEditing(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id && $workout->finished_at !== null;
    }

    /**
     * Determine whether the user can end the explicit edit mode
     * ("修正を終える"), returning the finished workout to read-only.
     */
    public function endEditing(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id && $workout->editing_started_at !== null;
    }

    /**
     * Determine whether the user can (soft) delete this workout, active or
     * finished.
     */
    public function delete(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    /**
     * Determine whether the user can restore a soft-deleted workout of theirs.
     */
    public function restore(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }
}
