<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff review queue for uploaded payment screenshots.
 * Accept: proof accepted -> payment verified -> reservation confirmed.
 * Reject: only the proof changes; payment stays pending so the moviegoer can re-upload.
 */
class PaymentProofController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PaymentProof::class);

        $status = $request->validate([
            'status' => ['nullable', Rule::in([...PaymentProof::STATUSES, 'all'])],
        ])['status'] ?? 'pending';

        $proofs = PaymentProof::with('payment.reservation.screening', 'reviewer')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderBy('submitted_at')
            ->paginate(25)
            ->withQueryString();

        return view('staff.payment-proofs.index', compact('proofs', 'status'));
    }

    public function accept(Request $request, PaymentProof $proof): RedirectResponse
    {
        Gate::authorize('review', $proof);

        DB::transaction(function () use ($request, $proof) {
            $proof->update([
                'status' => 'accepted',
                'reviewed_by' => $request->user()->user_id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $proof->payment->update(['status' => 'verified']);
            $proof->payment->reservation->update(['status' => 'confirmed']);
        });

        return back()->with('status', 'Proof accepted. Payment verified and reservation confirmed.');
    }

    public function reject(Request $request, PaymentProof $proof): RedirectResponse
    {
        Gate::authorize('review', $proof);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $proof->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->user_id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        return back()->with('status', 'Proof rejected. The moviegoer can upload a new one.');
    }
}
