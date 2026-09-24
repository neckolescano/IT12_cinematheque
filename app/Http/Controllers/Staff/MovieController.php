<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Actor;
use App\Models\Director;
use App\Models\Genre;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovieController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Movie::class);

        $movies = Movie::with('genres', 'directors')->withCount('screenings')->orderBy('title')->paginate(25);

        return view('staff.movies.index', compact('movies'));
    }

    public function create(): View
    {
        Gate::authorize('create', Movie::class);

        return view('staff.movies.form', ['movie' => new Movie(), ...$this->options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Movie::class);

        $data = $this->validated($request);

        DB::transaction(function () use ($data) {
            $movie = Movie::create($data['movie']);
            $this->syncCredits($movie, $data);
        });

        return redirect()->route('staff.movies.index')->with('status', 'Movie added.');
    }

    public function edit(Movie $movie): View
    {
        Gate::authorize('update', $movie);

        $movie->load('actors', 'directors', 'genres');

        return view('staff.movies.form', ['movie' => $movie, ...$this->options()]);
    }

    public function update(Request $request, Movie $movie): RedirectResponse
    {
        Gate::authorize('update', $movie);

        $data = $this->validated($request);

        DB::transaction(function () use ($movie, $data) {
            $movie->update($data['movie']);
            $this->syncCredits($movie, $data);
        });

        return redirect()->route('staff.movies.index')->with('status', 'Movie updated.');
    }

    public function destroy(Movie $movie): RedirectResponse
    {
        Gate::authorize('delete', $movie);

        // Pivot rows cascade; screenings.movie_id is set to null (nullOnDelete).
        $movie->delete();

        return redirect()->route('staff.movies.index')->with('status', 'Movie deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'runtime_minutes' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'rating' => ['nullable', 'string', 'max:10'],
            'release_year' => ['nullable', 'integer', 'min:1888', 'max:'.(now()->year + 5)],
            'synopsis' => ['nullable', 'string'],
            'actor_ids' => ['nullable', 'array'],
            'actor_ids.*' => ['integer', Rule::exists('actors', 'actor_id')],
            'director_ids' => ['nullable', 'array'],
            'director_ids.*' => ['integer', Rule::exists('directors', 'director_id')],
            'genre_ids' => ['nullable', 'array'],
            'genre_ids.*' => ['integer', Rule::exists('genres', 'genre_id')],
        ]);

        return [
            'movie' => collect($data)->only(['title', 'runtime_minutes', 'rating', 'release_year', 'synopsis'])->all(),
            'actor_ids' => $data['actor_ids'] ?? [],
            'director_ids' => $data['director_ids'] ?? [],
            'genre_ids' => $data['genre_ids'] ?? [],
        ];
    }

    private function syncCredits(Movie $movie, array $data): void
    {
        $movie->actors()->sync($data['actor_ids']);
        $movie->directors()->sync($data['director_ids']);
        $movie->genres()->sync($data['genre_ids']);
    }

    private function options(): array
    {
        return [
            'actors' => Actor::orderBy('last_name')->orderBy('first_name')->get(),
            'directors' => Director::orderBy('last_name')->orderBy('first_name')->get(),
            'genres' => Genre::orderBy('genre_name')->get(),
        ];
    }
}
