<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Shared base for the reservation emails. Subclasses only choose a subject and a
 * template; the booking data given to every template is assembled here once.
 */
abstract class ReservationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }

    abstract protected function subjectLine(): string;

    abstract protected function template(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine().' · '.$this->reservation->booking_reference);
    }

    public function content(): Content
    {
        $this->reservation->loadMissing('screening.movie', 'reservationSeats.seat', 'reservationSeats.attendee', 'payment');

        return new Content(view: $this->template(), with: [
            'reservation' => $this->reservation,
            'screening' => $this->reservation->screening,
            'payment' => $this->reservation->payment,
            'bookingUrl' => route('bookings.show', $this->reservation),
        ]);
    }
}
