<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PayMongo\PayMongoClient;
use App\Services\PayMongo\PayMongoException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Connects a reservation's Payment row to a PayMongo Checkout Session.
 *
 * The only thing that ever marks a payment as paid is settle(), and settle() only runs
 * after PayMongo's own API has been asked for the session and reports a payment with
 * status "paid" for the right booking and the right amount. Reaching the payment page,
 * the success_url redirect, and the webhook body are never trusted on their own.
 */
class ReservationPayments
{
    public function __construct(
        private readonly PayMongoClient $paymongo,
        private readonly ReservationMailer $mailer,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->paymongo->isConfigured();
    }

    /**
     * Returns the PayMongo checkout URL to send the customer to, or null when the payment
     * turned out to be already paid (the caller should just show the booking).
     *
     * @throws PayMongoException
     */
    public function checkoutUrl(Reservation $reservation): ?string
    {
        $payment = $reservation->payment;

        if (! $payment || $payment->isPaid() || $reservation->status === 'cancelled') {
            return null;
        }

        // Reuse an open session so a customer with two tabs can't pay twice.
        if ($payment->provider_session_id) {
            $session = $this->paymongo->retrieveCheckoutSession($payment->provider_session_id);

            if ($this->paidPaymentIn($session)) {
                $this->sync($payment, $session);

                return null;
            }

            if (data_get($session, 'attributes.status') === 'active' && data_get($session, 'attributes.checkout_url')) {
                return data_get($session, 'attributes.checkout_url');
            }
        }

        $reservation->loadMissing('screening', 'reservationSeats.seat');
        $seats = $reservation->reservationSeats->pluck('seat.seat_label')->join(', ');

        $session = $this->paymongo->createCheckoutSession([
            'line_items' => [[
                'name' => mb_strimwidth($reservation->screening->event_title, 0, 120, '…').' — seats '.$seats,
                'amount' => $this->centavos($payment->amount),
                'currency' => 'PHP',
                'quantity' => 1,
            ]],
            'payment_method_types' => array_values(config('services.paymongo.payment_method_types')),
            'reference_number' => $reservation->booking_reference,
            'description' => 'Cinematheque Centre Davao booking '.$reservation->booking_reference,
            'success_url' => route('bookings.payment.return', $reservation),
            'cancel_url' => route('bookings.show', [$reservation, 'payment' => 'cancelled']),
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'billing' => array_filter([
                'name' => $reservation->lead_full_name,
                'email' => $reservation->lead_email,
                'phone' => $reservation->lead_contact_no,
            ]),
            'metadata' => [
                'reservation_id' => (string) $reservation->reservation_id,
                'payment_id' => (string) $payment->payment_id,
            ],
        ]);

        $payment->update(['provider_session_id' => $session['id']]);

        return data_get($session, 'attributes.checkout_url');
    }

    /**
     * Ask PayMongo for the latest state of this payment's session and settle it if paid.
     * Safe to call any number of times (return page, webhook, staff "refresh").
     *
     * @param  array<string, mixed>|null  $session  an already-fetched session, if any
     * @return bool  true when the payment is (now) paid
     *
     * @throws PayMongoException
     */
    public function sync(Payment $payment, ?array $session = null, ?string $sessionId = null): bool
    {
        if ($payment->isPaid()) {
            return true;
        }

        $sessionId ??= $payment->provider_session_id;
        if (! $session && ! $sessionId) {
            return false;
        }

        $session ??= $this->paymongo->retrieveCheckoutSession($sessionId);
        $reservation = $payment->reservation;

        if (data_get($session, 'attributes.reference_number') !== $reservation->booking_reference) {
            Log::warning('PayMongo session does not belong to this booking', [
                'session' => $session['id'] ?? null, 'booking' => $reservation->booking_reference,
            ]);

            return false;
        }

        $paid = $this->paidPaymentIn($session);
        if (! $paid) {
            return false;
        }

        if ((int) data_get($paid, 'attributes.amount') !== $this->centavos($payment->amount)) {
            Log::error('PayMongo paid amount does not match the amount due', [
                'booking' => $reservation->booking_reference,
                'paid_centavos' => data_get($paid, 'attributes.amount'),
                'due_centavos' => $this->centavos($payment->amount),
            ]);

            return false;
        }

        return $this->settle($payment, $session, $paid);
    }

    /** @param array<string, mixed> $session  @param array<string, mixed> $paid */
    private function settle(Payment $payment, array $session, array $paid): bool
    {
        $confirmedNow = DB::transaction(function () use ($payment, $session, $paid) {
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($payment->isPaid()) {
                return false; // settled by a concurrent request (e.g. webhook + return page)
            }

            $paidAt = data_get($paid, 'attributes.paid_at');
            $payment->update([
                'status' => 'verified',
                'provider_session_id' => $session['id'],
                'provider_payment_id' => $paid['id'] ?? null,
                'paid_at' => $paidAt ? Carbon::createFromTimestamp($paidAt) : now(),
                'payment_channel' => data_get($paid, 'attributes.source.type') ?? $payment->payment_channel,
            ]);

            $reservation = $payment->reservation;
            if ($reservation->status === 'pending') {
                $reservation->update(['status' => 'confirmed']);

                return true;
            }

            // Paid after staff cancelled: keep it cancelled and flag it for a refund.
            Log::warning('PayMongo payment received for a reservation that is not pending', [
                'booking' => $reservation->booking_reference, 'status' => $reservation->status,
            ]);

            return false;
        });

        if ($confirmedNow) {
            $this->mailer->approved($payment->reservation->fresh());
        }

        return true;
    }

    /** @param array<string, mixed> $session  @return array<string, mixed>|null */
    private function paidPaymentIn(array $session): ?array
    {
        return collect(data_get($session, 'attributes.payments', []))
            ->first(fn ($p) => data_get($p, 'attributes.status') === 'paid');
    }

    private function centavos(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
