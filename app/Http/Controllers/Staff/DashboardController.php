<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Screening;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $withCounts = [
            'reservationSeats as reserved_count' => fn ($q) => $q->whereHas('reservation', fn ($r) => $r->where('status', '!=', 'cancelled')),
            'reservationSeats as admitted_count' => fn ($q) => $q->whereHas('attendance'),
            'reservations as pending_count' => fn ($q) => $q->where('status', 'pending'),
        ];

        return view('staff.dashboard', [
            'today' => Screening::withCount($withCounts)->whereDate('event_date', today())->orderBy('start_time')->get(),
            'upcoming' => Screening::withCount($withCounts)
                ->whereDate('event_date', '>', today())->whereDate('event_date', '<=', today()->addDays(14))
                ->orderBy('event_date')->orderBy('start_time')->limit(6)->get(),
            'stats' => [
                'upcoming' => Screening::whereDate('event_date', '>=', today())->count(),
                'awaiting_approval' => Reservation::where('status', 'pending')->doesntHave('payment')
                    ->whereHas('screening', fn ($q) => $q->whereDate('event_date', '>=', today()))->count(),
                'awaiting_payment' => Reservation::where('status', 'pending')->has('payment')
                    ->whereHas('screening', fn ($q) => $q->whereDate('event_date', '>=', today()))->count(),
                'paid_7d' => (float) Payment::where('status', 'verified')->where('paid_at', '>=', now()->subDays(7))->sum('amount'),
            ],
            'recent' => Reservation::with('screening', 'payment')->withCount('reservationSeats')
                ->orderByDesc('reservation_datetime')->limit(8)->get(),
            'paymentsEnabled' => filled(config('services.paymongo.secret_key')),
            'mailConfigured' => config('mail.default') !== 'smtp' || filled(config('mail.mailers.smtp.host')),
        ]);
    }
}
