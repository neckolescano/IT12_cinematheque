<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public: the moviegoer uploads a screenshot of an external e-wallet/bank transfer.
 * This only stores the file — it never confirms anything. Staff review it later.
 */
class PaymentProofUploadController extends Controller
{
    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $payment = $reservation->payment;

        abort_if(! $payment, 404, 'This reservation has no payment due.');

        if ($reservation->status === 'cancelled' || $payment->status === 'verified') {
            return back()->withErrors(['proof_image' => 'This payment no longer accepts proof uploads.']);
        }

        if ($payment->hasPendingProof()) {
            return back()->withErrors(['proof_image' => 'Your previous proof is still awaiting staff review.']);
        }

        $data = $request->validate([
            'proof_image' => ['required', 'image', 'max:5120'],
            'payment_channel' => ['nullable', 'string', 'max:30'],
        ]);

        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        if (! empty($data['payment_channel'])) {
            $payment->update(['payment_channel' => $data['payment_channel']]);
        }

        $payment->proofs()->create([
            'proof_image' => $path,
            'submitted_at' => now(),
            'status' => 'pending',
        ]);

        return redirect()->route('bookings.show', $reservation)
            ->with('status', 'Proof uploaded. Staff will review it; your reservation is confirmed once it is accepted.');
    }
}
