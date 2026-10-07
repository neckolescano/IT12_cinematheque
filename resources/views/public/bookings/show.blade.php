@extends('layouts.app')

@section('title', 'Booking '.$reservation->booking_reference)
@section('main_class', 'page page--narrow')

@php
    $screening = $reservation->screening;
    $cancelled = $reservation->status === 'cancelled';
    $approved = $reservation->status === 'confirmed';
    $seats = $reservation->reservationSeats;
    $methodNames = ['card' => 'Card', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'grab_pay' => 'GrabPay', 'qrph' => 'QR Ph', 'shopee_pay' => 'ShopeePay', 'billease' => 'BillEase'];
    $methods = collect(config('services.paymongo.payment_method_types'))->map(fn ($m) => $methodNames[$m] ?? ucfirst($m));

    // The status panel at the top, as in the mobile app.
    [$tone, $headline, $message] = match (true) {
        $reservation->wasExpired() && $payment?->isPaid() => ['error', 'Reservation expired', 'Your payment arrived after the '.\App\Models\Reservation::PAYMENT_WINDOW_MINUTES.'-minute window, so the seats were released. Cinematheque will refund it — keep your booking reference.'],
        $reservation->wasExpired() => ['error', 'Reservation expired', 'Payment was not completed within '.\App\Models\Reservation::PAYMENT_WINDOW_MINUTES.' minutes, so the seats were released.'],
        $cancelled => ['neutral', 'Booking cancelled', $payment?->isPaid() ? 'Refunds for paid bookings are handled directly by Cinematheque Centre Davao.' : 'This booking can no longer be used for admission.'],
        $approved => ['success', 'Booking confirmed', 'Your e-ticket is below and was sent to '.$reservation->lead_email.'. Show it at the entrance.'],
        $canPay => ['gold', 'Payment needed', 'Your seats are held'.($payBy ? ' until '.$payBy->format('g:i A') : '').'. Pay before then to confirm them.'],
        default => ['gold', 'Reservation received', 'Cinematheque staff will review it. Your e-ticket is emailed to '.$reservation->lead_email.' once it is approved.'],
    };
@endphp

@section('content')
    @if (session('booked') && ! $cancelled)
        <dialog class="modal modal--success no-print" data-autoclose="3500" aria-labelledby="booked-title">
            <div class="modal__body">
                <h2 id="booked-title">{{ $approved ? 'Booking confirmed' : 'Reservation received' }}</h2>
                <p class="muted small">Reference <strong class="ref">{{ $reservation->booking_reference }}</strong></p>
                <div class="modal__timer" aria-hidden="true"><span></span></div>
            </div>
            <div class="modal__actions"><button type="button" class="btn btn--gold btn--sm" data-modal-close>View booking</button></div>
        </dialog>
    @endif

    @unless ($cancelled)
        <x-stepper :current="3" :paid="(bool) $payment" class="no-print" />
    @endunless

    <div class="status status--{{ $tone }}" role="status">
        <strong>{{ $headline }}</strong>
        <span>{{ $message }}</span>
    </div>

    @if ($canPay)
        {{-- Payment: one compact order summary and one action. --}}
        @if ($paymentCancelled)
            <div class="alert alert--warning"><div>Payment was not completed. Your seats are still held.</div></div>
        @endif
        <section class="pay" aria-labelledby="pay-title">
            <h1 class="sr-only" id="pay-title">Payment</h1>
            <div class="pay__film">
                <x-poster :screening="$screening" class="poster--thumb" />
                <div>
                    <strong class="pay__title">{{ $screening->event_title }}</strong>
                    <span class="muted small">{{ $screening->event_date->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</span>
                    <span class="muted small">Seats {{ $seats->pluck('seat.seat_label')->join(', ') }}</span>
                </div>
            </div>
            <dl class="pay__lines">
                <div><dt>₱{{ number_format($screening->price, 2) }} × {{ $seats->count() }} {{ Str::plural('seat', $seats->count()) }}</dt><dd class="money">₱{{ number_format($payment->amount, 2) }}</dd></div>
                <div class="pay__total"><dt>Total</dt><dd class="money">₱{{ number_format($payment->amount, 2) }}</dd></div>
            </dl>
            @if ($paymentsEnabled)
                <a class="btn btn--gold btn--lg btn--block" href="{{ route('bookings.pay', $reservation) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    Pay ₱{{ number_format($payment->amount, 2) }}
                </a>
                <p class="pay__note">Secure payment by PayMongo · {{ $methods->join(', ', ' or ') }}</p>
                @if ($payment->provider_session_id)
                    <p class="pay__note"><a href="{{ route('bookings.payment.return', $reservation) }}">Already paid? Check payment status</a></p>
                @endif
            @else
                <div class="alert alert--warning"><div>Online payment is unavailable right now. Your seats are held — please try again shortly.</div></div>
            @endif
            <p class="pay__ref">Reference <strong class="ref">{{ $reservation->booking_reference }}</strong></p>
        </section>
    @else
        @include('public.bookings._eticket')

        <div class="actions no-print">
            @if ($approved)
                <button type="button" class="btn btn--gold" onclick="window.print()">Print e-ticket</button>
            @endif
            <a class="btn btn--secondary" href="{{ route('home') }}">Back to screenings</a>
        </div>
    @endif
@endsection
