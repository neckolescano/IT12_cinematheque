<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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

        return $this->respond($request, $reservationSeat, 'Seat '.$reservationSeat->seat->seat_label.' admitted.');
    }

    public function update(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $attendance);

        $attendance->update($request->validate(['remarks' => ['nullable', 'string', 'max:2000']]));

        return $this->respond($request, $attendance->reservationSeat, 'Remarks saved.');
    }

    /** Undo an admission recorded by mistake. */
    public function destroy(Request $request, Attendance $attendance): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $attendance);

        $seat = $attendance->reservationSeat;
        $attendance->delete();

        return $this->respond($request, $seat, 'Admission for seat '.$seat->seat->seat_label.' undone.');
    }

    private function respond(Request $request, ReservationSeat $rs, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $rs->load('seat', 'attendee', 'attendance.checkedInBy', 'reservation.payment', 'screening');

            return response()->json([
                'message' => $message,
                'row' => view('staff.screenings._attendee-row', ['rs' => $rs])->render(),
                'admitted' => $rs->screening->reservationSeats()->whereHas('attendance')->count(),
            ]);
        }

        return redirect()->to(url()->previous().'#seat-'.$rs->reservation_seat_id)->with('status', $message);
    }
}
