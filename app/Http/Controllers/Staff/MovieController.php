<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicScreeningController;
use App\Models\Movie;
use App\Models\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Film catalog: poster tiles grouped by program, each opening the film's panel (details, Edit movie,
 * Add screening schedule, and every screening of the film). Programs are free-text tags typed on the film
 * form (Program::fromTag); there is no Programs page, and tags nothing uses any more are dropped. Directors and cast are typed as names and
 * genres picked from a fixed list; Movie::syncDetails() stores them (no separate people/genre admin).
 *
 * The form saves with an intent: Save as draft (staff only), Review (the customer page as it will look)
 * or Publish.
 */
class MovieController extends Controller
{
    public const INTENTS = ['draft', 'review', 'publish'];

    /** ?program=<id> shows one program's films; ?program=none the films not in any program. */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Movie::class);

        $programs = Program::withCount('movies')->has('movies')->orderBy('name')->get();
        $filter = $request->query('program');
        $current = $filter === 'none' ? 'none' : $programs->firstWhere('program_id', (int) $filter);

        $movies = Movie::with('programs')->withCount('screenings')
            ->when($current instanceof Program, fn ($q) => $q->whereHas('programs', fn ($p) => $p->whereKey($current->program_id)))
            ->when($current === 'none', fn ($q) => $q->doesntHave('programs'))
            ->orderBy('title')->get();

        // All films: one section per program (a film in two programs shows in both), then the unfiled ones.
        $groups = $current
            ? collect([(object) ['program' => $current instanceof Program ? $current : null, 'movies' => $movies]])
            : $programs->map(fn (Program $p) => (object) ['program' => $p, 'movies' => $movies->filter(fn ($m) => $m->programs->contains('program_id', $p->program_id))->values()])
                ->push((object) ['program' => null, 'movies' => $movies->filter(fn ($m) => $m->programs->isEmpty())->values()])
                ->filter(fn ($g) => $g->movies->isNotEmpty())->values();

        return view('staff.movies.index', [
            'groups' => $groups,
            'programs' => $programs,
            'current' => $current,
            'total' => $movies->count(),
            'unfiled' => Movie::doesntHave('programs')->count(),
        ]);
    }

    /** The film panel. */
    public function show(Movie $movie): View
    {
        Gate::authorize('view', $movie);

        $movie->load('programs', 'genres', 'directors', 'actors');
        $screenings = $movie->screenings()->with('program')
            ->withCount([
                'reservationSeats as booked_count' => fn ($q) => $q->whereNull('released_at')
                    ->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'cancelled')),
                'reservationSeats as admitted_count' => fn ($q) => $q->whereHas('attendance'),
            ])
            ->orderByRaw('event_date < ? ', [today()->toDateString()]) // upcoming first
            ->orderBy('event_date')->orderBy('start_time')
            ->get();

        return view('staff.movies.show', compact('movie', 'screenings'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Movie::class);

        $movie = new Movie(['status' => 'draft']);
        $movie->setRelation('programs', Program::whereKey($request->integer('program'))->get());

        return view('staff.movies.form', ['movie' => $movie, 'programs' => $this->programs($movie)]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Movie::class);

        $data = $this->validated($request);

        $poster = $data['poster']?->store(Movie::POSTER_DIR, 'public');

        $movie = DB::transaction(function () use ($data, $poster) {
            $movie = Movie::create([
                ...$data['movie'],
                'poster_path' => $poster ?: null,
                'status' => $data['intent'] === 'publish' ? 'published' : 'draft',
            ]);
            $movie->syncDetails($data['genres'], $data['directors'], $data['actors']);
            $movie->programs()->sync($this->programIds($data['programs']));

            return $movie;
        });

        return $this->afterSave($movie, $data['intent'], 'Film added');
    }

    public function edit(Movie $movie): View
    {
        Gate::authorize('update', $movie);

        $movie->load('actors', 'directors', 'genres', 'programs');

        return view('staff.movies.form', ['movie' => $movie, 'programs' => $this->programs($movie)]);
    }

    public function update(Request $request, Movie $movie): RedirectResponse
    {
        Gate::authorize('update', $movie);

        $data = $this->validated($request);

        $oldPoster = $movie->poster_path;
        $poster = match (true) {
            (bool) $data['poster'] => $data['poster']->store(Movie::POSTER_DIR, 'public'),
            $data['remove_poster'] => null,
            default => $oldPoster,
        };

        DB::transaction(function () use ($movie, $data, $poster) {
            $movie->update([
                ...$data['movie'],
                'poster_path' => $poster,
                // Review keeps the current state; Save as draft takes a published film off the site.
                'status' => match ($data['intent']) {
                    'publish' => 'published',
                    'draft' => 'draft',
                    default => $movie->status,
                },
            ]);
            $movie->syncDetails($data['genres'], $data['directors'], $data['actors']);
            // A program the film is screened under stays linked even if its tag was removed.
            $movie->programs()->sync(array_unique([...$this->programIds($data['programs']), ...$movie->screenings()->distinct()->pluck('program_id')->all()]));
            Program::pruneUnused();
        });

        if ($oldPoster && $oldPoster !== $poster) {
            Storage::disk('public')->delete($oldPoster);
        }

        return $this->afterSave($movie, $data['intent'], 'Film saved');
    }

    /** Review: the film page as customers will see it, through the film's next (or latest) screening. */
    public function preview(Movie $movie, PublicScreeningController $pages): View|RedirectResponse
    {
        Gate::authorize('view', $movie);

        $screening = $movie->screenings()->whereDate('event_date', '>=', today())->orderBy('event_date')->orderBy('start_time')->first()
            ?? $movie->screenings()->orderByDesc('event_date')->first();

        if (! $screening) {
            return redirect()->route('staff.movies.show', $movie)
                ->with('warning', 'Customers see a film through its screenings. Add a screening schedule to preview the film page.');
        }

        return $pages->page($screening, [
            'label' => $movie->isDraft() ? 'This film is a draft. Customers can’t see it until you publish it.' : 'This film is live on the customer site.',
            'back' => route('staff.movies.edit', $movie),
            'publish' => $movie->isDraft() ? route('staff.movies.publish', $movie) : null,
        ]);
    }

    public function publish(Movie $movie): RedirectResponse
    {
        Gate::authorize('update', $movie);

        $movie->update(['status' => 'published']);

        return redirect()->route('staff.movies.show', $movie)->with('status', 'Film published. Its published screenings now show to customers.');
    }

    public function destroy(Movie $movie): RedirectResponse
    {
        Gate::authorize('delete', $movie);

        // Every screening needs its film (and its bookings and report rows point at it).
        if ($movie->screenings()->exists()) {
            return back()->withErrors(['movie' => 'This film has screenings, so it can’t be deleted. Save it as a draft to hide it from customers.']);
        }

        // Pivot rows cascade.
        $movie->delete();

        if ($movie->poster_path) {
            Storage::disk('public')->delete($movie->poster_path);
        }

        return redirect()->route('staff.movies.index')->with('status', 'Film deleted.');
    }

    private function afterSave(Movie $movie, string $intent, string $done): RedirectResponse
    {
        return match ($intent) {
            'review' => redirect()->route('staff.movies.preview', $movie),
            'publish' => redirect()->route('staff.movies.show', $movie)->with('status', $done.' and published.'),
            default => redirect()->route('staff.movies.show', $movie)->with('status', $done.' as a draft. Customers can’t see it yet.'),
        };
    }

    /** Program names for the tag suggestions. */
    private function programs(Movie $movie)
    {
        return Program::orderBy('name')->pluck('name');
    }

    /** Typed tags → program ids (existing ones matched ignoring case and spacing, new ones created). */
    private function programIds(array $names): array
    {
        return collect($names)->map(fn ($n) => Program::clean($n))->filter()
            ->map(fn ($n) => Program::fromTag($n)->program_id)->unique()->values()->all();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'intent' => ['nullable', Rule::in(self::INTENTS)],
            'title' => ['required', 'string', 'max:150'],
            'programs' => ['nullable', 'array', 'max:10'],
            'programs.*' => ['nullable', 'string', 'max:100'],
            ...Movie::detailRules(),
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_poster' => ['nullable', 'boolean'],
            'logline' => ['nullable', 'string', 'max:200'],
            'curator_note' => ['nullable', 'string', 'max:200'],
            'trailer_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        return [
            'intent' => $data['intent'] ?? 'publish',
            'movie' => collect($data)->only(['title', 'runtime_minutes', 'rating', 'release_year', 'synopsis', 'logline', 'curator_note', 'trailer_url'])->all(),
            'programs' => $data['programs'] ?? [],
            'poster' => $request->file('poster'),
            'remove_poster' => $request->boolean('remove_poster'),
            'genres' => Movie::genreList($data),
            'directors' => $data['directors'] ?? null,
            'actors' => $data['actors'] ?? null,
        ];
    }
}
