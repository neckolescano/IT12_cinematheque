<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScreeningRequest;
use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use App\Services\ReservationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Screening management. The screening page is the staff "workspace": details, numbers,
 * the complete attendee checklist and admission all live on one screen.
 * Create/edit open in a side drawer (the create/edit pages remain as a no-JS fallback).
 */
class ScreeningController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Screening::class);

        $filters = $request->validate([
            'when' => ['nullable', Rule::in(['upcoming', 'past', 'all'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $when = $filters['when'] ?? 'upcoming';

        $screenings = Screening::with('movie')
            ->withCount([
                'reservationSeats as reserved_count' => fn ($q) => $q->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'cancelled')),
                'reservationSeats as admitted_count' => fn ($q) => $q->whereHas('attendance'),
                'reservations as pending_count' => fn ($q) => $q->where('status', 'pending'),
            ])
            ->when($when === 'upcoming', fn ($q) => $q->whereDate('event_date', '>=', today()))
            ->when($when === 'past', fn ($q) => $q->whereDate('event_date', '<', today()))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('event_title', 'like', "%{$term}%"))
            ->orderBy('event_date', $when === 'past' ? 'desc' : 'asc')
            ->orderBy('start_time')
            ->paginate(25)
            ->withQueryString();

        return view('staff.screenings.index', [
            'screenings' => $screenings,
            'when' => $when,
            'q' => $filters['q'] ?? '',
            'movies' => Movie::orderBy('title')->get(),
            'blank' => new Screening(['event_date' => today(), 'type' => 'free', 'total_seats' => 100]),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Screening::class);

        return view('staff.screenings.form', [
            'screening' => new Screening(['event_date' => today()]),
            'movies' => Movie::orderBy('title')->get(),
        ]);
    }

    public function store(ScreeningRequest $request): RedirectResponse
    {
        $screening = Screening::create([
            ...$request->screeningData(),
            'created_by' => $request->user()->user_id,
        ]);

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening created. It is now open for reservations.');
    }

    /** The workspace: one screening → one complete list of reserved moviegoers. */
    public function show(Screening $screening): View
    {
        Gate::authorize('view', $screening);

        $screening->load('movie', 'creator');

        $rows = $screening->reservationSeats()
            ->with('seat', 'attendee', 'attendance.checkedInBy', 'reservation.payment')
            ->get()
            ->each(fn ($rs) => $rs->setRelation('screening', $screening))
            ->sortBy(fn ($rs) => [$rs->reservation->status === 'cancelled' ? 1 : 0, $rs->seat_id])
            ->values();

        $active = $rows->filter(fn ($rs) => $rs->reservation->status !== 'cancelled');
        $reservations = $rows->pluck('reservation')->unique('reservation_id');

        return view('staff.screenings.show', [
            'screening' => $screening,
            'rows' => $rows,
            'movies' => Movie::orderBy('title')->get(),
            'stats' => [
                'reserved' => $active->count(),
                'admitted' => $rows->filter(fn ($rs) => $rs->attendance)->count(),
                'available' => $screening->availableSeatCount(),
                'bookings' => $reservations->where('status', '!=', 'cancelled')->count(),
                'pending' => $reservations->where('status', 'pending')->count(),
                'paid_total' => $reservations->filter(fn ($r) => $r->payment?->isPaid())->sum(fn ($r) => (float) $r->payment->amount),
            ],
            'isPast' => $screening->event_date->lt(today()),
        ]);
    }

    public function edit(Screening $screening): View
    {
        Gate::authorize('update', $screening);

        return view('staff.screenings.form', [
            'screening' => $screening,
            'movies' => Movie::orderBy('title')->get(),
        ]);
    }

    public function update(ScreeningRequest $request, Screening $screening): RedirectResponse
    {
        $screening->update($request->screeningData());

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening updated.');
    }

    public function destroy(Screening $screening): RedirectResponse
    {
        Gate::authorize('delete', $screening);

        $screening->delete();

        return redirect()->route('staff.screenings.index')->with('status', 'Screening deleted.');
    }

    /** Free screenings: approve every pending reservation at once and email the e-tickets. */
    public function approvePending(Screening $screening, ReservationMailer $mailer): RedirectResponse
    {
        Gate::authorize('update', $screening);
        abort_if($screening->isPaid(), 422, 'Paid reservations are approved by PayMongo when payment succeeds.');

        $pending = $screening->reservations()->where('status', 'pending')->get()
            ->filter(fn (Reservation $r) => Gate::allows('confirm', $r));

        $failed = 0;
        foreach ($pending as $reservation) {
            $reservation->update(['status' => 'confirmed']);
            $failed += $mailer->approved($reservation) ? 0 : 1;
        }

        $message = $pending->count().' '.str('reservation')->plural($pending->count()).' approved.';

        return $failed
            ? back()->with('warning', $message.' '.$failed.' e-ticket '.str('email')->plural($failed).' could not be sent — use "Resend email" on those bookings once mail is configured.')
            : back()->with('status', $message.($pending->isNotEmpty() ? ' E-tickets were emailed.' : ''));
    }
}
