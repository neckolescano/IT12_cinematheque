@extends('layouts.staff')

@section('title', $screening->event_title)

@section('content')
    {{-- Header: the screening, its numbers in one line, and its actions --}}
    <header class="page-head">
        <div>
            <a class="back-link" href="{{ route('staff.screenings.index') }}"><x-arrow dir="left" /> Screenings</a>
            <span class="eyebrow">
                {{ $screening->event_date->isToday() ? 'Today' : $screening->event_date->format('l') }} · {{ $screening->event_date->format('F j, Y') }}
                · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}
                · {{ $screening->isPaid() ? '₱'.number_format($screening->price, 0).' per seat' : 'Free' }}
            </span>
            <h1>{{ $screening->event_title }} @if ($screening->isDraft())<span class="state state--warning">Draft · hidden from customers</span>@endif</h1>
            <p class="figures">
                <span><b>{{ $stats['reserved'] }}</b> / {{ $screening->total_seats }} seats booked</span>
                <span><b data-admitted-count>{{ $stats['admitted'] }}</b> admitted</span>
                @if ($stats['pending'])<span class="figures__warn"><b>{{ $stats['pending'] }}</b> awaiting payment</span>@endif
                @if ($screening->isPaid())<span><b>₱{{ number_format($stats['paid_total'], 0) }}</b> paid</span>@endif
                <span>Film: <a href="{{ route('staff.movies.show', $screening->movie) }}">{{ $screening->movie->title }}</a></span>
                <span>Program: {{ $screening->program->name }}</span>
            </p>
        </div>
        <div class="cluster">
            @can('update', $screening)
                <a class="btn btn--secondary" href="{{ route('staff.screenings.edit', $screening) }}">Edit screening</a>
                @if ($screening->isDraft())
                    <a class="btn btn--secondary" href="{{ route('staff.screenings.preview', $screening) }}">Review</a>
                    <form method="POST" action="{{ route('staff.screenings.publish', $screening) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary">Publish</button>
                    </form>
                @endif
            @endcan
            <details class="more">
                <summary class="btn btn--secondary" aria-label="More actions">More</summary>
                <div class="more__panel">
                    @if ($screening->isDraft())
                        <a href="{{ route('staff.screenings.preview', $screening) }}">Preview customer page</a>
                    @else
                        <a href="{{ route('screenings.show', $screening) }}" target="_blank" rel="noopener">Public page</a>
                    @endif
                    @can('delete', $screening)
                        <form method="POST" action="{{ route('staff.screenings.destroy', $screening) }}" data-confirm-danger
                              data-confirm="Delete “{{ $screening->event_title }}”? This cannot be undone." data-confirm-label="Delete screening">
                            @csrf @method('DELETE')
                            <button class="danger" type="submit">Delete screening</button>
                        </form>
                    @endcan
                </div>
            </details>
        </div>
    </header>

    {{-- Door check-in window: a reservation roster until 20 minutes before the start, check-in until 60 minutes
         after the end. The page reloads itself when the window opens or closes (timed by the server clock). --}}
    @php
        $checkIn = $screening->checkInState();
        $switchAt = $checkIn === 'not_yet' ? $screening->checkInOpensAt() : ($checkIn === 'open' ? $screening->checkInClosesAt() : null);
        $reloadIn = $switchAt ? (int) now()->diffInSeconds($switchAt, false) : null;
        $super = auth()->user()->isSuperAdmin();
    @endphp
    <p id="checkin-status" class="checkin-bar checkin-bar--{{ $checkIn === 'not_yet' ? 'soon' : $checkIn }}" role="status"
       @if ($reloadIn !== null && $reloadIn > 0 && $reloadIn < 86400) data-reload-in="{{ $reloadIn }}" @endif>
        @if ($checkIn === 'not_yet')
            <strong>Reservation roster.</strong>
            <span>Check-in opens at {{ $screening->checkInOpensAt()->format('g:i A') }}{{ $screening->event_date->isToday() ? '' : ' on '.$screening->event_date->format('M j') }}.@if ($super) As Super Admin you can admit now.@endif</span>
        @elseif ($checkIn === 'open')
            <strong>Check-in is open</strong>
            <span>until {{ $screening->checkInClosesAt()->format('g:i A') }}.</span>
        @else
            <strong>Check-in closed</strong>
            <span>at {{ $screening->checkInClosesAt()->format('M j, g:i A') }}.@if ($super) As Super Admin you can still correct this roster.@endif</span>
        @endif
    </p>

    {{-- One row per reservation (party); its attendees open beneath it. --}}
    <section aria-label="Reservations">
        @if ($parties->isEmpty())
            <x-empty title="No reservations yet" />
        @else
            <div class="filterbar">
                <div class="chips" role="group" aria-label="Show">
                    <button type="button" data-filter="all" aria-pressed="true">All <span class="n"></span></button>
                    <button type="button" data-filter="{{ $isPast ? 'no-show' : 'to-admit' }}" aria-pressed="false">{{ $isPast ? 'No-show' : 'To admit' }} <span class="n"></span></button>
                    <button type="button" data-filter="admitted" aria-pressed="false">Admitted <span class="n"></span></button>
                    @if ($screening->isPaid())<button type="button" data-filter="pending" aria-pressed="false">Awaiting payment <span class="n"></span></button>@endif
                    <button type="button" data-filter="cancelled" aria-pressed="false">Cancelled <span class="n"></span></button>
                </div>
                <div class="filterbar__inputs">
                    <label class="search">
                        <span class="sr-only">Find a reservation</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input type="search" data-filter-input placeholder="Name, reference or seat" autocomplete="off">
                    </label>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table attendance" data-checklist>
                    <thead>
                    <tr><th>Reservation</th><th>Seats</th><th>Status</th><th>Admitted</th><th class="actions">Actions</th></tr>
                    </thead>
                    @foreach ($parties as $r)
                        @include('staff.screenings._party', ['r' => $r])
                    @endforeach
                </table>
                <div data-no-match hidden><x-empty title="No matches" icon="doc" /></div>
            </div>
        @endif
    </section>

    <p class="muted small" style="margin-top:16px">Scheduled by {{ $screening->creator->full_name }}.</p>
@endsection
