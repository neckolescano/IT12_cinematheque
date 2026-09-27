@extends('emails.layout')

@section('title', 'Reservation received')
@section('preheader', $payment ? 'Complete your payment to receive your e-ticket.' : 'We received your reservation. Your e-ticket follows once it is approved.')
@section('status_bg', '#fdf1e3')
@section('status_fg', '#7c3a06')
@section('status', $payment ? 'PENDING · AWAITING PAYMENT' : 'PENDING · AWAITING APPROVAL')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $reservation->lead_first_name }},</p>

    @if ($payment)
        <p style="margin:0 0 20px;">We've received your reservation. Your seats are held, but <strong>your booking is only confirmed once payment is completed</strong>. Pay securely through PayMongo using the button below. We'll email your e-ticket as soon as the payment goes through.</p>
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
            <tr><td style="border-radius:24px;background:#ebbc00;">
                <a href="{{ route('bookings.pay', $reservation) }}" style="display:inline-block;padding:12px 26px;color:#141219;font-weight:bold;text-decoration:none;font-size:15px;">Complete payment · ₱{{ number_format($payment->amount, 2) }}</a>
            </td></tr>
        </table>
    @else
        <p style="margin:0 0 20px;">We've received your reservation for a free screening. Cinematheque staff will review it, and <strong>your e-ticket will be emailed to you once it's approved</strong>.</p>
    @endif

    @include('emails.partials.details')

    <p style="margin:0;font-size:13px;color:#6b6674;">You can check your booking anytime: <a href="{{ $bookingUrl }}" style="color:#580076;">{{ $bookingUrl }}</a></p>
@endsection
