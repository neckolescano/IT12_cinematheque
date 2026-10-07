<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Film catalog. Directors and cast are typed as names and genres picked from a fixed list;
 * Movie::syncDetails() stores them in the existing tables (no separate people/genre admin).
 */
class MovieController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Movie::class);

        $movies = Movie::with('genres', 'directors')->withCount('screenings')->orderBy('title')->paginate(50);

        return view('staff.movies.index', compact('movies'));
    }

    public function create(): View
    {
        Gate::authorize('create', Movie::class);

        return view('staff.movies.form', ['movie' => new Movie()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Movie::class);

        $data = $this->validated($request);

        $poster = $data['poster']?->store(Movie::POSTER_DIR, 'public');

        DB::transaction(function () use ($data, $poster) {
            $movie = Movie::create([...$data['movie'], 'poster_path' => $poster ?: null]);
            $movie->syncDetails($data['genres'], $data['directors'], $data['actors']);
        });

        return redirect()->route('staff.movies.index')->with('status', 'Film added.');
    }

    public function edit(Movie $movie): View
    {
        Gate::authorize('update', $movie);

        $movie->load('actors', 'directors', 'genres');

        return view('staff.movies.form', ['movie' => $movie]);
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
            $movie->update([...$data['movie'], 'poster_path' => $poster]);
            $movie->syncDetails($data['genres'], $data['directors'], $data['actors']);
        });

        if ($oldPoster && $oldPoster !== $poster) {
            Storage::disk('public')->delete($oldPoster);
        }

        return redirect()->route('staff.movies.index')->with('status', 'Film updated.');
    }

    public function destroy(Movie $movie): RedirectResponse
    {
        Gate::authorize('delete', $movie);

        // Pivot rows cascade; screenings.movie_id is set to null (nullOnDelete).
        $movie->delete();

        if ($movie->poster_path) {
            Storage::disk('public')->delete($movie->poster_path);
        }

        return redirect()->route('staff.movies.index')->with('status', 'Film deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            ...Movie::detailRules(),
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_poster' => ['nullable', 'boolean'],
            'curator_note' => ['nullable', 'string', 'max:200'],
            'trailer_url' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        return [
            'movie' => collect($data)->only(['title', 'runtime_minutes', 'rating', 'release_year', 'synopsis', 'curator_note', 'trailer_url'])->all(),
            'poster' => $request->file('poster'),
            'remove_poster' => $request->boolean('remove_poster'),
            'genres' => Movie::genreList($data),
            'directors' => $data['directors'] ?? null,
            'actors' => $data['actors'] ?? null,
        ];
    }
}
