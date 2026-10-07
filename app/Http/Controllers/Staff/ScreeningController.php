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
        ]);
    }

    /** The dedicated New screening page; ?movie= (from the film catalog's "Schedule") pre-fills the film. */
    public function create(Request $request): View
    {
        Gate::authorize('create', Screening::class);

        $screening = new Screening(['event_date' => today(), 'type' => 'free']);
        if ($movie = Movie::with('genres', 'directors', 'actors')->find($request->integer('movie'))) {
            $screening->setRelation('movie', $movie);
        }

        return view('staff.screenings.form', [
            'screening' => $screening,
            'movies' => $this->catalog(),
        ]);
    }

    public function store(ScreeningRequest $request): RedirectResponse
    {
        $screening = DB::transaction(fn () => Screening::create([
            ...$request->screeningData(),
            'movie_id' => $this->filmFor($request),
            'created_by' => $request->user()->user_id,
        ]));

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening created.');
    }

    /** The workspace: one screening → one complete list of reserved moviegoers. */
    public function show(Screening $screening): View
    {
        Gate::authorize('view', $screening);

        $screening->load('movie', 'creator');

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
            'movies' => $this->catalog(),
        ]);
    }

    public function update(ScreeningRequest $request, Screening $screening): RedirectResponse
    {
        DB::transaction(fn () => $screening->update([...$request->screeningData(), 'movie_id' => $this->filmFor($request)]));

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening updated.');
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
    private function filmFor(ScreeningRequest $request): ?int
    {
        $film = $request->filmData();
        if (! $film) {
            return null; // special programme: no single film
        }

        $movie = $request->existingFilm($film['title']) ?? new Movie(['title' => $film['title']]);
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

        return $movie->movie_id;
    }

    /** Catalog films with their details, so picking a known title fills the form in. */
    private function catalog()
    {
        return Movie::with('genres', 'directors', 'actors')->orderBy('title')->get();
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
            ? back()->with('warning', $message.' '.$failed.' e-ticket '.str('email')->plural($failed).' not sent. Use "Resend email" on those bookings.')
            : back()->with('status', $message.($pending->isNotEmpty() ? ' E-tickets were emailed.' : ''));
    }
}
