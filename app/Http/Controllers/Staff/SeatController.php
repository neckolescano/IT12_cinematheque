<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Seat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SeatController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Seat::class);

        return view('staff.seats.index', [
            'seats' => Seat::withCount('reservationSeats')->orderBy('section')->orderBy('seat_id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Seat::class);

        $data = $request->validate([
            'seat_label' => ['required', 'string', 'max:10', Rule::unique('seats', 'seat_label')],
            'section' => ['nullable', 'string', 'max:30'],
        ]);
        $data['seat_label'] = strtoupper($data['seat_label']);

        Seat::create($data);

        return redirect()->route('staff.seats.index')->with('status', 'Seat added.');
    }

    public function destroy(Seat $seat): RedirectResponse
    {
        Gate::authorize('delete', $seat);

        $seat->delete();

        return redirect()->route('staff.seats.index')->with('status', 'Seat removed.');
    }
}
