<?php

namespace App\Http\Controllers;

use App\Models\Screening;
use App\Support\ShowcaseTags;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public (no login) screening listing. */
class PublicScreeningController extends Controller
{
    /** Films whose next screening is within this many days (today included) are "Now Showing". */
    public const NOW_SHOWING_DAYS = 7;

    /** ...within this many days are "Advance Booking"; anything later is "Coming Soon". */
    public const ADVANCE_BOOKING_DAYS = 30;

    /** Films in the home spotlight carousel. */
    public const FEATURED = 5;

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

        // One poster card per film (special programmes without a film group by title), placed in a
        // tab by its next screening: the coming week, then the next month, then later.
        $films = $screenings
            ->groupBy(fn (Screening $s) => $s->movie_id ? 'film-'.$s->movie_id : 'event-'.Str::slug($s->event_title))
            ->map(fn (Collection $shows, string $key) => (object) [
                'key' => $key,
                'movie' => $shows->first()->movie,
                'title' => $shows->first()->movie?->title ?? $shows->first()->event_title,
                'shows' => $shows->values(),
            ])
            ->values();

        $tabs = $films->groupBy(fn ($film) => match (true) {
            $film->shows->first()->event_date->lt(today()->addDays(self::NOW_SHOWING_DAYS)) => 'now',
            $film->shows->first()->event_date->lt(today()->addDays(self::ADVANCE_BOOKING_DAYS)) => 'advance',
            default => 'soon',
        });
        // Special Screenings cuts across the dates: programmes without a film, 35mm prints, Q&As, limited runs.
        $tabs['special'] = $films->filter(fn ($film) => ! $film->movie
            || $film->shows->contains(fn ($s) => ShowcaseTags::forTitle($s->event_title)));

        return view('public.screenings.index', [
            'tabs' => collect(['now' => 'Now Showing', 'advance' => 'Advance Booking', 'soon' => 'Coming Soon', 'special' => 'Special Screenings'])
                ->map(fn ($label, $key) => (object) ['key' => $key, 'label' => $label, 'films' => $tabs->get($key, collect())->values()]),
            // Spotlight: the soonest films, those with a real poster first (stable sort keeps date order).
            'featured' => $films->sortByDesc(fn ($film) => (bool) $film->movie?->poster_path)->take(self::FEATURED)->values(),
            'total' => $screenings->count(),
            'filters' => $filters,
        ]);
    }

    public function show(Screening $screening): View
    {
        $screening->load('movie.actors', 'movie.directors', 'movie.genres');

        // Every upcoming showtime of the same film (this one included), for the showtimes board.
        $showtimes = $screening->movie_id
            ? Screening::where('movie_id', $screening->movie_id)
                ->withCount('heldSeats')
                ->where(fn ($q) => $q->whereDate('event_date', '>=', today())->orWhere('screening_id', $screening->getKey()))
                ->orderBy('event_date')->orderBy('start_time')
                ->limit(60)->get()
            : collect([$screening->loadCount('heldSeats')]);

        return view('public.screenings.show', [
            'screening' => $screening,
            'showtimes' => $showtimes,
        ]);
    }
}
