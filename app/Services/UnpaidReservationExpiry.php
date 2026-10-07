<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

/**
 * Decision 5b: a booking for a paid screening holds its seats for
 * Reservation::PAYMENT_WINDOW_MINUTES. If PayMongo hasn't reported it paid by then, it is
 * cancelled (reason payment_expired) and its seats are released.
 *
 * No cron is needed: the ExpireUnpaidReservations middleware runs this on every web request,
 * so a seat is never shown, validated or booked against a stale hold. The
 * reservations:expire-unpaid command does the same for anyone running the scheduler.
 *
 * No email is sent on expiry; the booking page explains what happened.
 */
class UnpaidReservationExpiry
{
    /** @return int how many bookings expired */
    public function run(): int
    {
        $ids = Reservation::where('status', 'pending')
            ->where('reservation_datetime', '<=', now()->subMinutes(Reservation::PAYMENT_WINDOW_MINUTES))
            ->whereHas('payment', fn ($q) => $q->where('status', '!=', 'verified'))
            ->pluck('reservation_id');

        $expired = 0;
        foreach ($ids as $id) {
            $expired += (int) DB::transaction(function () use ($id) {
                // Same lock order as ReservationPayments::settle() (payment first), so a payment
                // being settled at this moment either wins completely or not at all.
                $payment = Payment::where('reservation_id', $id)->lockForUpdate()->first();
                if (! $payment || $payment->isPaid()) {
                    return false;
                }

                return Reservation::whereKey($id)->where('status', 'pending')->first()?->cancel('payment_expired') ?? false;
            });
        }

        return $expired;
    }
}
