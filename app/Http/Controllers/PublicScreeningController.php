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

    /** Films in the home banner: the soonest upcoming ones. */
    public const FEATURED = 6;

    /** With nothing upcoming, the banner shows this many recent screenings instead, one per program first. */
    public const FEATURED_PAST = 5;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Screening::TYPES)],
        ]);

        $screenings = Screening::with('movie.genres', 'movie.directors', 'program')
            ->published()
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
            'featured' => $this->featured($films, $filters),
            'total' => $screenings->count(),
            'filters' => $filters,
        ]);
    }

    /**
     * The home banner: the soonest upcoming films (one slide per film, by next screening date). When nothing is
     * upcoming: the most recent past screenings, one per program first, so the banner is never empty.
     */
    private function featured(Collection $films, array $filters): Collection
    {
        if ($films->isNotEmpty()) {
            return $films->take(self::FEATURED)->values();
        }
        if (array_filter($filters)) {
            return collect(); // a search with no results: no banner
        }

        $recent = Screening::with('movie.genres', 'movie.directors', 'program')
            ->published()
            ->withCount('heldSeats')
            ->whereDate('event_date', '<', today())
            ->orderByDesc('event_date')->orderByDesc('start_time')
            ->limit(60)->get();

        $picked = $recent->unique('program_id')->take(self::FEATURED_PAST);
        $picked = $picked->concat($recent->diff($picked))->take(self::FEATURED_PAST);

        return $picked->map(fn (Screening $s) => (object) [
            'key' => 'past-'.$s->screening_id,
            'movie' => $s->movie,
            'title' => $s->movie?->title ?? $s->event_title,
            'shows' => collect([$s]),
        ])->values();
    }

    public function show(Screening $screening): View
    {
        // Drafts (the screening or its film) don't exist for customers.
        abort_if($screening->isDraft(), 404);

        return $this->page($screening);
    }

    /**
     * The film page for a screening. Staff "Review" renders the same page with $preview set (a bar with
     * Back to edit / Publish), and then the draft itself and its draft showtimes are included.
     *
     * @param  array{back: string, publish: string, label: string}|null  $preview
     */
    public function page(Screening $screening, ?array $preview = null): View
    {
        $screening->load('movie.actors', 'movie.directors', 'movie.genres');

        // Every upcoming showtime of the same film (this one included), for the showtimes board.
        $showtimes = Screening::where('movie_id', $screening->movie_id)
            ->when(! $preview, fn ($q) => $q->where('status', 'published'))
            ->withCount('heldSeats')
            ->where(fn ($q) => $q->whereDate('event_date', '>=', today())->orWhere('screening_id', $screening->getKey()))
            ->orderBy('event_date')->orderBy('start_time')
            ->limit(60)->get();

        if ($preview) {
            // The home page card for this film, built the way index() builds it.
            $upcoming = $showtimes->filter(fn ($s) => $s->event_date->gte(today()))->values();
            $preview['film'] = (object) [
                'key' => 'film-'.$screening->movie_id,
                'movie' => $screening->movie,
                'title' => $screening->movie->title,
                'shows' => $upcoming->isNotEmpty() ? $upcoming : $showtimes->values(),
            ];
        }

        return view('public.screenings.show', [
            'screening' => $screening,
            'showtimes' => $showtimes,
            'preview' => $preview,
        ]);
    }
}
