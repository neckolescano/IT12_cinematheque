{{--
    One booking ("party") on the screening roster: a summary row, then one row per guest with the details
    staff verify at the door (School / Company, PWD / Senior ID, discount). The roster replaces the old
    Reservations and Attendance pages: booking actions (Resend email, Refresh from PayMongo, Cancel) are here.
    Party of 1: "Admit" on the summary row. Party of 2+: "Admit party" opens the guests with everyone ticked;
    staff untick anyone absent and confirm ("Admit 3").
    Check-in is time-locked (Screening::checkInState()): before it opens the Admit buttons are disabled with
    the opening time; after it closes the roster is read-only, except for the Super Admin.
    Rendered on page load and returned (JSON "party") after every admission change.
    Expects $r with screening, payment, reservationSeats.{seat,attendee,attendance.checkedInBy}; optional $open.
--}}
@php
    $screening = $r->screening;
    $seats = $r->reservationSeats->sortBy(fn ($s) => [$s->attendee?->is_lead_reserver ? 0 : 1, $s->seat_id])->values();
    $size = $seats->count();
    $in = $seats->filter(fn ($s) => $s->attendance)->count();
    $isPast = $screening->event_date->lt(today());
    $state = match (true) {
        $r->status === 'cancelled' => 'cancelled',
        $r->status === 'awaiting_payment' => 'pending',
        $in === $size => 'admitted',
        $isPast => 'no-show',
        default => 'to-admit',
    };
    [$stateLabel, $tone] = $r->staffState();
    $admittable = $seats->filter(fn ($s) => Gate::allows('checkIn', [App\Models\Attendance::class, $s]));
    // Waiting for the check-in window (or after it): show the button, disabled, with when it opens.
    $locked = $admittable->isEmpty() && $r->status === 'confirmed' && $in < $size && ! $screening->allowsCheckInBy(auth()->user());
    $lockText = $screening->checkInState() === 'not_yet'
        ? 'Check-in opens at '.$screening->checkInOpensAt()->format('g:i A').($screening->event_date->isToday() ? '' : ', '.$screening->event_date->format('M j'))
        : 'Check-in closed';
    $payment = $r->payment;
    $paymentText = match (true) {
        ! $payment => 'Free',
        $payment->isPaid() && $r->status === 'cancelled' => 'Paid · refund due',
        $payment->isPaid() => '₱'.number_format($payment->amount, 2).' paid',
        default => '₱'.number_format($payment->amount, 2).' unpaid',
    };
    $flags = collect([
        $seats->filter(fn ($s) => $s->attendee?->senior_card_no)->count() ? 'Senior' : null,
        $seats->filter(fn ($s) => $s->attendee?->pwd_indicator || $s->attendee?->pwd_id_no)->count() ? 'PWD' : null,
    ])->filter();
    $search = mb_strtolower(collect([$r->lead_full_name, $r->booking_reference, $r->lead_email, $r->lead_contact_no])
        ->merge($seats->map(fn ($s) => $s->attendee?->full_name.' '.$s->seat->seat_label.' '.$s->attendee?->company_school))->join(' '));
    $single = $size === 1 ? $seats->first() : null;
    $formId = 'admit-'.$r->reservation_id;
    $open = ($open ?? false) || request('open') === $r->booking_reference;
