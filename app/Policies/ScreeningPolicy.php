<?php

namespace App\Policies;

use App\Models\User;

class ScreeningPolicy extends StaffPolicy
{
    /** A screening that already has reservations cannot be deleted (FK is restrict). */
    public function delete(User $user, mixed $screening = null): bool
    {
        return $this->isStaff($user) && ! $screening->reservations()->exists();
    }
}
