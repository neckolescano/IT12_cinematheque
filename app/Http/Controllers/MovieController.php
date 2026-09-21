<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $query = Movie::query();

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }
        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }
        if ($request->filled('genre')) {
            $query->where('genre', $request->genre);
        }
        if ($request->filled('rating')) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        return view('movies.index', [
            'nowShowing' => (clone $query)->nowShowing()
                ->with('upcomingShowtimes.cinema')
                ->orderByDesc('rating')
                ->get(),
            'comingSoon' => (clone $query)->comingSoon()->orderBy('release_date')->get(),
            'languages' => Movie::query()->distinct()->orderBy('language')->pluck('language'),
            'genres' => Movie::query()->distinct()->orderBy('genre')->pluck('genre'),
        ]);
    }
}
