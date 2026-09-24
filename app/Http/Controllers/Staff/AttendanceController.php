<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ReservationSeat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Onsite admission. Independent of the reservation and payment flows:
 * an Attendance row exists only once staff confirm someone walked in.
 *
 * control_number is typed in by staff from the physical FDCP ticket —
 * this system never generates, increments or validates it against a sequence.
 */
class AttendanceController extends Controller
{
    public function store(Request $request, ReservationSeat $reservationSeat): RedirectResponse
    {
        Gate::authorize('checkIn', [Attendance::class, $reservationSeat]);

        $data = $request->validate([
            'control_number' => ['nullable', 'string', 'max:20', Rule::unique('attendances', 'control_number')],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $reservationSeat->attendance()->create([
            'control_number' => $data['control_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->user_id,
        ]);

        return back()->with('status', 'Seat '.$reservationSeat->seat->seat_label.' checked in.');
    }

    /** Record/correct the control number or remarks after check-in. */
    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        Gate::authorize('update', $attendance);

        $data = $request->validate([
            'control_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('attendances', 'control_number')->ignore($attendance->attendance_id, 'attendance_id'),
            ],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendance->update($data);

        return back()->with('status', 'Attendance record updated.');
    }
}
