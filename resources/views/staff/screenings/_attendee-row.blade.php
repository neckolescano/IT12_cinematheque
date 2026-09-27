{{--
    One row of a screening's attendee checklist = one reserved seat.
    Rendered on page load and returned by AttendanceController after an AJAX admit/undo.
    Reservation status, payment status and attendance are shown separately on purpose:
    a reservation (even a paid one) is never treated as attendance.
--}}
@php
    $r = $rs->reservation;
    $a = $rs->attendee;
    $att = $rs->attendance;
    $payment = $r->payment;
    $isPast = $rs->screening->event_date->lt(today());

    $state = match (true) {
        $r->status === 'cancelled' => 'cancelled',
        (bool) $att => 'admitted',
        $r->status === 'pending' => 'pending',
        $isPast => 'no-show',
        default => 'to-admit',
    };
    $search = mb_strtolower(implode(' ', [$a?->full_name, $r->booking_reference, $rs->seat->seat_label, $r->lead_full_name, $r->lead_email]));
@endphp
<tr data-row id="seat-{{ $rs->reservation_seat_id }}" data-state="{{ $state }}" data-search="{{ $search }}" @class(['is-muted' => $state === 'cancelled'])>
    <td class="admit-cell">
        @if ($att)
            <span class="admitted-mark" title="Admitted"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg><span class="sr-only">Admitted</span></span>
        @elseif (Gate::allows('checkIn', [App\Models\Attendance::class, $rs]))
            <form method="POST" action="{{ route('staff.attendances.store', $rs) }}" data-ajax>
                @csrf
                <button type="submit" class="btn btn--success btn--sm" aria-label="Admit {{ $a?->full_name }}, seat {{ $rs->seat->seat_label }}">Admit</button>
            </form>
        @elseif ($r->status === 'pending' && Gate::allows('confirm', $r))
            <form method="POST" action="{{ route('staff.reservations.confirm', $r) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn--ghost btn--sm" title="Approve this free booking and email the e-ticket">Approve</button>
            </form>
        @endif
    </td>
    <td>
        <div class="cell-title">{{ $a?->full_name ?? '—' }}</div>
        <div class="cell-sub">
            @if ($a?->is_lead_reserver) Booker @endif
            @if ($a?->pwd_indicator) · PWD @endif
            @if ($a?->senior_card_no) · Senior @endif
        </div>
    </td>
    <td><span class="badge badge--seat badge--plain">{{ $rs->seat->seat_label }}</span></td>
    <td>
        <a href="{{ route('staff.reservations.show', $r) }}" style="font-weight:600;letter-spacing:.02em">{{ $r->booking_reference }}</a>
        @unless ($a?->is_lead_reserver)<div class="cell-sub">by {{ $r->lead_full_name }}</div>@endunless
    </td>
    <td>
        @if ($r->status === 'confirmed')
            <x-status value="approved" />
        @else
            <x-status :value="$r->status" />
        @endif
    </td>
    <td>
        @if (! $payment)
            <span class="badge badge--neutral badge--plain">Free</span>
        @elseif ($payment->isPaid())
            <x-status value="paid" />
        @else
            <x-status value="unpaid" />
        @endif
    </td>
    <td>
        @switch($state)
            @case('admitted')
                <x-status value="admitted" />
                <div class="attend-meta">{{ $att->checked_in_at->format('g:i A') }} · {{ $att->checkedInBy->first_name }}</div>
                @break
            @case('no-show')
                <x-status value="no-show" />
                @break
            @case('pending')
                <span class="muted small">{{ $payment ? 'Awaiting payment' : 'Awaiting approval' }}</span>
                @break
            @case('cancelled')
                <span class="muted small">—</span>
                @break
            @default
                <span class="muted small">Not arrived yet</span>
        @endswitch
    </td>
    <td class="actions">
        @if ($att)
            <details class="note-pop">
                <summary class="btn btn--text btn--sm" title="Remarks">{{ $att->remarks ? 'Note ●' : 'Note' }}</summary>
                <div class="note-pop__panel">
                    <form method="POST" action="{{ route('staff.attendances.update', $att) }}" data-ajax>
                        @csrf @method('PATCH')
                        <div class="field">
                            <label for="rm{{ $att->attendance_id }}">Remarks</label>
                            <textarea id="rm{{ $att->attendance_id }}" name="remarks" rows="3" placeholder="e.g. arrived late; different person than declared">{{ $att->remarks }}</textarea>
                        </div>
                        <button type="submit" class="btn btn--primary btn--sm">Save</button>
                    </form>
                </div>
            </details>
            <form class="inline-form" method="POST" action="{{ route('staff.attendances.destroy', $att) }}" data-ajax
                  data-confirm="Undo the admission for {{ $a?->full_name }} (seat {{ $rs->seat->seat_label }})?" data-confirm-label="Undo admission">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn--text btn--sm">Undo</button>
            </form>
        @endif
    </td>
</tr>
