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
     */
    public function update(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }
}
