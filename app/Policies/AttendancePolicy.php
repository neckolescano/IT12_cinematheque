<?php

namespace App\Policies;

use App\Models\ReservationSeat;
use App\Models\User;

/**
 * Door check-in. Admit, Undo and Note work only while the screening's check-in is open (20 minutes before
 * the start until 60 minutes after the end, Screening::checkInState()); the Super Admin can always correct
 * a roster, e.g. before submitting the program report.
 */
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
            && ! $reservationSeat->attendance()->exists()
            && $reservationSeat->screening->allowsCheckInBy($user);
    }

    /** Note on an admission. */
    public function update(User $user, mixed $attendance = null): bool
    {
        return $this->isStaff($user) && $attendance?->reservationSeat->screening->allowsCheckInBy($user);
    }

    /** Undo an admission. */
    public function delete(User $user, mixed $attendance = null): bool
    {
        return $this->update($user, $attendance);
    }
}
