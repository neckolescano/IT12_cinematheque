<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The top bar's one search box: bookings (reference, booker, attendee, email, phone),
 * screenings and films in one place, so staff don't have to pick a section first.
 * An exact booking reference goes straight to that booking.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', Reservation::class);

        $q = trim((string) $request->query('q', ''));

        if ($q !== '' && preg_match('/^CCD-[A-Z0-9]{8}$/i', $q)) {
            $exact = Reservation::where('booking_reference', Str::upper($q))->first();
            if ($exact) {
                return redirect()->route('staff.reservations.show', $exact);
            }
        }

        if (mb_strlen($q) < 2) {
            return view('staff.search', ['q' => $q, 'bookings' => collect(), 'screenings' => collect(), 'films' => collect()]);
        }

        $like = '%'.$q.'%';

        $bookings = Reservation::with('screening', 'payment')->withCount('reservationSeats')
            ->where(fn ($w) => $w
                ->where('booking_reference', 'like', $like)
                ->orWhere('lead_first_name', 'like', $like)
                ->orWhere('lead_last_name', 'like', $like)
                ->orWhereRaw("CONCAT_WS(' ', lead_first_name, lead_last_name) LIKE ?", [$like])
                ->orWhere('lead_email', 'like', $like)
                ->orWhere('lead_contact_no', 'like', '%'.preg_replace('/[\s\-()]/', '', $q).'%')
                ->orWhereHas('attendees', fn ($a) => $a
                    ->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) LIKE ?", [$like])))
            ->orderByDesc('reservation_datetime')
            ->limit(15)->get();

        $screenings = Screening::with('movie')
            ->withCount(['heldSeats as reserved_count'])
            ->where(fn ($w) => $w->where('event_title', 'like', $like)->orWhereHas('movie', fn ($m) => $m->where('title', 'like', $like)))
            ->orderByRaw('event_date < ? ', [today()->toDateString()]) // upcoming first
            ->orderBy('event_date')
            ->limit(10)->get();

        $films = Movie::withCount('screenings')
            ->where('title', 'like', $like)
            ->orderBy('title')->limit(8)->get();

        return view('staff.search', compact('q', 'bookings', 'screenings', 'films'));
    }
}
