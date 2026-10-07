@extends('layouts.staff')

@section('title', 'Reservations')

@section('content')
    @php
        $view = $filters['view'] ?? 'all';
        $keep = array_filter(['q' => $filters['q'] ?? null, 'screening_id' => $filters['screening_id'] ?? null]);
    @endphp

    <header class="page-head">
        <div>
            <h1>Reservations</h1>
        </div>
    </header>

    {{-- Quick filters (one click) + a search and screening picker that apply as you change them --}}
    <form class="filterbar" method="GET" action="{{ route('staff.reservations.index') }}" data-no-loading>
        <nav class="chips" aria-label="Show">
            @foreach (App\Http\Controllers\Staff\ReservationController::VIEWS as $key => $label)
                <a href="{{ route('staff.reservations.index', $keep + ($key === 'all' ? [] : ['view' => $key])) }}" @if ($view === $key) aria-current="page" @endif>
                    {{ $label }} <span class="n">{{ $counts[$key] }}</span>
                </a>
            @endforeach
        </nav>
        @if ($view !== 'all')<input type="hidden" name="view" value="{{ $view }}">@endif
        <div class="filterbar__inputs">
            <label class="search">
                <span class="sr-only">Search reservations</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Reference, name, email or phone">
            </label>
            <label>
                <span class="sr-only">Screening</span>
                <select name="screening_id" data-autosubmit>
                    <option value="">All screenings</option>
                    @foreach ($screenings as $s)
                        <option value="{{ $s->screening_id }}" @selected(($filters['screening_id'] ?? null) == $s->screening_id)>{{ $s->event_date->format('M j') }} · {{ $s->event_title }}</option>
                    @endforeach
                </select>
            </label>
            @if ($keep)<a class="btn btn--text btn--sm" href="{{ route('staff.reservations.index', $view === 'all' ? [] : ['view' => $view]) }}">Clear</a>@endif
        </div>
    </form>

    @if ($reservations->isEmpty())
        <x-empty :title="$keep ? 'No matches' : 'No reservations'" icon="doc" />
    @else
        {{-- Who, for what, how many, money, state, when; the next action on the right. --}}
        <div class="table-wrap">
            <table class="table reservations">
                <thead>
                <tr>
                    <th>Reservation</th><th>Screening</th><th>Party</th><th class="num">Amount</th>
                    <th>Status</th><th>Booked</th><th class="actions">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($reservations as $r)
                    @php
                        [$stateLabel, $tone] = $r->staffState();
                        $labels = $r->reservationSeats->sortBy('seat_id')->map(fn ($s) => $s->seat->seat_label);
                    @endphp
                    <tr data-href="{{ route('staff.reservations.show', $r) }}" @class(['is-muted' => $r->status === 'cancelled'])>
                        <td>
                            <a class="cell-title link-quiet" href="{{ route('staff.reservations.show', $r) }}">{{ $r->lead_full_name }}</a>
                            <div class="cell-sub ref">{{ $r->booking_reference }}</div>
                        </td>
                        <td>
                            <div>{{ $r->screening->event_title }}</div>
                            <div class="cell-sub">{{ $r->screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($r->screening->start_time)->format('g:i A') }}</div>
                        </td>
                        <td>
                            <div>{{ $r->reservation_seats_count }} {{ Str::plural('person', $r->reservation_seats_count) }}</div>
                            <div class="cell-sub mono-seats">{{ $labels->take(6)->join(', ') }}{{ $labels->count() > 6 ? ' …' : '' }}</div>
                        </td>
                        <td class="num">{{ $r->payment ? '₱'.number_format($r->payment->amount, 0) : 'Free' }}</td>
                        <td><span class="state state--{{ $tone }}">{{ $stateLabel }}</span></td>
                        <td class="nowrap cell-sub" title="{{ $r->reservation_datetime->format('M j, Y g:i A') }}">{{ $r->reservation_datetime->format('M j, g:i A') }}</td>
                        <td class="actions">
                            @can('confirm', $r)
                                <form method="POST" action="{{ route('staff.reservations.confirm', $r) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn--sm btn--primary" title="Approve and email the e-ticket">Approve</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $reservations->links() }}</div>
    @endif
@endsection
