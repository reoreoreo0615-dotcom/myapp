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
     */
    public function update(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id && $workout->finished_at === null;
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
}
