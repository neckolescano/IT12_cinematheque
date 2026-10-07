<?php

namespace App\Mail;

/** Booking received. For paid screenings it carries the "complete payment" link. */
class ReservationPendingMail extends ReservationMail
{
    protected function subjectLine(): string
    {
        return $this->reservation->payment ? 'Complete your payment' : 'Reservation received';
    }

    protected function template(): string
    {
        return 'emails.reservations.pending';
    }
}
