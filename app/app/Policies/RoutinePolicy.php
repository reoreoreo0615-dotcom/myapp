<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    /**
     * Determine whether the user can view/edit the routine (its name,
     * description, and exercise composition — add / remove / reorder / target_sets
     * are all treated as part of "editing the menu").
     */
    public function update(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }

    /**
     * Determine whether the user can delete the routine.
     */
    public function delete(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }
}
