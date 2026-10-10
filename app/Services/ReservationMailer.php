<?php

namespace App\Services;

use App\Mail\ReservationApprovedMail;
use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationPendingMail;
use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the three customer emails. Delivery never blocks or undoes a reservation:
 * a failure is logged (storage/logs/laravel.log) and reported back as `false` so the
 * caller can tell staff/customers, and staff can resend from the reservation page.
 */
class ReservationMailer
{
    /** A paid booking's seats are held: pay by the deadline (link inside). */
    public function pending(Reservation $reservation): bool
    {
        return $this->send($reservation, new ReservationPendingMail($reservation), 'pending');
    }

    /** "Confirmed" and the e-ticket are the same email. */
    public function approved(Reservation $reservation): bool
    {
        return $this->send($reservation, new ReservationApprovedMail($reservation), 'approved');
    }

    public function cancelled(Reservation $reservation): bool
    {
        return $this->send($reservation, new ReservationCancelledMail($reservation), 'cancelled');
    }

    /** The email that matches the reservation's current status. */
    public function forCurrentStatus(Reservation $reservation): bool
    {
        return match ($reservation->status) {
            'confirmed' => $this->approved($reservation),
            'cancelled' => $this->cancelled($reservation),
            default => $this->pending($reservation),
        };
    }

    private function send(Reservation $reservation, Mailable $mail, string $type): bool
    {
        if (blank($reservation->lead_email)) {
            Log::warning('Reservation email skipped: no email address on file', [
                'booking' => $reservation->booking_reference, 'type' => $type,
            ]);

            return false;
        }

        try {
            Mail::to($reservation->lead_email, $reservation->lead_full_name)->send($mail);

            return true;
        } catch (\Throwable $e) {
            Log::error('Reservation email failed', [
                'booking' => $reservation->booking_reference,
                'type' => $type,
                'to' => $reservation->lead_email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
