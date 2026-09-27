<?php

namespace App\Policies;

use App\Models\ReservationSeat;
use App\Models\User;

class AttendancePolicy extends StaffPolicy
{
    /**
     * Admit: called as Gate::authorize('checkIn', [Attendance::class, $reservationSeat]).
     * Only a confirmed reservation can be admitted (paid ones are confirmed only once
     * PayMongo reports the payment), and each seat is admitted at most once.
     */
    public function checkIn(User $user, ReservationSeat $reservationSeat): bool
    {
        return $this->isStaff($user)
            && $reservationSeat->reservation->status === 'confirmed'
            && ! $reservationSeat->attendance()->exists();
    }
}
