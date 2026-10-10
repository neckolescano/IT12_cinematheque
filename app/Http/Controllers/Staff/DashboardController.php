<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Screening;
use Illuminate\View\View;

/**
 * "Today": what a staff member needs on arrival, in the order they act on it:
 * the door (today's screenings and admission), bookings waiting on staff, the week ahead.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $withCounts = [
            'heldSeats as reserved_count',
            'reservationSeats as admitted_count' => fn ($q) => $q->whereHas('attendance'),
            'reservations as pending_count' => fn ($q) => $q->where('status', 'awaiting_payment'),
        ];
        $bookingList = fn () => Reservation::with('screening', 'payment')->withCount('reservationSeats');

        // Paid but cancelled (staff cancel, or paid after expiry): money to return outside the system.
        $refunds = $bookingList()->where('status', 'cancelled')
            ->whereHas('payment', fn ($q) => $q->where('status', 'verified'))
            ->orderByDesc('cancelled_at')->limit(5)->get();

        return view('staff.dashboard', [
            'today' => Screening::with('movie')->withCount($withCounts)->whereDate('event_date', today())->orderBy('start_time')->get(),
            'week' => Screening::with('movie')->withCount($withCounts)
                ->whereDate('event_date', '>', today())->whereDate('event_date', '<=', today()->addDays(7))
                ->orderBy('event_date')->orderBy('start_time')->get(),
            'next' => Screening::whereDate('event_date', '>', today()->addDays(7))->orderBy('event_date')->orderBy('start_time')->first(),
            'refunds' => $refunds,
            // Not repeated: bookings already listed under "Needs your action" are left out.
            'recent' => $bookingList()->whereNotIn('reservation_id', $refunds->pluck('reservation_id'))
                ->orderByDesc('reservation_datetime')->limit(5)->get(),
            'summary' => [
                'upcoming' => Screening::whereDate('event_date', '>=', today())->count(),
                'awaiting_payment' => Reservation::where('status', 'awaiting_payment')->count(),
                'paid_7d' => (float) Payment::where('status', 'verified')->where('paid_at', '>=', now()->subDays(7))->sum('amount'),
            ],
            'paymentsEnabled' => filled(config('services.paymongo.secret_key')),
            'mailConfigured' => config('mail.default') !== 'smtp' || filled(config('mail.mailers.smtp.host')),
        ]);
    }
}
