<?php

namespace App\Policies;

use App\Models\ReservationSeat;
use App\Models\User;

class AttendancePolicy extends StaffPolicy
{
    /**
     * Check-in: called as Gate::authorize('checkIn', [Attendance::class, $reservationSeat]).
     * One admission per seat, and never for a cancelled reservation.
     */
    public function checkIn(User $user, ReservationSeat $reservationSeat): bool
    {
        return $this->isStaff($user)
            && $reservationSeat->reservation->status !== 'cancelled'
            && ! $reservationSeat->attendance()->exists();
    }
}
