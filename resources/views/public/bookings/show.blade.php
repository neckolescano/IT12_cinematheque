@extends('layouts.app')

@section('title', 'Booking '.$reservation->booking_reference)

@php
    $screening = $reservation->screening;
    $movie = $screening->movie;
    $paid = (bool) $payment;
    $cancelled = $reservation->status === 'cancelled';
    $approved = $reservation->status === 'confirmed';
    $step = $approved ? ($paid ? 4 : 3) : ($paid ? 3 : 2);
    $seatLabels = $reservation->reservationSeats->pluck('seat.seat_label');
    $methodNames = ['card' => 'Card', 'gcash' => 'GCash', 'paymaya' => 'Maya', 'grab_pay' => 'GrabPay', 'qrph' => 'QR Ph', 'shopee_pay' => 'ShopeePay', 'billease' => 'BillEase'];
    $methods = collect(config('services.paymongo.payment_method_types'))->map(fn ($m) => $methodNames[$m] ?? ucfirst($m))->take(3);

    [$stateIcon, $headline, $subline] = match (true) {
        $cancelled => ['cancelled', 'Reservation cancelled', 'This booking can no longer be used for admission.'],
        $approved => ['success', 'Booking confirmed', 'Your e-ticket has been emailed to '.$reservation->lead_email.'.'],
        default => ['pending', 'Reservation received', 'Waiting for staff approval. Your e-ticket will be emailed to '.$reservation->lead_email.' once approved.'],
    };
@endphp

@section('content')
    @unless ($cancelled)
        <x-stepper :current="$step" :paid="$paid" class="no-print" />
    @endunless

    @if ($canPay)
        {{-- Unpaid (paid screening): payment + summary, as in the payment-step reference --}}
        @if ($paymentCancelled)
            <div class="alert alert--warning"><div>Payment was not completed. Your seats are still held. You can try again below.</div></div>
        @endif

        <div class="booking-layout">
            <section class="card">
                <h1 class="card__title" style="font-size:var(--fs-xl)">
                    <span class="card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></span>
                    Complete your payment
                </h1>
                <p class="muted small">Booking <strong style="color:var(--text);letter-spacing:.04em">{{ $reservation->booking_reference }}</strong> is held for you. It is confirmed, and your e-ticket sent, as soon as PayMongo reports the payment.</p>

                <h2 class="label" style="margin:var(--s-5) 0 var(--s-3)">Pay securely through PayMongo with</h2>
                <div class="pay-methods">
                    @foreach ($methods as $name)
                        <div class="pay-method">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">@if ($name === 'Card')<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>@else<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>@endif</svg>
                            {{ $name }}
                        </div>
                    @endforeach
                </div>

                <div class="summary__total" style="border-top:0;padding-top:0">
                    <span class="muted">Amount due</span>
                    <strong>₱{{ number_format($payment->amount, 2) }}</strong>
                </div>

                @if ($paymentsEnabled)
                    <a class="btn btn--primary btn--lg btn--block" href="{{ route('bookings.pay', $reservation) }}">Pay ₱{{ number_format($payment->amount, 2) }} with PayMongo <span class="arrow" aria-hidden="true">&rarr;</span></a>
                    <p class="secure-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>You'll enter your payment details on PayMongo's secure page. This site never sees your card or wallet details.</p>
                    @if ($payment->provider_session_id)
                        <p class="small" style="margin:var(--s-4) 0 0">Already paid? <a href="{{ route('bookings.payment.return', $reservation) }}">Check payment status</a></p>
                    @endif
                @else
                    <div class="alert alert--warning" style="margin:0"><div>Online payment is temporarily unavailable. Your seats are held — please try again later.</div></div>
                @endif
            </section>

            <x-booking-summary :screening="$screening" :seat-labels="$seatLabels" :booked-by="$reservation->lead_full_name">
                <p class="summary__hint" style="margin-top:0">Reference {{ $reservation->booking_reference }} · <a href="{{ route('bookings.lookup') }}">find it again later</a></p>
            </x-booking-summary>
        </div>
    @else
        {{-- Confirmed / waiting for approval / cancelled: the confirmation (e-ticket) layout --}}
        <div class="confirm">
            <span class="confirm__icon confirm__icon--{{ $stateIcon }}" aria-hidden="true">
                @if ($stateIcon === 'success')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m5 12 5 5 9-10"/></svg>
                @elseif ($stateIcon === 'pending')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                @endif
            </span>
            <h1>{{ $headline }}</h1>
            <p class="muted">{{ $subline }}</p>

            <article class="card ticket" aria-label="{{ $approved ? 'E-ticket' : 'Booking details' }}">
                <div class="ticket__top">
                    <x-poster :screening="$screening" class="poster--thumb" />
                    <div>
                        <strong style="font-size:var(--fs-lg)">{{ $screening->event_title }}</strong>
                        <div class="dot-list" style="margin-top:4px">
                            @if ($movie?->genres->isNotEmpty())<span>{{ $movie->genres->pluck('genre_name')->take(2)->join(', ') }}</span>@endif
                            @if ($movie?->rating)<span>{{ $movie->rating }}</span>@endif
                            <span>{{ $approved ? 'Approved' : ucfirst($reservation->status) }}</span>
                        </div>
                    </div>
                    <div class="ticket__ref"><span>Booking reference</span><strong>{{ $reservation->booking_reference }}</strong></div>
                </div>
                <dl class="ticket__grid">
                    <div><dt>Date</dt><dd>{{ $screening->event_date->format('l, F j, Y') }}</dd></div>
                    <div><dt>Time</dt><dd>{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</dd></div>
                    <div><dt>Seats</dt><dd>{{ $seatLabels->join(', ') }}</dd></div>
                    <div><dt>{{ $paid ? 'Amount' : 'Admission' }}</dt><dd>{{ $paid ? '₱'.number_format($payment->amount, 2).($payment->isPaid() ? ' · paid' : '') : 'Free' }}</dd></div>
                    <div style="grid-column:1 / -1">
                        <dt>Attendees</dt>
                        <dd>
                            @foreach ($reservation->reservationSeats as $rs)
                                <span style="display:inline-block;margin:2px 12px 2px 0">{{ $rs->seat->seat_label }} — {{ $rs->attendee?->full_name }}@if ($rs->attendee?->is_lead_reserver) <span class="muted small">(booker)</span>@endif</span>
                            @endforeach
                        </dd>
                    </div>
                </dl>
                <div class="ticket__foot">
                    @if ($approved)
                        Show this e-ticket or the booking reference at the entrance. Each attendee is admitted by staff on arrival.
                    @elseif ($cancelled)
                        {{ $payment?->isPaid() ? 'A payment was recorded — contact Cinematheque Centre Davao about a refund.' : 'Contact Cinematheque Centre Davao if you think this is a mistake.' }}
                    @else
                        Keep your booking reference. You can check this page anytime from "Find my booking".
                    @endif
                </div>
            </article>

            <div class="confirm__actions no-print">
                @if ($approved)
                    <button type="button" class="btn btn--primary" onclick="window.print()">Print / save e-ticket</button>
                @else
                    <a class="btn btn--primary" href="{{ route('bookings.lookup') }}">Find my booking</a>
                @endif
                <a class="btn btn--ghost" href="{{ route('home') }}">Back to screenings</a>
            </div>

            @can('view', $reservation)
                <p class="small no-print" style="margin-top:var(--s-5)"><a href="{{ route('staff.reservations.show', $reservation) }}">Staff view of this reservation &rarr;</a></p>
            @endcan
        </div>
    @endif
@endsection
