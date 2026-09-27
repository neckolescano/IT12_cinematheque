<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Services\PayMongo\PayMongoException;
use App\Services\ReservationPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives PayMongo webhooks (register the event "checkout_session.payment.paid").
 *
 * Covers customers who pay but close the tab before returning to the site.
 * The signature is checked first; even then the body is only used to find the
 * session — its status is re-fetched from PayMongo's API before anything changes.
 */
class PayMongoWebhookController extends Controller
{
    public function __invoke(Request $request, ReservationPayments $payments): JsonResponse
    {
        $secret = config('services.paymongo.webhook_secret');
        if (blank($secret)) {
            Log::error('PayMongo webhook received but PAYMONGO_WEBHOOK_SECRET is not set.');

            return response()->json(['message' => 'Webhook not configured.'], 503);
        }

        $payload = $request->getContent();
        $event = json_decode($payload, true);
        if (! is_array($event) || ! $this->signatureIsValid($request->header('Paymongo-Signature', ''), $payload, $secret, (bool) data_get($event, 'data.attributes.livemode'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        if (data_get($event, 'data.attributes.type') !== 'checkout_session.payment.paid') {
            return response()->json(['message' => 'Ignored.']);
        }

        $sessionId = data_get($event, 'data.attributes.data.id');
        $reference = data_get($event, 'data.attributes.data.attributes.reference_number');

        $payment = Payment::where('provider_session_id', $sessionId)->first()
            ?? Reservation::where('booking_reference', $reference)->first()?->payment;

        if (! $payment || ! $sessionId) {
            Log::warning('PayMongo webhook for an unknown checkout session', ['session' => $sessionId, 'reference' => $reference]);

            return response()->json(['message' => 'Unknown session.']);
        }

        try {
            $payments->sync($payment, sessionId: $sessionId);
        } catch (PayMongoException $e) {
            report($e);

            return response()->json(['message' => 'Could not verify with PayMongo; please retry.'], 502);
        }

        return response()->json(['message' => 'OK']);
    }

    /**
     * Paymongo-Signature: t=<timestamp>,te=<test-mode signature>,li=<live-mode signature>
     * signature = HMAC-SHA256("<timestamp>.<raw body>", webhook secret)
     */
    private function signatureIsValid(string $header, string $payload, string $secret, bool $livemode): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key] = $value;
        }

        $expected = $livemode ? ($parts['li'] ?? '') : ($parts['te'] ?? '');
        if (($parts['t'] ?? '') === '' || $expected === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $parts['t'].'.'.$payload, $secret), $expected);
    }
}
