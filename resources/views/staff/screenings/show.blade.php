@extends('layouts.staff')

@section('title', $screening->event_title)

@section('content')
    {{-- Header: the screening, its numbers in one line, and its actions --}}
    <header class="page-head">
        <div>
            <a class="back-link" href="{{ route('staff.screenings.index') }}"><x-arrow dir="left" /> Attendance</a>
            <span class="eyebrow">
                {{ $screening->event_date->isToday() ? 'Today' : $screening->event_date->format('l') }} · {{ $screening->event_date->format('F j, Y') }}
                · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}
                · {{ $screening->isPaid() ? '₱'.number_format($screening->price, 0).' per seat' : 'Free' }}
            </span>
            <h1>{{ $screening->event_title }}</h1>
            <p class="figures">
                <span><b>{{ $stats['reserved'] }}</b> / {{ $screening->total_seats }} seats booked</span>
                <span><b data-admitted-count>{{ $stats['admitted'] }}</b> admitted</span>
                @if ($stats['pending'])<span class="figures__warn"><b>{{ $stats['pending'] }}</b> {{ $screening->isPaid() ? 'awaiting payment' : 'to approve' }}</span>@endif
                @if ($screening->isPaid())<span><b>₱{{ number_format($stats['paid_total'], 0) }}</b> paid</span>@endif
                @if ($screening->movie && $screening->movie->title !== $screening->event_title)<span>Film: {{ $screening->movie->title }}</span>@endif
            </p>
        </div>
        <div class="cluster">
            @if (! $screening->isPaid() && $stats['pending'] > 0)
                <form class="inline-form" method="POST" action="{{ route('staff.screenings.approve-pending', $screening) }}"
                      data-confirm="Approve {{ $stats['pending'] }} pending {{ Str::plural('booking', $stats['pending']) }} and email the e-tickets?" data-confirm-label="Approve all">
                    @csrf
                    <button type="submit" class="btn btn--secondary">Approve all pending ({{ $stats['pending'] }})</button>
                </form>
            @endif
            @can('update', $screening)
                <a class="btn btn--secondary" href="{{ route('staff.screenings.edit', $screening) }}">Edit screening</a>
            @endcan
            <details class="more">
                <summary class="btn btn--secondary" aria-label="More actions">More</summary>
                <div class="more__panel">
                    <a href="{{ route('screenings.show', $screening) }}" target="_blank" rel="noopener">Public page</a>
                    <a href="{{ route('staff.reservations.index', ['screening_id' => $screening->screening_id]) }}">Reservations list</a>
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
                    <button type="button" data-filter="pending" aria-pressed="false">Pending <span class="n"></span></button>
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
