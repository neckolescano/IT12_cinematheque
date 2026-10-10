<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy extends StaffPolicy
{
    /** Reservations are only ever created by the public booking form. */
    public function create(User $user): bool
    {
        return false;
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->isStaff($user) && $reservation->status !== 'cancelled';
    }
}