@endphp
<tbody id="booking-{{ $r->booking_reference }}" data-party="{{ $r->reservation_id }}" data-state="{{ $state }}" data-search="{{ $search }}" @class(['party', 'is-open' => $open, 'is-muted' => $state === 'cancelled'])>
    <tr class="party__row">
        <td>
            <div class="party__who">
                <button type="button" class="party__toggle" data-party-toggle aria-expanded="{{ $open ? 'true' : 'false' }}" aria-label="Show {{ $size > 1 ? 'the '.$size.' guests' : 'guest details' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                </button>
                <div>
                    <div class="cell-title">
                        {{ $r->lead_full_name }}@if ($size > 1)<span class="party__size"> · Party of {{ $size }}</span>@endif
                    </div>
                    <div class="cell-sub">
                        <span class="ref">{{ $r->booking_reference }}</span> · {{ $paymentText }}
                        @foreach ($flags as $flag) · <b>{{ $flag }}</b> @endforeach
                    </div>
                </div>
            </div>
        </td>
        <td class="party__seats">@foreach ($seats as $s)<span class="seat-tag">{{ $s->seat->seat_label }}</span>@endforeach</td>
        <td><span class="state state--{{ $tone }}">{{ $stateLabel }}</span></td>
        <td class="nowrap">
            @if ($single && $single->attendance)
                <span class="text-success">In {{ $single->attendance->checked_in_at->format('g:i A') }}</span>
                <div class="attend-meta">by {{ $single->attendance->checkedInBy->first_name }}</div>
            @elseif ($state === 'cancelled' || $state === 'pending')
                <span class="muted">—</span>
            @elseif ($size > 1)
                <span @class(['text-success' => $in === $size])><b>{{ $in }}</b> / {{ $size }}</span>
                @if ($isPast && $in < $size)<div class="attend-meta text-error">{{ $size - $in }} no-show</div>@endif
            @elseif ($isPast)
                <span class="text-error">No-show</span>
            @else
                <span class="muted">Not in</span>
            @endif
        </td>
        <td class="actions">
            <div class="row-actions">
                @if ($locked)
                    <button type="button" class="btn btn--primary btn--sm" disabled title="{{ $lockText }}" aria-describedby="checkin-status">{{ $size > 1 ? 'Admit party' : 'Admit' }}</button>
                @elseif ($single && $state !== 'pending')
                    @include('staff.screenings._attendee-actions', ['s' => $single, 'admittable' => $admittable->isNotEmpty()])
                @elseif ($admittable->isNotEmpty())
                    <button type="button" class="btn btn--primary btn--sm" data-party-admit>Admit party</button>
                @endif
                <details class="more">
                    <summary class="btn btn--secondary btn--sm" aria-label="More actions for {{ $r->booking_reference }}">⋯</summary>
                    <div class="more__panel">
                        <form method="POST" action="{{ route('staff.reservations.resend', $r) }}">
                            @csrf
                            <button type="submit">Resend email</button>
                        </form>
                        @if ($payment?->provider_session_id && ! $payment->isPaid())
                            <form method="POST" action="{{ route('staff.reservations.sync-payment', $r) }}">
                                @csrf
                                <button type="submit">Refresh from PayMongo</button>
                            </form>
                        @endif
                        @can('cancel', $r)
                            <form method="POST" action="{{ route('staff.reservations.cancel', $r) }}" data-confirm-danger
                                  data-confirm="Cancel {{ $r->booking_reference }}? The booker is emailed and the seats are released." data-confirm-label="Cancel booking">
                                @csrf @method('PATCH')
                                <button class="danger" type="submit">Cancel booking</button>
                            </form>
                        @endcan
                    </div>
                </details>
            </div>
        </td>
    </tr>

    @foreach ($seats as $s)
        @php
            $a = $s->attendee;
            $ids = collect([
                $a?->pwd_id_no ? 'PWD '.$a->pwd_id_no : ($a?->pwd_indicator ? 'PWD' : null),
                $a?->senior_card_no ? 'Senior '.$a->senior_card_no : null,
            ])->filter();
            $discount = $s->discount_type !== 'none' && (float) $s->unit_price > 0
                ? '20% off · ₱'.number_format($s->amount_due, 2) : null;
        @endphp
        <tr class="party__member" data-seat-state="{{ $s->attendance ? 'admitted' : 'out' }}">
            <td>
                <div class="party__who party__who--member">
                    @if ($size > 1 && $admittable->contains($s))
                        <input type="checkbox" class="party__pick" name="seats[]" value="{{ $s->reservation_seat_id }}" form="{{ $formId }}" checked
                               aria-label="Admit {{ $a?->full_name }}, seat {{ $s->seat->seat_label }}">
                    @else
                        <span class="party__pick-spacer" aria-hidden="true"></span>
                    @endif
                    <div class="guest">
                        <span class="guest__name">{{ $a?->full_name ?? '—' }}@if ($a?->is_lead_reserver)<span class="muted small"> · Booker</span>@endif</span>
                        <span class="guest__meta">{{ collect([$a?->age ? $a->age.' yrs' : null, ['M' => 'Male', 'F' => 'Female'][$a?->sex] ?? null, $a?->company_school])->filter()->join(' · ') }}</span>
                        @if ($ids->isNotEmpty() || $discount)
                            <span class="guest__ids">{{ $ids->join(' · ') }}@if ($discount)<b> · {{ $discount }}</b>@endif</span>
                        @endif
                        <span class="guest__contact">{{ collect([$a?->contact_no, $a?->email])->filter()->join(' · ') }}</span>
                    </div>
                </div>
            </td>
            <td><span class="seat-tag">{{ $s->seat->seat_label }}</span></td>
            <td></td>
            <td class="nowrap">
                @if ($s->attendance)
                    <span class="text-success">In {{ $s->attendance->checked_in_at->format('g:i A') }}</span>
                    <span class="attend-meta">· {{ $s->attendance->checkedInBy->first_name }}</span>
                @elseif ($r->status === 'confirmed' && $isPast)
                    <span class="text-error">No-show</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="actions"><div class="row-actions">@if ($size > 1 && $s->attendance && Gate::allows('update', $s->attendance))@include('staff.screenings._attendee-actions', ['s' => $s, 'admittable' => false])@endif</div></td>
        </tr>
    @endforeach
    @if ($size > 1 && $admittable->isNotEmpty())
        <tr class="party__confirm">
            <td colspan="4" class="muted small">Untick anyone who isn’t here.</td>
            <td class="actions">
                <form id="{{ $formId }}" method="POST" action="{{ route('staff.reservations.admit', $r) }}" data-ajax data-no-loading>
                    @csrf
                    <button type="submit" class="btn btn--primary btn--sm" data-party-submit>Admit {{ $admittable->count() }}</button>
                </form>
            </td>
        </tr>
    @endif
</tbody>
