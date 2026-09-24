<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use App\Models\PaymentQrCode;
use App\Models\Reservation;
use App\Models\Screening;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('staff.dashboard', [
            'todayScreenings' => Screening::withCount('reservationSeats')
                ->whereDate('event_date', today())->orderBy('start_time')->get(),
            'upcomingCount' => Screening::whereDate('event_date', '>=', today())->count(),
            'pendingProofCount' => PaymentProof::where('status', 'pending')->count(),
            'pendingReservationCount' => Reservation::where('status', 'pending')->count(),
            'hasActiveQr' => PaymentQrCode::current() !== null,
        ]);
    }
}
