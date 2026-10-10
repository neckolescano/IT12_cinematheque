@extends('emails.layout')

{{-- Sent for paid screenings only: free reservations are confirmed on submit and get the e-ticket (approved.blade.php). --}}
@section('title', 'Reservation received')
@section('preheader', 'Complete your payment to receive your e-ticket.')
@section('status_bg', '#fdf1e3')
@section('status_fg', '#7c3a06')
@section('status', 'AWAITING PAYMENT')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $reservation->lead_first_name }},</p>

    <p style="margin:0 0 20px;">We've received your reservation. Your seats are held until <strong>{{ $reservation->paymentDeadline()->format('g:i A') }}</strong>, but <strong>your booking is only confirmed once payment is completed</strong>; unpaid bookings expire after {{ \App\Models\Reservation::PAYMENT_WINDOW_MINUTES }} minutes. Pay securely through PayMongo using the button below. We'll email your e-ticket as soon as the payment goes through.</p>
    @if ($payment)
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
            <tr><td style="border-radius:24px;background:#ebbc00;">
                <a href="{{ route('bookings.pay', $reservation) }}" style="display:inline-block;padding:12px 26px;color:#141219;font-weight:bold;text-decoration:none;font-size:15px;">Complete payment · ₱{{ number_format($payment->amount, 2) }}</a>
            </td></tr>
        </table>
    @endif

    @include('emails.partials.details')

    <p style="margin:0;font-size:13px;color:#6b6674;">You can check your booking anytime: <a href="{{ $bookingUrl }}" style="color:#580076;">{{ $bookingUrl }}</a></p>
@endsection
