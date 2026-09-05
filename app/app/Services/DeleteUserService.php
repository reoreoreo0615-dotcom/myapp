<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteUserService
{
    /**
     * Permanently delete a user and every resource that belongs to them.
     *
     * MySQL does not guarantee the execution order of the multiple cascade
     * paths that originate from `users`, so we cannot rely on foreign keys
     * alone. `workout_sets.exercise_id` is RESTRICT, which means an exercise
     * cannot be deleted while sets referencing it still exist. We therefore
     * delete explicitly, inside a transaction, in an order that is always
     * safe:
     *
     *  1. workouts    → cascades to workout_sets
     *  2. routines    → cascades to routine_exercises
     *  3. exercises   → now safe, since workout_sets are already gone
     *  4. body_logs   → no dependents; order relative to the others doesn't
     *                   matter, but it must happen before the user row is
     *                   gone (Issue #21, following the #13 pattern so no
     *                   orphaned body_logs row survives the user).
     *  5. the user
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->workouts()->delete();
            $user->routines()->delete();
            $user->exercises()->delete();
            $user->bodyLogs()->delete();
            $user->delete();
        });
    }
}
