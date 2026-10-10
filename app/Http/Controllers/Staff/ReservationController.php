<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\PayMongo\PayMongoException;
use App\Services\ReservationMailer;
use App\Services\ReservationPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Booking actions used from the screening roster: cancel, resend email, refresh from PayMongo. (Bookings are approved automatically.) */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationMailer $mailer)
    {
    }

    /** The bookings list was merged into each screening's roster; old links land on the screenings list. */
    public function index(): RedirectResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        return redirect()->route('staff.screenings.index');
    }

    /** A booking opens on its screening's roster, expanded. */
    public function show(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('view', $reservation);

        return redirect()->to(route('staff.screenings.show', [$reservation->screening_id, 'open' => $reservation->booking_reference]).'#booking-'.$reservation->booking_reference);
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        // Releases the seats for other bookings; the record and attendee list stay.
        $reservation->cancel('staff');

        return back()->with(...$this->notice('Reservation cancelled.', $this->mailer->cancelled($reservation), 'cancellation notice'));
    }

    /** Re-send the email that matches the current status (e.g. after an SMTP failure). */
    public function resend(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        return $this->mailer->forCurrentStatus($reservation)
            ? back()->with('status', 'Email sent to '.$reservation->lead_email.'.')
            : back()->with('warning', 'The email could not be sent. Check the mail settings in .env and storage/logs/laravel.log.');
    }

    /** Ask PayMongo for the latest status (useful when webhooks can't reach this server). */
    public function syncPayment(Reservation $reservation, ReservationPayments $payments): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        if (! $reservation->payment?->provider_session_id) {
            return back()->with('warning', 'This booking has not started a PayMongo payment yet.');
        }

        try {
            $paid = $payments->sync($reservation->payment);
        } catch (PayMongoException $e) {
            report($e);

            return back()->with('warning', 'Could not reach PayMongo: '.$e->getMessage());
        }

        return match (true) {
            ! $paid => back()->with('status', 'PayMongo has no completed payment for this booking yet.'),
            $reservation->refresh()->status === 'cancelled' => back()->with('warning', 'PayMongo reports this booking as paid, but the booking is cancelled. A refund is due.'),
            default => back()->with('status', 'PayMongo reports this booking as paid.'),
        };
    }

    /** @return array{0: string, 1: string} flash key + message */
    private function notice(string $done, bool $emailed, string $what): array
    {
        return $emailed
            ? ['status', $done.' '.ucfirst($what).' emailed.']
            : ['warning', $done.' The '.$what.' email was not sent. Use "Resend email" once mail works.'];
    }
}
