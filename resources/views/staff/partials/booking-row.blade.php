{{--
    One booking as a scannable list row: who · which screening · state, with the action that
    usually comes next (approve a free booking) right on the row. The whole row opens the booking.
    Expects $r (Reservation with screening, payment, reservation_seats_count). Optional $showScreening, and
    $showState (off where the list heading already says the state, e.g. "To approve").
--}}
@php
    [$stateLabel, $tone] = $r->staffState();
    $showScreening = $showScreening ?? true;
    $showState = $showState ?? true;
@endphp
<li class="row" data-href="{{ route('staff.reservations.show', $r) }}">
    <div class="row__main">
        <a class="row__title" href="{{ route('staff.reservations.show', $r) }}">{{ $r->lead_full_name }}</a>
        <span class="row__meta">
            <span class="ref">{{ $r->booking_reference }}</span>
            · {{ $r->reservation_seats_count }} {{ Str::plural('seat', $r->reservation_seats_count) }}
            @if ($r->payment) · ₱{{ number_format($r->payment->amount, 0) }} @endif
            @if ($showScreening)
                · {{ $r->screening->event_title }}, {{ $r->screening->event_date->format('M j') }}
            @endif
        </span>
    </div>
    @if ($showState)<span class="state state--{{ $tone }}">{{ $stateLabel }}</span>@endif
    <div class="row__actions">
        @can('confirm', $r)
            <form method="POST" action="{{ route('staff.reservations.confirm', $r) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn--sm btn--primary" title="Approve and email the e-ticket">Approve</button>
            </form>
        @endcan
        <span class="row__time" title="{{ $r->reservation_datetime->format('M j, Y g:i A') }}">{{ $r->reservation_datetime->diffForHumans(['short' => true]) }}</span>
    </div>
</li>
