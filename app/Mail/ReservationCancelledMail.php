<?php

namespace App\Mail;

class ReservationCancelledMail extends ReservationMail
{
    protected function subjectLine(): string
    {
        return 'Reservation cancelled';
    }

    protected function template(): string
    {
        return 'emails.reservations.cancelled';
    }
}
