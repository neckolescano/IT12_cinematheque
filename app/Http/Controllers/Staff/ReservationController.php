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
            'view' => ['nullable', Rule::in(array_keys(self::VIEWS))],
            'screening_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Reservation::STATUSES)],
            'payment' => ['nullable', Rule::in(['paid', 'unpaid', 'free'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $counts = collect(self::VIEWS)->map(fn ($v, $key) => $this->applyView(Reservation::query(), $key)
            ->when($filters['screening_id'] ?? null, fn ($q, $id) => $q->where('screening_id', $id))->count());

        $reservations = $this->applyView(Reservation::with('screening', 'payment', 'reservationSeats.seat')->withCount('reservationSeats'), $filters['view'] ?? 'all')
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
            'counts' => $counts,
        ]);
    }

    /** Quick filters, named after what staff want to see rather than database fields. */
    public const VIEWS = [
        'all' => 'All',
        'approve' => 'To approve',
        'payment' => 'Awaiting payment',
        'approved' => 'Approved',
        'refund' => 'Refund due',
        'cancelled' => 'Cancelled',
    ];

    private function applyView($query, string $view)
    {
        return match ($view) {
            'approve' => $query->where('status', 'pending')->doesntHave('payment'),
            'payment' => $query->where('status', 'pending')->has('payment'),
            'approved' => $query->where('status', 'confirmed'),
            'refund' => $query->where('status', 'cancelled')->whereHas('payment', fn ($p) => $p->where('status', 'verified')),
            'cancelled' => $query->where('status', 'cancelled'),
            default => $query,
        };
    }

    public function show(Reservation $reservation): View
    {
        Gate::authorize('view', $reservation);

        $reservation->load(
            'screening',
            'reservationSeats.seat',
            'reservationSeats.attendee',
            'reservationSeats.attendance.checkedInBy',
            'payment',
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
