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
            <a class="crumb" style="color:var(--muted);margin-bottom:var(--s-1)" href="{{ route('staff.screenings.show', $screening) }}">&larr; {{ $screening->event_title }}</a>
            <h1 style="letter-spacing:.03em">{{ $reservation->booking_reference }}</h1>
            <div class="cluster">
                <x-status :value="$reservation->status" label="Reservation" />
                @if ($payment)
                    <x-status :value="$payment->status" label="Payment" />
                @endif
                <span class="badge badge--neutral badge--plain">{{ $admitted }} / {{ $reservation->reservationSeats->count() }} admitted</span>
            </div>
        </div>
        <div class="cluster">
            @can('confirm', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.confirm', $reservation) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn--success">Confirm reservation</button>
                </form>
            @endcan
            @can('cancel', $reservation)
                <form class="inline-form" method="POST" action="{{ route('staff.reservations.cancel', $reservation) }}"
                      data-confirm="Cancel {{ $reservation->booking_reference }}? The seats stay held as history and attendees can no longer be checked in." data-confirm-label="Cancel reservation">
                    @csrf @method('PATCH')
                    <button class="btn btn--danger" type="submit">Cancel reservation</button>
                </form>
            @endcan
        </div>
    </div>

    @if ($screening->isPaid() && $payment?->status !== 'verified' && $reservation->status !== 'cancelled')
        <div class="alert alert--warning">
            <div><strong>Payment not yet verified.</strong> Check the proof below before admitting anyone.</div>
        </div>
    @endif

    <div class="grid" style="grid-template-columns:minmax(0,1fr);gap:var(--s-5)">
        <section class="card reveal">
            <div class="card__head"><h2>Booking</h2></div>
            <dl class="kv">
                <dt>Screening</dt><dd>{{ $screening->event_title }} · {{ $screening->event_date->format('M j, Y') }} {{ substr($screening->start_time, 0, 5) }}</dd>
                <dt>Booked by</dt><dd>{{ $reservation->lead_full_name }}</dd>
                <dt>Contact</dt><dd>{{ $reservation->lead_contact_no }} @if ($reservation->lead_email) · {{ $reservation->lead_email }} @endif</dd>
                <dt>Submitted</dt><dd>{{ $reservation->reservation_datetime->format('M j, Y H:i') }}</dd>
            </dl>
        </section>

        <section class="card reveal">
            <div class="card__head">
                <h2>Attendees &amp; admission</h2>
                <span class="muted small">Confirm each person against their declared details, then check them in.</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Seat</th><th>Declared attendee</th><th>Details</th><th style="min-width:260px">Admission</th></tr></thead>
                    <tbody>
                    @foreach ($reservation->reservationSeats as $rs)
                        @php($a = $rs->attendee)
                        <tr>
                            <td><span class="badge badge--gold badge--plain">{{ $rs->seat->seat_label }}</span></td>
                            <td>
                                <strong>{{ $a?->full_name }}</strong>
                                @if ($a?->is_lead_reserver) <div class="muted small">Booker</div> @endif
                            </td>
                            <td class="small">
                                @if ($a)
                                    <div>Age {{ $a->age ?? '—' }} · Sex {{ $a->sex ?? '—' }}</div>
                                    @if ($a->company_school) <div>{{ $a->company_school }}</div> @endif
                                    @if ($a->contact_no || $a->email) <div class="muted">{{ $a->contact_no }} {{ $a->email }}</div> @endif
                                    <div class="cluster" style="margin-top:4px">
                                        @if ($a->senior_card_no) <span class="badge badge--plain">Senior · {{ $a->senior_card_no }}</span> @endif
                                        @if ($a->pwd_indicator) <span class="badge badge--plain">PWD</span> @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($att = $rs->attendance)
                                    <div class="cluster" style="margin-bottom:var(--s-2)">
                                        <x-status value="checked in" />
                                        <span class="muted small">{{ $att->checked_in_at->format('M j H:i') }} · {{ $att->checkedInBy->full_name }}</span>
                                    </div>
                                    @can('update', $att)
                                        <form class="checkin" method="POST" action="{{ route('staff.attendances.update', $att) }}">
                                            @csrf @method('PATCH')
                                            <div class="field">
                                                <label for="cn{{ $att->attendance_id }}">Control no.</label>
                                                <input type="text" id="cn{{ $att->attendance_id }}" name="control_number" maxlength="20" value="{{ $att->control_number }}">
                                            </div>
                                            <div class="field">
                                                <label for="rm{{ $att->attendance_id }}">Remarks</label>
                                                <textarea id="rm{{ $att->attendance_id }}" name="remarks" rows="2">{{ $att->remarks }}</textarea>
                                            </div>
                                            <button type="submit" class="btn btn--ghost btn--sm">Save</button>
                                        </form>
                                    @endcan
                                @else
                                    @can('checkIn', [App\Models\Attendance::class, $rs])
                                        <form class="checkin" method="POST" action="{{ route('staff.attendances.store', $rs) }}">
                                            @csrf
                                            <div class="field">
                                                <label for="ncn{{ $rs->reservation_seat_id }}">Control no. <span class="hint">(from the physical ticket, optional)</span></label>
                                                <input type="text" id="ncn{{ $rs->reservation_seat_id }}" name="control_number" maxlength="20">
                                            </div>
                                            <div class="field">
                                                <label for="nrm{{ $rs->reservation_seat_id }}">Remarks</label>
                                                <textarea id="nrm{{ $rs->reservation_seat_id }}" name="remarks" rows="2"></textarea>
                                            </div>
                                            <button type="submit" class="btn btn--success btn--sm">Check in {{ $rs->seat->seat_label }}</button>
                                        </form>
                                    @else
                                        <span class="muted small">Not admitted</span>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($payment)
            <section class="card reveal">
                <div class="card__head">
                    <h2>Payment</h2>
                    <x-status :value="$payment->status" />
                </div>
                <dl class="kv" style="margin-bottom:var(--s-5)">
                    <dt>Amount due</dt><dd style="font-weight:700">₱{{ number_format($payment->amount, 2) }}</dd>
                    <dt>Channel</dt><dd>{{ $payment->payment_channel ?? '—' }} <span class="muted small">(self-reported)</span></dd>
                    <dt>Created</dt><dd>{{ $payment->created_at?->format('M j, Y H:i') }}</dd>
                </dl>
                <h3>Proof submissions</h3>
                @include('staff.payment-proofs._table', ['proofs' => $payment->proofs->sortByDesc('submitted_at'), 'showReservation' => false])
            </section>
        @endif
    </div>
@endsection
