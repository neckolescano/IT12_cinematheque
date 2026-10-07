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

    /**
     * Staff can confirm a pending reservation for a free screening.
     * A paid reservation is confirmed only when PayMongo reports it paid.
     */
    public function confirm(User $user, Reservation $reservation): bool
    {
        return $this->isStaff($user)
            && $reservation->status === 'pending'
            && ! $reservation->screening->isPaid();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $this->isStaff($user) && $reservation->status !== 'cancelled';
    }
}
