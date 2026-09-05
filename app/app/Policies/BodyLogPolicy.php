<?php

namespace App\Policies;

use App\Models\BodyLog;
use App\Models\User;

class BodyLogPolicy
{
    /**
     * Determine whether the user can edit this body log entry.
     */
    public function update(User $user, BodyLog $bodyLog): bool
    {
        return $user->id === $bodyLog->user_id;
    }

    /**
     * Determine whether the user can delete this body log entry.
     */
    public function delete(User $user, BodyLog $bodyLog): bool
    {
        return $user->id === $bodyLog->user_id;
    }
}
