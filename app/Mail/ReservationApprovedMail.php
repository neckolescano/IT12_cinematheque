<?php

namespace App\Mail;

/** Approved = E-Ticket: the customer's proof of reservation for onsite admission. */
class ReservationApprovedMail extends ReservationMail
{
    protected function subjectLine(): string
    {
        return 'Your e-ticket';
    }

    protected function template(): string
    {
        return 'emails.reservations.approved';
    }
}
