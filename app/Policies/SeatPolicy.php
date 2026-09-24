<?php

namespace App\Policies;

use App\Models\User;

class SeatPolicy extends StaffPolicy
{
    /** A seat that has ever been reserved stays, so reservation history remains intact. */
    public function delete(User $user, mixed $seat = null): bool
    {
        return $this->isStaff($user) && ! $seat->reservationSeats()->exists();
    }
}
