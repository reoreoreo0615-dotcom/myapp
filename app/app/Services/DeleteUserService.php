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
     *
     * Issue #23③ note: workouts and routines now use SoftDeletes, but user
     * deletion must remain a true, complete erasure ("ユーザー削除は完全消去が
     * 正しい"), not a logical delete. Hence withTrashed()->forceDelete() for
     * both:
     *  - withTrashed(): the user may already have soft-deleted (trashed)
     *    workouts/routines sitting around; those must be purged too rather
     *    than left as orphans once the user row is gone.
     *  - forceDelete(): bypasses SoftDeletes and issues a real SQL DELETE,
     *    which is what actually fires the physical
     *    workout_sets.workout_id / routine_exercises.routine_id
     *    ON DELETE CASCADE constraints that step 3 depends on. A soft
     *    delete (a plain UPDATE) would leave workout_sets rows behind and
     *    make the exercises RESTRICT delete below fail.
     *  exercises / body_logs intentionally do not use SoftDeletes
     *  (Issue #23③), so `->delete()` on them is already a real delete.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->workouts()->withTrashed()->forceDelete();
            $user->routines()->withTrashed()->forceDelete();
            $user->exercises()->delete();
            $user->bodyLogs()->delete();
            $user->delete();
        });
    }
}
