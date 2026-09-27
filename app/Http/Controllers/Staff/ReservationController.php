<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Screening;
use App\Services\PayMongo\PayMongoException;
use App\Services\ReservationMailer;
use App\Services\ReservationPayments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Search across all bookings, plus approve / cancel / resend / payment refresh. */
class ReservationController extends Controller
{
    public function __construct(private readonly ReservationMailer $mailer)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Reservation::class);

        $filters = $request->validate([
            'screening_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Reservation::STATUSES)],
            'payment' => ['nullable', Rule::in(['paid', 'unpaid', 'free'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reservations = Reservation::with('screening', 'payment')
            ->withCount('reservationSeats')
            ->when($filters['screening_id'] ?? null, fn ($q, $id) => $q->where('screening_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['payment'] ?? null, fn ($q, $p) => match ($p) {
                'paid' => $q->whereHas('payment', fn ($q) => $q->where('status', 'verified')),
                'unpaid' => $q->whereHas('payment', fn ($q) => $q->where('status', '!=', 'verified')),
                'free' => $q->doesntHave('payment'),
            })
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('booking_reference', 'like', "%{$term}%")
                        ->orWhere('lead_last_name', 'like', "%{$term}%")
                        ->orWhere('lead_first_name', 'like', "%{$term}%")
                        ->orWhere('lead_email', 'like', "%{$term}%")
                        ->orWhere('lead_contact_no', 'like', "%{$term}%")
                        ->orWhereHas('attendees', fn ($a) => $a->where(fn ($n) => $n->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")));
                });
            })
            ->orderByDesc('reservation_datetime')
            ->paginate(25)
            ->withQueryString();

        return view('staff.reservations.index', [
            'reservations' => $reservations,
            'screenings' => Screening::orderByDesc('event_date')->limit(100)->get(),
            'filters' => $filters,
        ]);
    }

    public function show(Reservation $reservation): View
    {
        Gate::authorize('view', $reservation);

        $reservation->load(
            'screening',
            'reservationSeats.seat',
            'reservationSeats.attendee',
            'reservationSeats.attendance.checkedInBy',
            'payment.proofs',
        );

        return view('staff.reservations.show', compact('reservation'));
    }

    /** Approve a free reservation → e-ticket email. (Paid ones are approved by PayMongo.) */
    public function confirm(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('confirm', $reservation);

        $reservation->update(['status' => 'confirmed']);

        return back()->with(...$this->notice('Reservation approved.', $this->mailer->approved($reservation), 'e-ticket'));
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        $reservation->update(['status' => 'cancelled']);

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

        return back()->with('status', $paid ? 'PayMongo reports this booking as paid.' : 'PayMongo has no completed payment for this booking yet.');
    }

    /** @return array{0: string, 1: string} flash key + message */
    private function notice(string $done, bool $emailed, string $what): array
    {
        return $emailed
            ? ['status', $done.' The '.$what.' was emailed to the customer.']
            : ['warning', $done.' But the '.$what.' email could not be sent — use "Resend email" once mail is configured.'];
    }
}
