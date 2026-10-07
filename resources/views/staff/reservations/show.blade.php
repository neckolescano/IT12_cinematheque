@extends('layouts.staff')

@section('title', 'Reservation '.$reservation->booking_reference)

@php
    $screening = $reservation->screening;
    $payment = $reservation->payment;
    $seats = $reservation->reservationSeats;
    $admitted = $seats->filter(fn ($rs) => $rs->attendance)->count();
    [$stateLabel, $tone] = $reservation->staffState();
@endphp

@section('content')
    <header class="page-head">
        <div>
            <a class="back-link" href="{{ route('staff.screenings.show', $screening) }}"><x-arrow dir="left" /> {{ $screening->event_title }}</a>
            <span class="eyebrow">Booking <span class="ref">{{ $reservation->booking_reference }}</span></span>
            <h1>{{ $reservation->lead_full_name }}</h1>
            <p class="figures">
                <span class="state state--{{ $tone }}">{{ $stateLabel }}</span>
                <span><b>{{ $seats->count() }}</b> {{ Str::plural('seat', $seats->count()) }}</span>
                <span><b>{{ $admitted }}</b> admitted</span>
                <span>{{ $screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</span>
            </p>
        </div>
        <div class="cluster">
            @can('confirm', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.confirm', $reservation) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn--primary" title="Approve and email the e-ticket">Approve</button>
                </form>
            @endcan
            <form class="inline-form" method="POST" action="{{ route('staff.reservations.resend', $reservation) }}">
                @csrf
                <button type="submit" class="btn btn--secondary" title="Send the email for the current status again">Resend email</button>
            </form>
            @can('cancel', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.cancel', $reservation) }}" data-confirm-danger
                      data-confirm="Cancel {{ $reservation->booking_reference }}? The booker is emailed and the seats are released." data-confirm-label="Cancel booking">
                    @csrf @method('PATCH')
                    <button class="btn btn--danger" type="submit">Cancel booking</button>
                </form>
            @endcan
        </div>
    </header>

    @if ($reservation->status === 'cancelled' && $payment?->isPaid())
        <div class="alert alert--warning"><div><strong>Refund due.</strong> PayMongo payment {{ $payment->provider_payment_id ?? '(ID not recorded)' }}</div></div>
    @endif

    <div class="split">
        <div class="split__main">
            <section class="block" aria-labelledby="seats-title">
                <div class="block__head">
                    <h2 id="seats-title">Admission</h2>
                    <a href="{{ route('staff.screenings.show', $screening) }}">Screening attendance</a>
                </div>
                <div class="table-wrap">
                    <table class="table attendance">
                        <thead><tr><th>Reservation</th><th>Seats</th><th>Status</th><th>Admitted</th><th class="actions">Actions</th></tr></thead>
                        @include('staff.screenings._party', ['r' => $reservation, 'open' => true])
                    </table>
                </div>
            </section>

            {{-- The declared logsheet details, readable at a glance instead of a second wide table --}}
            <section class="block" aria-labelledby="declared-title">
                <div class="block__head"><h2 id="declared-title">Attendee details</h2></div>
                <ul class="people">
                    @foreach ($seats as $rs)
                        @php($a = $rs->attendee)
                        <li>
                            <span class="seat-tag">{{ $rs->seat->seat_label }}</span>
                            <div>
                                <strong>{{ $a?->full_name ?? '—' }}</strong>
                                <span>{{ collect([$a?->age ? $a->age.' yrs' : null, ['M' => 'Male', 'F' => 'Female'][$a?->sex] ?? null, $a?->company_school])->filter()->join(' · ') ?: '—' }}</span>
                                <span class="muted">{{ collect([$a?->contact_no, $a?->email])->filter()->join(' · ') ?: 'No contact details' }}</span>
                                @if ($a?->senior_card_no || $a?->pwd_id_no || $a?->pwd_indicator)
                                    <span class="muted">{{ collect([$a?->senior_card_no ? 'Senior ID '.$a->senior_card_no : null, $a?->pwd_id_no ? 'PWD ID '.$a->pwd_id_no : ($a?->pwd_indicator ? 'PWD' : null)])->filter()->join(' · ') }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="split__side">
            <section class="block">
                <div class="block__head"><h2>Booker</h2></div>
                <dl class="facts">
                    <dt>Email</dt><dd><a href="mailto:{{ $reservation->lead_email }}">{{ $reservation->lead_email ?? '—' }}</a></dd>
                    <dt>Mobile</dt><dd>{{ $reservation->lead_contact_no }}</dd>
                    <dt>Booked</dt><dd>{{ $reservation->reservation_datetime->format('M j, Y g:i A') }}</dd>
                    @if ($reservation->status === 'pending' && $reservation->paymentDeadline())
                        <dt>Expires</dt><dd>{{ $reservation->paymentDeadline()->format('g:i A') }} if unpaid</dd>
                    @endif
                    @if ($reservation->status === 'cancelled')
                        <dt>Cancelled</dt>
                        <dd>{{ $reservation->wasExpired() ? 'Not paid within '.App\Models\Reservation::PAYMENT_WINDOW_MINUTES.' min' : 'By staff' }}@if ($reservation->cancelled_at), {{ $reservation->cancelled_at->format('M j, g:i A') }}@endif</dd>
                    @endif
                </dl>
            </section>

            <section class="block">
                <div class="block__head">
                    <h2>Payment</h2>
                    @if ($payment?->provider_session_id && ! $payment->isPaid())
                        <form class="inline-form" method="POST" action="{{ route('staff.reservations.sync-payment', $reservation) }}">
                            @csrf
                            <button type="submit" class="btn btn--text btn--sm">Refresh from PayMongo</button>
                        </form>
                    @endif
                </div>
                @if (! $payment)
                    <p class="quiet">Free screening.</p>
                @else
                    <dl class="facts">
                        <dt>Amount</dt><dd>₱{{ number_format($payment->amount, 2) }}</dd>
                        <dt>Status</dt><dd>{{ $payment->isPaid() ? ($reservation->status === 'cancelled' ? 'Paid · refund due' : 'Paid') : 'Not paid yet' }}</dd>
                        <dt>Method</dt><dd>{{ $payment->payment_channel ? strtoupper($payment->payment_channel) : '—' }}</dd>
                        <dt>Paid at</dt><dd>{{ $payment->paid_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                        <dt>PayMongo</dt><dd class="small mono">{{ $payment->provider_payment_id ?? ($payment->provider_session_id ?? 'Checkout not started') }}</dd>
                    </dl>
                @endif
            </section>
        </aside>
    </div>
@endsection

