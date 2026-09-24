<?php

namespace App\Http\Controllers;

use App\Models\Screening;
use Illuminate\View\View;

/** Public (no login) screening listing. */
class PublicScreeningController extends Controller
{
    public function index(): View
    {
        $screenings = Screening::with('movie')
            ->withCount('reservationSeats')
            ->whereDate('event_date', '>=', today())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(20);

        return view('public.screenings.index', compact('screenings'));
    }

    public function show(Screening $screening): View
    {
        $screening->load('movie.actors', 'movie.directors', 'movie.genres');

        return view('public.screenings.show', [
            'screening' => $screening,
            'available' => $screening->availableSeatCount(),
        ]);
    }
}
