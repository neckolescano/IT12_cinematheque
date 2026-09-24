<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\PaymentQrCode;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public reservation flow: pick seats -> declare one attendee per seat -> submit.
 * Paid screenings continue to the Payment Screen (bookings.show).
 */
class BookingController extends Controller
{
    /**
     * Step 1 (no ?seats[]): seat picker.
     * Step 2 (?seats[]=..): lead contact + one attendee fieldset per selected seat.
     */
    public function create(Request $request, Screening $screening): View|RedirectResponse
    {
        if ($screening->event_date->lt(today())) {
            return redirect()->route('screenings.show', $screening)
                ->withErrors(['seat_ids' => 'This screening has already taken place.']);
        }

        $takenSeatIds = $screening->takenSeatIds();
        $seats = Seat::orderBy('section')->orderBy('seat_id')->get();

        $selected = collect();
        if ($request->filled('seats')) {
            $request->validate([
                'seats' => ['array', 'min:1', 'max:'.StoreReservationRequest::MAX_SEATS_PER_RESERVATION],
                'seats.*' => ['integer', 'distinct'],
            ]);

            $selected = $seats->whereIn('seat_id', array_map('intval', $request->input('seats')))
                ->whereNotIn('seat_id', $takenSeatIds)
                ->values();

            if ($selected->count() !== count($request->input('seats'))) {
                return redirect()->route('bookings.create', $screening)
                    ->withErrors(['seat_ids' => 'Some selected seats are no longer available.']);
            }
        }

        return view('public.bookings.create', [
            'screening' => $screening,
            'seats' => $seats,
            'takenSeatIds' => $takenSeatIds,
            'selected' => $selected,
            'available' => $screening->availableSeatCount(),
        ]);
    }

    public function store(StoreReservationRequest $request, Screening $screening): RedirectResponse
    {
        $data = $request->validated();
        $seatIds = array_map('intval', $data['seat_ids']);
        $leadSeatId = isset($data['lead_seat_id']) ? (int) $data['lead_seat_id'] : null;

        try {
            $reservation = DB::transaction(function () use ($screening, $data, $seatIds, $leadSeatId) {
                // Serialise concurrent bookings for the same screening (MySQL/Postgres).
                $screening = Screening::whereKey($screening->getKey())->lockForUpdate()->firstOrFail();

                if (count($seatIds) > $screening->availableSeatCount()) {
                    return null;
                }

                $reservation = Reservation::create([
                    'screening_id' => $screening->screening_id,
                    'booking_reference' => Reservation::generateBookingReference(),
                    // Free screenings need no payment step, so they are confirmed immediately.
                    'status' => $screening->isPaid() ? 'pending' : 'confirmed',
                    'reservation_datetime' => now(),
                    'lead_first_name' => $data['lead_first_name'],
                    'lead_middle_name' => $data['lead_middle_name'] ?? null,
                    'lead_last_name' => $data['lead_last_name'],
                    'lead_contact_no' => $data['lead_contact_no'],
                    'lead_email' => $data['lead_email'] ?? null,
                ]);

                foreach ($seatIds as $seatId) {
                    $reservationSeat = $reservation->reservationSeats()->create([
                        'screening_id' => $screening->screening_id,
                        'seat_id' => $seatId,
                    ]);

                    $attendee = $data['attendees'][$seatId];
                    $reservationSeat->attendee()->create([
                        'is_lead_reserver' => $leadSeatId === $seatId,
                        'first_name' => $attendee['first_name'],
                        'middle_name' => $attendee['middle_name'] ?? null,
                        'last_name' => $attendee['last_name'],
                        'age' => $attendee['age'] ?? null,
                        'sex' => $attendee['sex'] ?? null,
                        'company_school' => $attendee['company_school'] ?? null,
                        'contact_no' => $attendee['contact_no'] ?? null,
                        'email' => $attendee['email'] ?? null,
                        'senior_card_no' => $attendee['senior_card_no'] ?? null,
                        'pwd_indicator' => (bool) ($attendee['pwd_indicator'] ?? false),
                    ]);
                }

                if ($screening->isPaid()) {
                    // Business rule 11: payment starts pending; it is never assumed verified.
                    $reservation->payment()->create([
                        'amount' => round((float) $screening->price * count($seatIds), 2),
                        'status' => 'pending',
                    ]);
                }

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            // UNIQUE(screening_id, seat_id) caught a race with another booking.
            return back()->withInput()
                ->withErrors(['seat_ids' => 'One or more seats were just reserved by someone else. Please choose again.']);
        }

        if (! $reservation) {
            return back()->withInput()->withErrors(['seat_ids' => 'Not enough seats left for this screening.']);
        }

        return redirect()->route('bookings.show', $reservation)
            ->with('status', 'Reservation submitted. Keep your booking reference: '.$reservation->booking_reference);
    }

    public function lookup(Request $request): View|RedirectResponse
    {
        if ($request->filled('reference')) {
            $reservation = Reservation::where('booking_reference', strtoupper(trim($request->input('reference'))))->first();

            if ($reservation) {
                return redirect()->route('bookings.show', $reservation);
            }

            return back()->withInput()->withErrors(['reference' => 'No reservation found with that booking reference.']);
        }

        return view('public.bookings.lookup');
    }

    /** Booking summary + Payment Screen (QR, amount due, proof upload/history). */
    public function show(Reservation $reservation): View
    {
        $reservation->load('screening.movie', 'reservationSeats.seat', 'reservationSeats.attendee', 'payment.proofs');

        $payment = $reservation->payment;

        return view('public.bookings.show', [
            'reservation' => $reservation,
            'payment' => $payment,
            'qrCode' => $payment ? PaymentQrCode::current() : null,
            'canUploadProof' => $payment
                && $payment->status !== 'verified'
                && $reservation->status !== 'cancelled'
                && ! $payment->hasPendingProof(),
        ]);
    }
}
