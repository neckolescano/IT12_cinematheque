<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /* ---------- Step 2: date & time ---------- */
    public function showtimes(Request $request, Movie $movie)
    {
        abort_unless($movie->status === 'now_showing', 404);
        Booking::releaseExpired();

        $showtimes = $movie->showtimes()
            ->where('starts_at', '>=', now())
            ->with('cinema')
            ->withCount('bookingSeats')
            ->orderBy('starts_at')
            ->get();

        $byDate = $showtimes->groupBy(fn ($s) => $s->starts_at->toDateString());
        $selectedDate = $request->query('date');
        if (!$selectedDate || !$byDate->has($selectedDate)) {
            $selectedDate = $byDate->keys()->first();
        }

        return view('booking.showtimes', [
            'movie' => $movie,
            'dates' => $byDate->keys(),
            'selectedDate' => $selectedDate,
            'times' => $byDate->get($selectedDate, collect()),
        ]);
    }

    /* ---------- Step 3: seats ---------- */
    public function seats(Showtime $showtime)
    {
        abort_if($showtime->starts_at->isPast(), 404);
        Booking::releaseExpired();
        $showtime->load('movie', 'cinema');

        $seats = $showtime->bookingSeats()->with('booking:id,status')->get();

        return view('booking.seats', [
            'showtime' => $showtime,
            'taken' => $seats->filter(fn ($s) => $s->booking->status === 'confirmed')->pluck('seat_label')->all(),
            'locked' => $seats->filter(fn ($s) => $s->booking->status === 'held')->pluck('seat_label')->all(),
        ]);
    }

    /** Lock the chosen seats and create a "held" booking. */
    public function hold(Request $request, Showtime $showtime)
    {
        abort_if($showtime->starts_at->isPast(), 404);

        $max = config('cinema.max_tickets');
        $data = $request->validate([
            'seats' => ['required', 'array', 'min:1', "max:$max"],
            'seats.*' => ['string', 'distinct'],
        ]);

        $showtime->load('cinema');
        $seats = array_map('strtoupper', $data['seats']);

        foreach ($seats as $label) {
            abort_unless($showtime->cinema->isValidSeat($label), 422);
        }

        Booking::releaseExpired();

        try {
            $booking = DB::transaction(function () use ($showtime, $seats) {
                $booking = Booking::create([
                    'reference' => Booking::newReference(),
                    'showtime_id' => $showtime->id,
                    'status' => 'held',
                    'payment_status' => 'free',
                    'total_amount' => count($seats) * $showtime->cinema->ticket_price,
                    'expires_at' => now()->addMinutes(config('cinema.hold_minutes')),
                ]);

                foreach ($seats as $label) {
                    BookingSeat::create([
                        'booking_id' => $booking->id,
                        'showtime_id' => $showtime->id,
                        'seat_label' => $label,
                    ]);
                }

                return $booking;
            });
        } catch (QueryException $e) {
            // 23000 = MySQL/SQLite unique violation, 23505 = PostgreSQL
            if (!in_array((string) $e->getCode(), ['23000', '23505'], true)) {
                throw $e;
            }

            return redirect()->route('booking.seats', $showtime)
                ->withErrors(['seats' => 'One or more of those seats were just taken. Please pick different seats.']);
        }

        return redirect()->route('booking.checkout', $booking);
    }

    /* ---------- Step 4: details (+ QR if the cinema charges) ---------- */
    public function checkout(Booking $booking)
    {
        Booking::releaseExpired();
        $booking->refresh();

        if ($booking->status === 'confirmed') {
            return redirect()->route('booking.show', $booking);
        }
        if ($booking->status !== 'held') {
            return $this->holdExpired($booking);
        }

        $booking->load('showtime.movie', 'showtime.cinema', 'seats');

        return view('booking.checkout', ['booking' => $booking]);
    }

    public function confirm(Request $request, Booking $booking)
    {
        Booking::releaseExpired();
        $booking->refresh();

        if ($booking->status !== 'held') {
            return $this->holdExpired($booking);
        }

        $charges = $booking->showtime->cinema->chargesCustomers();

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'email', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'paid' => $charges ? ['accepted'] : ['nullable'],
        ], [
            'paid.accepted' => 'Please scan the QR code, pay, then tick the box to confirm.',
        ]);

        $booking->update([
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'status' => 'confirmed',
            'payment_status' => $charges ? 'unverified' : 'free',
            'expires_at' => null,
        ]);

        return redirect()->route('booking.show', $booking);
    }

    /** "Back" (back=1) returns to seat selection, "Cancel Booking" returns to the movie list. */
    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->status === 'held') {
            BookingSeat::where('booking_id', $booking->id)->delete();
            $booking->update(['status' => 'cancelled', 'expires_at' => null]);
        }

        return $request->boolean('back')
            ? redirect()->route('booking.seats', $booking->showtime_id)
            : redirect()->route('movies.index')->with('status', 'Booking cancelled.');
    }

    /* ---------- Step 5: confirmation ---------- */
    public function show(Booking $booking)
    {
        if ($booking->status !== 'confirmed') {
            return redirect()->route('movies.index');
        }

        $booking->load('showtime.movie', 'showtime.cinema', 'seats');
        $movie = $booking->showtime->movie;

        $similar = Movie::nowShowing()
            ->where('id', '!=', $movie->id)
            ->orderByRaw('genre = ? DESC', [$movie->genre])
            ->orderByDesc('rating')
            ->limit(4)
            ->get();

        return view('booking.confirmation', compact('booking', 'movie', 'similar'));
    }

    public function tickets(Booking $booking)
    {
        abort_unless($booking->status === 'confirmed', 404);
        $booking->load('showtime.movie', 'showtime.cinema', 'seats');

        return view('booking.tickets', ['booking' => $booking]);
    }

    private function holdExpired(Booking $booking)
    {
        return redirect()->route('booking.seats', $booking->showtime_id)
            ->withErrors(['seats' => 'Your seat hold expired. Please choose your seats again.']);
    }
}
