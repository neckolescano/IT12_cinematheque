<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Services\PayMongo\PayMongoException;
use App\Services\ReservationMailer;
use App\Services\ReservationPayments;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public reservation flow: pick seats -> declare one attendee per seat -> submit.
 * Every new reservation starts "pending":
 *   paid screenings  -> PayMongo checkout -> confirmed only when PayMongo reports it paid
 *   free screenings  -> confirmed when staff approve it
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

            // Kept in the order chosen: the first seat is the primary booker.
            $chosen = array_map('intval', $request->input('seats'));
            $selected = $seats->whereIn('seat_id', $chosen)
                ->whereNotIn('seat_id', $takenSeatIds)
                ->sortBy(fn ($seat) => array_search($seat->seat_id, $chosen, true))
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

    public function store(StoreReservationRequest $request, Screening $screening, ReservationMailer $mailer): RedirectResponse
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
                    // Pending until PayMongo confirms payment (paid) or staff approve it (free).
                    'status' => 'pending',
                    'reservation_datetime' => now(),
                    'lead_first_name' => $data['lead_first_name'],
                    'lead_middle_name' => $data['lead_middle_name'] ?? null,
                    'lead_last_name' => $data['lead_last_name'],
                    'lead_contact_no' => $data['lead_contact_no'],
                    'lead_email' => $data['lead_email'],
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
                        'pwd_id_no' => $attendee['pwd_id_no'] ?? null,
                        'pwd_indicator' => filled($attendee['pwd_id_no'] ?? null),
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

        // After the transaction: an email problem must never undo the booking.
        $emailed = $mailer->pending($reservation);
        $note = $emailed ? ' A copy was sent to '.$reservation->lead_email.'.'
            : ' We could not send the confirmation email, so please save your booking reference.';

        if ($screening->isPaid()) {
            // Straight on to payment: one less click for the customer.
            return redirect()->route('bookings.pay', $reservation)
                ->with('status', 'Reservation '.$reservation->booking_reference.' is held. Complete payment to receive your e-ticket.'.$note);
        }

        return redirect()->route('bookings.show', $reservation)
            ->with('booked', true)->with('status', 'Reservation '.$reservation->booking_reference.' received. Your e-ticket will be emailed once staff approve it.'.$note);
    }

    /** Send the customer to PayMongo's hosted checkout (reusing an open session). */
    public function pay(Reservation $reservation, ReservationPayments $payments): RedirectResponse
    {
        // Keep the "reservation held" message from store() if we end up on the booking page.
        session()->reflash();

        if (! $reservation->payment) {
            return redirect()->route('bookings.show', $reservation);
        }

        if (! $payments->isConfigured()) {
            return redirect()->route('bookings.show', $reservation)
                ->withErrors(['payment' => 'Online payment is not available right now. Please try again later or contact Cinematheque Centre Davao.']);
        }

        try {
            $url = $payments->checkoutUrl($reservation);
        } catch (PayMongoException $e) {
            report($e);

            return redirect()->route('bookings.show', $reservation)
                ->withErrors(['payment' => 'We could not open the payment page. Please try again in a moment.']);
        }

        return $url ? redirect()->away($url) : redirect()->route('bookings.show', $reservation);
    }

    /**
     * PayMongo's success_url. The redirect itself proves nothing, so we ask PayMongo's API
     * for the session's real status before telling the customer anything.
     */
    public function paymentReturn(Reservation $reservation, ReservationPayments $payments): RedirectResponse
    {
        $payment = $reservation->payment;
        if (! $payment) {
            return redirect()->route('bookings.show', $reservation);
        }

        try {
            $paid = $payments->sync($payment);
        } catch (PayMongoException $e) {
            report($e);
            $paid = false;
        }

        $back = redirect()->route('bookings.show', $reservation);

        if (! $paid) {
            return $back->with('warning', 'We have not received confirmation from PayMongo yet. If you completed the payment, it can take a minute. Use "Check payment status" below.');
        }

        // Paid, but after the booking expired or was cancelled: it stays cancelled.
        return $reservation->refresh()->status === 'confirmed'
            ? $back->with('booked', true)->with('status', 'Payment received. Your reservation is confirmed and your e-ticket has been emailed to '.$reservation->lead_email.'.')
            : $back->with('warning', 'We received your payment, but this booking was no longer active, so it could not be confirmed. Please contact Cinematheque Centre Davao about a refund and quote '.$reservation->booking_reference.'.');
    }

    /**
     * Find my booking: by booking reference, OR by the booker's email (upcoming bookings only).
     * The result is the ticket itself (bookings.ticket), not the booking-flow page.
     */
    public function lookup(Request $request): View|RedirectResponse
    {
        if (! $request->hasAny(['reference', 'email'])) {
            return view('public.bookings.lookup');
        }

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:20', 'required_without:email'],
            'email' => ['nullable', 'email', 'max:100', 'required_without:reference'],
        ], ['reference.required_without' => 'Enter your booking reference or your email.'], ['reference' => 'booking reference']);

        if (filled($data['reference'] ?? null)) {
            $reservation = Reservation::where('booking_reference', strtoupper(trim($data['reference'])))->first();

            return $reservation
                ? redirect()->route('bookings.ticket', $reservation)
                : back()->withInput()->withErrors(['reference' => 'No booking matches that reference. Check it and try again.']);
        }

        // By email: only bookings for screenings that haven't happened yet.
        $matches = Reservation::with('screening')
            ->whereRaw('LOWER(lead_email) = ?', [Str::lower(trim($data['email']))])
            ->whereHas('screening', fn ($q) => $q->whereDate('event_date', '>=', today()))
            ->get()
            ->sortBy(fn (Reservation $r) => $r->screening->event_date->toDateString().' '.$r->screening->start_time)
            ->values();

        if ($matches->isEmpty()) {
            return back()->withInput()->withErrors(['email' => 'No upcoming bookings for that email.']);
        }

        if ($matches->count() === 1) {
            return redirect()->route('bookings.ticket', $matches->first());
        }

        return view('public.bookings.lookup', ['results' => $matches]);
    }

    /** The ticket on its own — what "Find my booking" opens. */
    public function ticket(Reservation $reservation): View
    {
        $reservation->load('screening.movie', 'reservationSeats.seat', 'reservationSeats.attendee', 'payment');

        return view('public.bookings.ticket', [
            'reservation' => $reservation,
            'payment' => $reservation->payment,
            'canPay' => $reservation->payment && ! $reservation->payment->isPaid() && $reservation->status === 'pending',
        ]);
    }

    /** Booking summary + payment status / "Pay with PayMongo". */
    public function show(Request $request, Reservation $reservation, ReservationPayments $payments): View
    {
        $reservation->load('screening.movie', 'reservationSeats.seat', 'reservationSeats.attendee', 'payment');
        $payment = $reservation->payment;

        return view('public.bookings.show', [
            'reservation' => $reservation,
            'payment' => $payment,
            'canPay' => $payment && ! $payment->isPaid() && $reservation->status === 'pending',
            'payBy' => $reservation->status === 'pending' ? $reservation->paymentDeadline() : null,
            'paymentsEnabled' => $payments->isConfigured(),
            'paymentCancelled' => $request->query('payment') === 'cancelled',
        ]);
    }
}
