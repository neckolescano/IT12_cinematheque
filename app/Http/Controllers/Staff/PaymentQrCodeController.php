<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\PaymentQrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentQrCodeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PaymentQrCode::class);

        return view('staff.qr-codes.index', [
            'current' => PaymentQrCode::current(),
            'history' => PaymentQrCode::with('uploader')->orderByDesc('uploaded_at')->paginate(20),
        ]);
    }

    /** Replacing the QR deactivates the old row and inserts the new one in one transaction. */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PaymentQrCode::class);

        $request->validate([
            'qr_image' => ['required', 'image', 'max:5120'],
        ]);

        $path = $request->file('qr_image')->store('qr-codes', 'public');

        DB::transaction(function () use ($request, $path) {
            PaymentQrCode::where('is_active', true)->update(['is_active' => false]);

            PaymentQrCode::create([
                'qr_image' => $path,
                'is_active' => true,
                'uploaded_by' => $request->user()->user_id,
                'uploaded_at' => now(),
            ]);
        });

        return redirect()->route('staff.qr-codes.index')->with('status', 'Payment QR code replaced.');
    }
}
