<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class TicketLookupController extends Controller
{
    public function form()
    {
        return view('tickets.lookup');
    }

    public function find(Request $request)
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email'],
        ]);

        $booking = Booking::where('reference', strtoupper(trim($data['reference'])))
            ->whereRaw('LOWER(customer_email) = ?', [strtolower($data['email'])])
            ->where('status', 'confirmed')
            ->first();

        if (!$booking) {
            return back()->withInput()->withErrors([
                'reference' => 'We could not find a booking with that reference and email.',
            ]);
        }

        return redirect()->route('booking.show', $booking);
    }
}
