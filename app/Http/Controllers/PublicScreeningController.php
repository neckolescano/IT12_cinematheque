<?php

namespace App\Http\Controllers;

use App\Models\Screening;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Public (no login) screening listing. */
class PublicScreeningController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Screening::TYPES)],
        ]);

        $screenings = Screening::with('movie.genres')
            ->withCount('reservationSeats')
            ->whereDate('event_date', '>=', today())
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('event_title', 'like', "%{$term}%")
                ->orWhereHas('movie', fn ($m) => $m->where('title', 'like', "%{$term}%"))))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(18)
            ->withQueryString();

        return view('public.screenings.index', [
            'screenings' => $screenings,
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
