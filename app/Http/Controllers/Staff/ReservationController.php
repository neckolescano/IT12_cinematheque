<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Staff view of reservations: lookup, onsite verification, confirm/cancel. */
class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Reservation::class);

        $filters = $request->validate([
            'screening_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(Reservation::STATUSES)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $reservations = Reservation::with('screening', 'payment')
            ->withCount('reservationSeats')
            ->when($filters['screening_id'] ?? null, fn ($q, $id) => $q->where('screening_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('booking_reference', 'like', "%{$term}%")
                        ->orWhere('lead_last_name', 'like', "%{$term}%")
                        ->orWhere('lead_first_name', 'like', "%{$term}%")
                        ->orWhere('lead_contact_no', 'like', "%{$term}%");
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
            'payment.proofs.reviewer',
        );

        return view('staff.reservations.show', compact('reservation'));
    }

    public function confirm(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('confirm', $reservation);

        $reservation->update(['status' => 'confirmed']);

        return back()->with('status', 'Reservation confirmed.');
    }

    /**
     * Cancelling keeps every row (seats, attendees, payment, proofs) as history.
     * Note: the seats stay locked by UNIQUE(screening_id, seat_id) — see README.
     */
    public function cancel(Reservation $reservation): RedirectResponse
    {
        Gate::authorize('cancel', $reservation);

        $reservation->update(['status' => 'cancelled']);

        return back()->with('status', 'Reservation cancelled.');
    }
}
