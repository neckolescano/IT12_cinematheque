<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Reservation;
use App\Models\ReservationSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Onsite admission from the screening's attendee checklist.
 * An Attendance row exists only once staff confirm the person actually arrived —
 * a reservation alone never counts as attendance, so no-shows stay visible.
 *
 * Each action answers JSON (the checklist updates in place, no page reload) or a
 * normal redirect when JavaScript is off.
 */
class AttendanceController extends Controller
{
    public function store(Request $request, ReservationSeat $reservationSeat): JsonResponse|RedirectResponse
    {
        Gate::authorize('checkIn', [Attendance::class, $reservationSeat]);

        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $reservationSeat->attendance()->create([
            'remarks' => $data['remarks'] ?? null,
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->user_id,
        ]);

        return $this->respond($request, $reservationSeat->reservation, 'Seat '.$reservationSeat->seat->seat_label.' admitted.');
    }

    /**
     * "Admit all" for one booking: staff review the list, untick anyone who didn't come, and
     * confirm. Only the ticked seats are admitted (each still passes the per-seat checkIn
     * rule); unticked people stay unadmitted and count as no-shows after the screening.
     */
    public function storeGroup(Request $request, Reservation $reservation): JsonResponse|RedirectResponse
    {
        $data = $request->validate(
            ['seats' => ['required', 'array', 'min:1'], 'seats.*' => ['integer']],
            ['seats.required' => 'Select at least one person to admit.'],
        );

        $admitted = $reservation->reservationSeats()->with('seat', 'attendance', 'reservation')
            ->whereIn('reservation_seat_id', $data['seats'])->get()
            ->filter(fn (ReservationSeat $rs) => Gate::allows('checkIn', [Attendance::class, $rs]))
            ->each(fn (ReservationSeat $rs) => $rs->attendance()->create([
                'checked_in_at' => now(),
                'checked_in_by' => $request->user()->user_id,
            ]));

        if ($admitted->isEmpty()) {
            $message = 'No one was admitted. The selected people are already in, or the booking is not approved.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('warning', $message);
        }

        return $this->respond($request, $reservation, $admitted->count().' admitted: '.$admitted->map(fn ($rs) => $rs->seat->seat_label)->join(', ').'.');
    }

    public function update(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $attendance);

        $attendance->update($request->validate(['remarks' => ['nullable', 'string', 'max:2000']]));

        return $this->respond($request, $attendance->reservationSeat->reservation, 'Remarks saved.');
    }

    /** Undo an admission recorded by mistake. */
    public function destroy(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $attendance);

        $seat = $attendance->reservationSeat;
        $attendance->delete();

        return $this->respond($request, $seat->reservation, 'Admission for seat '.$seat->seat->seat_label.' undone.');
    }

    /** JSON: the reservation's re-rendered party rows + the screening's admitted count; else a redirect. */
    private function respond(Request $request, Reservation $reservation, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $reservation->load('screening', 'payment', 'reservationSeats.seat', 'reservationSeats.attendee', 'reservationSeats.attendance.checkedInBy');

            return response()->json([
                'message' => $message,
                'party' => view('staff.screenings._party', ['r' => $reservation])->render(),
                'admitted' => ReservationSeat::where('screening_id', $reservation->screening_id)->whereHas('attendance')->count(),
            ]);
        }

        return back()->with('status', $message);
    }
}
