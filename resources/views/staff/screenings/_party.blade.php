{{--
    One reservation ("party") in the attendance table: a summary row, then one row per attendee.
    Party of 1: admitted straight from the summary row ("Admit").
    Party of 2+: "Admit party" opens the attendee rows with everyone ticked; staff untick anyone
    who isn't here and confirm ("Admit 3"). The same ticks admit just some of the party.
    Admitted attendees get Note and Undo in their Actions cell.
    Rendered on page load and returned (JSON "party") after every admission change.
    Expects $r with reservationSeats.{seat,attendee,attendance.checkedInBy}; optional $open.
--}}
@php
    $seats = $r->reservationSeats->sortBy(fn ($s) => [$s->attendee?->is_lead_reserver ? 0 : 1, $s->seat_id])->values();
    $size = $seats->count();
    $in = $seats->filter(fn ($s) => $s->attendance)->count();
    $isPast = $r->screening->event_date->lt(today());
    $state = match (true) {
        $r->status === 'cancelled' => 'cancelled',
        $r->status === 'pending' => 'pending',
        $in === $size => 'admitted',
        $isPast => 'no-show',
        default => 'to-admit',
    };
    [$stateLabel, $tone] = $r->staffState();
    $admittable = $seats->filter(fn ($s) => Gate::allows('checkIn', [App\Models\Attendance::class, $s]));
    $flags = collect([
        $seats->filter(fn ($s) => $s->attendee?->senior_card_no)->count() ? 'Senior' : null,
        $seats->filter(fn ($s) => $s->attendee?->pwd_indicator || $s->attendee?->pwd_id_no)->count() ? 'PWD' : null,
    ])->filter();
    $search = mb_strtolower(collect([$r->lead_full_name, $r->booking_reference, $r->lead_email])
        ->merge($seats->map(fn ($s) => $s->attendee?->full_name.' '.$s->seat->seat_label))->join(' '));
    $single = $size === 1 ? $seats->first() : null;
    $formId = 'admit-'.$r->reservation_id;
@endphp
<tbody data-party="{{ $r->reservation_id }}" data-state="{{ $state }}" data-search="{{ $search }}" @class(['party', 'is-open' => $open ?? false, 'is-muted' => $state === 'cancelled'])>
    <tr class="party__row">
        <td>
            <div class="party__who">
                @if ($size > 1)
                    <button type="button" class="party__toggle" data-party-toggle aria-expanded="{{ ($open ?? false) ? 'true' : 'false' }}" aria-label="Show the {{ $size }} attendees">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                @endif
                <div>
                    <div class="cell-title">
                        {{ $r->lead_full_name }}@if ($size > 1)<span class="party__size"> · Party of {{ $size }}</span>@endif
                    </div>
                    <div class="cell-sub">
                        <a href="{{ route('staff.reservations.show', $r) }}" class="ref">{{ $r->booking_reference }}</a>
                        @if ($single && $single->attendee && $single->attendee->full_name !== $r->lead_full_name) · {{ $single->attendee->full_name }} @endif
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
                @if ($r->status === 'pending' && Gate::allows('confirm', $r))
                    <form method="POST" action="{{ route('staff.reservations.confirm', $r) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn--primary btn--sm" title="Approve this free booking and email the e-ticket">Approve</button>
                    </form>
                @elseif ($single)
                    @include('staff.screenings._attendee-actions', ['s' => $single, 'admittable' => $admittable->isNotEmpty()])
                @elseif ($admittable->isNotEmpty())
                    <button type="button" class="btn btn--primary btn--sm" data-party-admit>Admit party</button>
                @endif
            </div>
        </td>
    </tr>

    @if ($size > 1)
        @foreach ($seats as $s)
            @php($a = $s->attendee)
            <tr class="party__member" data-seat-state="{{ $s->attendance ? 'admitted' : 'out' }}">
                <td>
                    <div class="party__who party__who--member">
                        @if ($admittable->contains($s))
                            <input type="checkbox" class="party__pick" name="seats[]" value="{{ $s->reservation_seat_id }}" form="{{ $formId }}" checked
                                   aria-label="Admit {{ $a?->full_name }}, seat {{ $s->seat->seat_label }}">
                        @else
                            <span class="party__pick-spacer" aria-hidden="true"></span>
                        @endif
                        <span>
                            {{ $a?->full_name ?? '—' }}
                            @if ($a?->is_lead_reserver)<span class="muted small"> · Booker</span>@endif
                            @if ($a?->senior_card_no)<b class="small"> · Senior</b>@endif
                            @if ($a?->pwd_indicator || $a?->pwd_id_no)<b class="small"> · PWD</b>@endif
                        </span>
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
                <td class="actions"><div class="row-actions">@if ($s->attendance)@include('staff.screenings._attendee-actions', ['s' => $s, 'admittable' => false])@endif</div></td>
            </tr>
        @endforeach
        @if ($admittable->isNotEmpty())
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
    @endif
</tbody>
