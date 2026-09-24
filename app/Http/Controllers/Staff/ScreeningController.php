<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScreeningRequest;
use App\Models\Movie;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScreeningController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Screening::class);

        $screenings = Screening::with('movie', 'creator')
            ->withCount('reservations', 'reservationSeats')
            ->when($request->input('when') !== 'all', fn ($q) => $q->whereDate('event_date', '>=', today()))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->paginate(25)
            ->withQueryString();

        return view('staff.screenings.index', compact('screenings'));
    }

    public function create(): View
    {
        Gate::authorize('create', Screening::class);

        return view('staff.screenings.form', [
            'screening' => new Screening(['event_date' => today()]),
            'movies' => Movie::orderBy('title')->get(),
        ]);
    }

    public function store(ScreeningRequest $request): RedirectResponse
    {
        $screening = Screening::create([
            ...$request->screeningData(),
            'created_by' => $request->user()->user_id,
        ]);

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening created.');
    }

    public function show(Screening $screening): View
    {
        Gate::authorize('view', $screening);

        $screening->load([
            'movie', 'creator',
            'reservations' => fn ($q) => $q->withCount('reservationSeats')->with('payment')->orderByDesc('reservation_datetime'),
        ]);

        $checkedIn = $screening->reservationSeats()->whereHas('attendance')->count();

        return view('staff.screenings.show', compact('screening', 'checkedIn'));
    }

    public function edit(Screening $screening): View
    {
        Gate::authorize('update', $screening);

        return view('staff.screenings.form', [
            'screening' => $screening,
            'movies' => Movie::orderBy('title')->get(),
        ]);
    }

    public function update(ScreeningRequest $request, Screening $screening): RedirectResponse
    {
        $screening->update($request->screeningData());

        return redirect()->route('staff.screenings.show', $screening)->with('status', 'Screening updated.');
    }

    public function destroy(Screening $screening): RedirectResponse
    {
        Gate::authorize('delete', $screening);

        $screening->delete();

        return redirect()->route('staff.screenings.index')->with('status', 'Screening deleted.');
    }
}
