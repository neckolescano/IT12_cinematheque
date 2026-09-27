@extends('layouts.staff')

@section('title', 'Reservation '.$reservation->booking_reference)

@php
    $screening = $reservation->screening;
    $payment = $reservation->payment;
    $admitted = $reservation->reservationSeats->filter(fn ($rs) => $rs->attendance)->count();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <a class="crumb" href="{{ route('staff.screenings.show', $screening) }}">&larr; {{ $screening->event_title }}</a>
            <h1 style="letter-spacing:.02em">{{ $reservation->booking_reference }}</h1>
            <div class="cluster" style="margin-top:6px">
                <x-status :value="$reservation->status === 'confirmed' ? 'approved' : $reservation->status" label="Reservation" />
                @if ($payment)
                    <x-status :value="$payment->isPaid() ? 'paid' : 'unpaid'" label="Payment" />
                @else
                    <span class="badge badge--neutral badge--plain">Free screening</span>
                @endif
                <span class="badge badge--neutral badge--plain">{{ $admitted }} / {{ $reservation->reservationSeats->count() }} admitted</span>
            </div>
        </div>
        <div class="cluster">
            @can('confirm', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.confirm', $reservation) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn--success">Approve &amp; send e-ticket</button>
                </form>
            @endcan
            <form class="inline-form" method="POST" action="{{ route('staff.reservations.resend', $reservation) }}">
                @csrf
                <button type="submit" class="btn btn--ghost" title="Send the email for the current status again">Resend email</button>
            </form>
            @can('cancel', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.cancel', $reservation) }}"
                      data-confirm="Cancel {{ $reservation->booking_reference }}? The customer is emailed and nobody on it can be admitted. Seats stay held as history." data-confirm-label="Cancel reservation">
                    @csrf @method('PATCH')
                    <button class="btn btn--danger" type="submit">Cancel</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($reservation->status === 'cancelled' && $payment?->isPaid())
        <div class="alert alert--warning">This booking was paid through PayMongo but is cancelled. A refund may be due. Quote PayMongo payment <strong>{{ $payment->provider_payment_id ?? 'ID not recorded' }}</strong>.</div>
    @endif

    <div class="grid grid-2" style="align-items:start;margin-bottom:20px">
        <section class="card">
            <div class="card__head"><h2>Booking</h2></div>
            <dl class="kv">
                <dt>Screening</dt><dd><a href="{{ route('staff.screenings.show', $screening) }}">{{ $screening->event_title }}</a></dd>
                <dt>When</dt><dd>{{ $screening->event_date->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</dd>
                <dt>Booked by</dt><dd>{{ $reservation->lead_full_name }}</dd>
                <dt>Email</dt><dd>{{ $reservation->lead_email ?? '—' }}</dd>
                <dt>Contact</dt><dd>{{ $reservation->lead_contact_no }}</dd>
                <dt>Submitted</dt><dd>{{ $reservation->reservation_datetime->format('M j, Y g:i A') }}</dd>
            </dl>
        </section>

        <section class="card">
            <div class="card__head">
                <h2>Payment</h2>
                @if ($payment?->provider_session_id && ! $payment->isPaid())
                    <form class="inline-form" method="POST" action="{{ route('staff.reservations.sync-payment', $reservation) }}">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm">Refresh from PayMongo</button>
                    </form>
                @endif
            </div>
            @if (! $payment)
                <p class="muted" style="margin:0">Free screening — no payment involved.</p>
            @else
                <dl class="kv">
                    <dt>Amount</dt><dd>₱{{ number_format($payment->amount, 2) }}</dd>
                    <dt>Status</dt><dd><x-status :value="$payment->isPaid() ? 'paid' : 'unpaid'" /></dd>
                    <dt>Method</dt><dd>{{ $payment->payment_channel ? strtoupper($payment->payment_channel) : '—' }}</dd>
                    <dt>Paid at</dt><dd>{{ $payment->paid_at?->format('M j, Y g:i A') ?? '—' }}</dd>
                    <dt>Checkout session</dt><dd class="small">{{ $payment->provider_session_id ?? 'Not started' }}</dd>
                    <dt>PayMongo payment</dt><dd class="small">{{ $payment->provider_payment_id ?? '—' }}</dd>
                </dl>
                @if ($payment->proofs->isNotEmpty())
                    <details style="margin-top:14px">
                        <summary class="small muted" style="cursor:pointer">Earlier screenshot proofs ({{ $payment->proofs->count() }}) — from the retired QR flow</summary>
                        <ul class="small" style="margin:8px 0 0;padding-left:18px">
                            @foreach ($payment->proofs as $proof)
                                <li><a href="{{ asset('storage/'.$proof->proof_image) }}" target="_blank" rel="noopener">{{ $proof->submitted_at->format('M j, Y g:i A') }}</a> · {{ $proof->status }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            @endif
        </section>
    </div>

    <section class="card card--flush">
        <div class="card__head">
            <h2>Attendees &amp; admission</h2>
            <a class="btn btn--ghost btn--sm" href="{{ route('staff.screenings.show', $screening) }}">Open full screening checklist</a>
        </div>
        <div class="table-wrap">
            <table class="table checklist">
                <thead><tr><th>✓</th><th>Name</th><th>Seat</th><th>Booking</th><th>Reservation</th><th>Payment</th><th>Attendance</th><th></th></tr></thead>
                <tbody data-checklist>
                @foreach ($reservation->reservationSeats as $rs)
                    @include('staff.screenings._attendee-row', ['rs' => $rs->setRelation('reservation', $reservation)->setRelation('screening', $screening)])
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--border)">
            <details>
                <summary class="small muted" style="cursor:pointer">Declared attendee details (age, sex, company/school, contact)</summary>
                <div class="table-wrap" style="margin-top:10px">
                    <table class="table">
                        <thead><tr><th>Seat</th><th>Name</th><th>Age</th><th>Sex</th><th>Company / school</th><th>Contact</th><th>Senior card</th><th>PWD</th></tr></thead>
                        <tbody>
                        @foreach ($reservation->reservationSeats as $rs)
                            @php($a = $rs->attendee)
                            <tr>
                                <td>{{ $rs->seat->seat_label }}</td><td>{{ $a?->full_name }}</td><td>{{ $a?->age ?? '—' }}</td><td>{{ $a?->sex ?? '—' }}</td>
                                <td>{{ $a?->company_school ?? '—' }}</td><td>{{ trim(($a?->contact_no ?? '').' '.($a?->email ?? '')) ?: '—' }}</td>
                                <td>{{ $a?->senior_card_no ?? '—' }}</td><td>{{ $a?->pwd_indicator ? 'Yes' : 'No' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    </section>
@endsection
