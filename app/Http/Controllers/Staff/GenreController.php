<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GenreController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Genre::class);

        return view('staff.genres.index', [
            'genres' => Genre::withCount('movies')->orderBy('genre_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Genre::class);

        Genre::create($request->validate([
            'genre_name' => ['required', 'string', 'max:50', Rule::unique('genres', 'genre_name')],
        ]));

        return redirect()->route('staff.genres.index')->with('status', 'Genre added.');
    }

    public function edit(Genre $genre): View
    {
        Gate::authorize('update', $genre);

        return view('staff.genres.edit', compact('genre'));
    }

    public function update(Request $request, Genre $genre): RedirectResponse
    {
        Gate::authorize('update', $genre);

        $genre->update($request->validate([
            'genre_name' => ['required', 'string', 'max:50', Rule::unique('genres', 'genre_name')->ignore($genre->genre_id, 'genre_id')],
        ]));

        return redirect()->route('staff.genres.index')->with('status', 'Genre updated.');
    }

    public function destroy(Genre $genre): RedirectResponse
    {
        Gate::authorize('delete', $genre);

        $genre->delete();

        return redirect()->route('staff.genres.index')->with('status', 'Genre deleted.');
    }
}
