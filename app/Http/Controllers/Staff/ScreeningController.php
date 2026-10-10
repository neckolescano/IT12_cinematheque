<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicScreeningController;
use App\Http\Requests\ScreeningRequest;
use App\Models\Movie;
use App\Models\Program;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Screening management. The screening page is the staff "workspace": details, numbers,
 * the complete attendee checklist and admission all live on one screen.
 * Create/edit use one dedicated page (enough fields to deserve focus), opened from the
 * dashboard and from a film in the catalog ("Schedule").
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
                'reservations as pending_count' => fn ($q) => $q->where('status', 'awaiting_payment'),
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
        ]);
    }

    /** The dedicated New screening page; ?movie= (from the film catalog's "Schedule") pre-fills the film. */
    public function create(Request $request): View
    {
        Gate::authorize('create', Screening::class);

        $screening = new Screening(['event_date' => today(), 'type' => 'free', 'status' => 'draft']);
        if ($movie = Movie::with('genres', 'directors', 'actors', 'programs')->find($request->integer('movie'))) {
            $screening->setRelation('movie', $movie);
        }
        // Pre-fill the program: ?program=, or the film's only program tag.
        if ($program = Program::find($request->integer('program')) ?? ($movie?->programs->count() === 1 ? $movie->programs->first() : null)) {
            $screening->program_id = $program->program_id;
            $screening->setRelation('program', $program);
        }

        return view('staff.screenings.form', [
            'screening' => $screening,
            'movies' => $this->catalog(),
            'programs' => $this->programs($screening),
        ]);
    }

    /** Creates one screening, or a batch (daily/weekly repeats and extra showtimes) sharing everything but the time. */
    public function store(ScreeningRequest $request): RedirectResponse
    {
        $created = DB::transaction(function () use ($request) {
            $shared = [
                ...$request->screeningData(),
                'status' => $request->intent() === 'publish' ? 'published' : 'draft',
                'movie_id' => $this->filmFor($request),
                'created_by' => $request->user()->user_id,
            ];

            return collect($request->showtimes())->map(fn ($t) => Screening::create([
                ...$shared, 'event_date' => $t[0], 'start_time' => $t[1], 'end_time' => $t[2],
            ]));
        });

        if ($created->count() === 1 || $request->intent() === 'review') {
            return $this->afterSave($request, $created->first(), $created->count() === 1 ? 'Screening created' : $created->count().' screenings created');
        }

        $movie = $created->first()->movie;
        if ($request->intent() === 'publish') {
            $movie->update(['status' => 'published']);
        }

        return redirect()->route('staff.movies.show', $movie)->with('status', $created->count().' screenings created'
            .($request->intent() === 'publish' ? ' and published.' : ' as drafts. Customers can’t see them yet.'));
    }

    /** The workspace: one screening → one complete list of reserved moviegoers. */
    public function show(Screening $screening): View
    {
        Gate::authorize('view', $screening);

        $screening->load('movie', 'program', 'creator');

        // One entry per reservation ("party"); active ones first, in seat order.
        $parties = $screening->reservations()
            ->with('payment', 'reservationSeats.seat', 'reservationSeats.attendee', 'reservationSeats.attendance.checkedInBy')
            ->get()
            ->each(fn ($r) => $r->setRelation('screening', $screening))
            ->sortBy(fn ($r) => [$r->status === 'cancelled' ? 1 : 0, $r->reservationSeats->min('seat_id')])
            ->values();
        $rows = $parties->flatMap->reservationSeats;
        $active = $rows->filter(fn ($rs) => $rs->released_at === null);
        $reservations = $parties;

        return view('staff.screenings.show', [
            'screening' => $screening,
            'parties' => $parties,
            'stats' => [
                'reserved' => $active->count(),
                'admitted' => $rows->filter(fn ($rs) => $rs->attendance)->count(),
                'available' => $screening->availableSeatCount(),
                'bookings' => $reservations->where('status', '!=', 'cancelled')->count(),
                'pending' => $reservations->where('status', 'awaiting_payment')->count(),
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
            'movies' => $this->catalog(),
            'programs' => $this->programs($screening),
        ]);
    }

    public function update(ScreeningRequest $request, Screening $screening): RedirectResponse
    {
        DB::transaction(fn () => $screening->update([
            ...$request->screeningData(),
            // Review keeps the current state; Save as draft takes a published screening off the site.
            'status' => match ($request->intent()) {
                'publish' => 'published',
                'draft' => 'draft',
                default => $screening->status,
            },
            'movie_id' => $this->filmFor($request),
        ]));

        Program::pruneUnused(); // the old program tag, if nothing else uses it

        return $this->afterSave($request, $screening, 'Screening saved');
    }

    /** Review: the customer film page for this screening, with Back to edit and Publish. */
    public function preview(Screening $screening, PublicScreeningController $pages): View
    {
        Gate::authorize('view', $screening);

        return $pages->page($screening, [
            'label' => $screening->isDraft() ? 'This screening is a draft. Customers can’t see it until you publish it.' : 'This screening is live on the customer site.',
            'back' => route('staff.screenings.edit', $screening),
            'publish' => $screening->isDraft() ? route('staff.screenings.publish', $screening) : null,
        ]);
    }

    /** Publish the screening, and its film if that is still a draft (customers need both). */
    public function publish(Screening $screening): RedirectResponse
    {
        Gate::authorize('update', $screening);

        DB::transaction(function () use ($screening) {
            $screening->update(['status' => 'published']);
            $screening->movie->update(['status' => 'published']);
        });

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening published. Customers can now book it.');
    }

    private function afterSave(ScreeningRequest $request, Screening $screening, string $done): RedirectResponse
    {
        $screening->refresh();
        if ($request->intent() === 'publish') {
            $screening->movie->update(['status' => 'published']);
        }

        return match ($request->intent()) {
            'review' => redirect()->route('staff.screenings.preview', $screening),
            'publish' => redirect()->route('staff.screenings.show', $screening)->with('status', $done.' and published.'),
            default => redirect()->route('staff.screenings.show', $screening)->with('status', $done.' as a draft. Customers can’t see it yet.'),
        };
    }

    public function destroy(Screening $screening): RedirectResponse
    {
        Gate::authorize('delete', $screening);

        $screening->delete();

        return redirect()->route('staff.screenings.index')->with('status', 'Screening deleted.');
    }

    /**
     * The film typed on the screening form, saved to the catalog: an existing film with the same
     * title is reused (so a re-screening keeps its poster and credits), otherwise it is created.
     * Typed values are filled in; blank fields never erase what the catalog already knows.
     */
    private function filmFor(ScreeningRequest $request): int
    {
        $film = $request->filmData();

        // A new film starts as a draft unless the screening is published with it.
        $movie = $request->existingFilm($film['title'])
            ?? new Movie(['title' => $film['title'], 'status' => $request->intent() === 'publish' ? 'published' : 'draft']);
        foreach (['runtime_minutes', 'rating', 'release_year', 'synopsis'] as $field) {
            if (filled($film[$field])) {
                $movie->{$field} = $film[$field];
            }
        }
        $movie->save();

        if ($film['genres'] || filled($film['directors']) || filled($film['actors'])) {
            $movie->load('genres', 'directors', 'actors');
            $movie->syncDetails(
                $film['genres'] ?: $movie->genres->pluck('genre_name')->all(),
                filled($film['directors']) ? $film['directors'] : $movie->directorNames(),
                filled($film['actors']) ? $film['actors'] : $movie->castNames(),
            );
        }

        // The film carries the screening's program tag.
        $movie->programs()->syncWithoutDetaching([$request->programId()]);

        return $movie->movie_id;
    }

    /** Program tag suggestions: the film's own tags first, then every other tag. */
    private function programs(Screening $screening)
    {
        $own = $screening->movie?->programs?->pluck('name') ?? collect();

        return $own->merge(Program::orderBy('name')->pluck('name'))->unique()->values();
    }

    /** Catalog films with their details, so picking a known title fills the form in. */
    private function catalog()
    {
        return Movie::with('genres', 'directors', 'actors', 'programs')->orderBy('title')->get();
    }

}
