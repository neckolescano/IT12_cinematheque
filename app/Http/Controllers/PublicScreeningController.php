<?php

namespace App\Http\Controllers;

use App\Models\Screening;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public (no login) screening listing. */
class PublicScreeningController extends Controller
{
    /** Screenings within this many days (today included) are "Now Showing". */
    public const NOW_SHOWING_DAYS = 7;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Screening::TYPES)],
        ]);

        $screenings = Screening::with('movie.genres')
            ->withCount('heldSeats')
            ->whereDate('event_date', '>=', today())
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('event_title', 'like', "%{$term}%")
                ->orWhereHas('movie', fn ($m) => $m->where('title', 'like', "%{$term}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(200) // one venue: a few dozen screenings at most, so no pagination
            ->get();

        // Now Showing = the coming week, as full cards; Upcoming = later dates, as a compact preview list.
        [$nowShowing, $upcoming] = $screenings->partition(
            fn (Screening $s) => $s->event_date->lte(today()->addDays(self::NOW_SHOWING_DAYS - 1))
        );

        return view('public.screenings.index', [
            'nowShowing' => $nowShowing->values(),
            'upcoming' => $upcoming->values(),
            'total' => $screenings->count(),
            'filters' => $filters,
        ]);
    }

    public function show(Screening $screening): View
    {
        $screening->load('movie.actors', 'movie.directors', 'movie.genres');

        // Other upcoming dates for the same film (shown as quick "showtime" links).
        $otherDates = $screening->movie_id
            ? Screening::where('movie_id', $screening->movie_id)
                ->whereKeyNot($screening->getKey())
                ->whereDate('event_date', '>=', today())
                ->orderBy('event_date')->orderBy('start_time')
                ->limit(6)->get()
            : collect();

        return view('public.screenings.show', [
            'screening' => $screening,
            'available' => $screening->availableSeatCount(),
            'otherDates' => $otherDates,
        ]);
    }
}
