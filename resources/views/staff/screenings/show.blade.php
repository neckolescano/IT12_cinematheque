@extends('layouts.staff')

@section('title', $screening->event_title)

@section('content')
    {{-- Header: everything about this screening at a glance --}}
    <div class="page-head">
        <div class="cluster" style="gap:16px;align-items:flex-start;flex-wrap:nowrap">
            <x-date-badge :date="$screening->event_date" />
            <div>
                <a class="crumb" href="{{ route('staff.screenings.index') }}">&larr; Screenings</a>
                <h1>{{ $screening->event_title }}</h1>
                <div class="cluster" style="margin-top:6px">
                    <span class="muted">{{ $screening->event_date->format('l, F j, Y') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}–{{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</span>
                    @if ($screening->isPaid())
                        <span class="badge badge--brand badge--plain">Paid · ₱{{ number_format($screening->price, 2) }}</span>
                    @else
                        <span class="badge badge--success badge--plain">Free</span>
                    @endif
                    @if ($screening->movie)<span class="badge badge--neutral badge--plain">{{ $screening->movie->title }}</span>@endif
                </div>
            </div>
        </div>
        <div class="cluster">
            @if (! $screening->isPaid() && $stats['pending'] > 0)
                <form class="inline-form" method="POST" action="{{ route('staff.screenings.approve-pending', $screening) }}"
                      data-confirm="Approve all {{ $stats['pending'] }} pending reservation(s) and email their e-tickets?" data-confirm-label="Approve all">
                    @csrf
                    <button type="submit" class="btn btn--success">Approve all pending ({{ $stats['pending'] }})</button>
                </form>
            @endif
            <a class="btn btn--ghost" href="{{ route('screenings.show', $screening) }}" target="_blank" rel="noopener">Public page ↗</a>
            @can('update', $screening)
                <a class="btn btn--ghost" href="{{ route('staff.screenings.edit', $screening) }}" data-open-dialog="edit-screening">Edit</a>
            @endcan
            @can('delete', $screening)
                <form class="inline-form" method="POST" action="{{ route('staff.screenings.destroy', $screening) }}"
                      data-confirm="Delete “{{ $screening->event_title }}”? This cannot be undone." data-confirm-label="Delete screening">
                    @csrf @method('DELETE')
                    <button class="btn btn--danger" type="submit">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat">
            <div class="stat__l">Seats reserved</div>
            <div class="stat__n">{{ $stats['reserved'] }} <span class="muted" style="font-size:15px;font-weight:500">/ {{ $screening->total_seats }}</span></div>
            <div class="progress"><span style="width:{{ $screening->total_seats ? min(100, $stats['reserved'] / $screening->total_seats * 100) : 0 }}%"></span></div>
        </div>
        <div class="stat stat--ok">
            <div class="stat__l">Admitted</div>
            <div class="stat__n"><span data-admitted-count>{{ $stats['admitted'] }}</span> <span class="muted" style="font-size:15px;font-weight:500">/ {{ $stats['reserved'] }}</span></div>
            <div class="progress progress--ok"><span data-admitted-bar data-total="{{ $stats['reserved'] }}" style="width:{{ $stats['reserved'] ? min(100, $stats['admitted'] / $stats['reserved'] * 100) : 0 }}%"></span></div>
        </div>
        <div @class(['stat', 'stat--warn' => $stats['pending'] > 0])>
            <div class="stat__l">{{ $screening->isPaid() ? 'Awaiting payment' : 'Awaiting approval' }}</div>
            <div class="stat__n">{{ $stats['pending'] }}</div>
            <div class="stat__sub">of {{ $stats['bookings'] }} {{ Str::plural('booking', $stats['bookings']) }}</div>
        </div>
        <div class="stat">
            @if ($screening->isPaid())
                <div class="stat__l">Paid via PayMongo</div>
                <div class="stat__n">₱{{ number_format($stats['paid_total'], 2) }}</div>
            @else
                <div class="stat__l">Seats still available</div>
                <div class="stat__n">{{ $stats['available'] }}</div>
            @endif
        </div>
    </div>

    {{-- The unified attendee checklist --}}
    <section class="card card--flush" aria-labelledby="checklist-title">
        <div class="card__head">
            <div>
                <h2 id="checklist-title">Attendee checklist</h2>
                <div class="cell-sub">Everyone who reserved for this screening, one row per seat. Mark people only when they actually arrive.</div>
            </div>
            <a class="btn btn--ghost btn--sm" href="{{ route('staff.reservations.index', ['screening_id' => $screening->screening_id]) }}">View as bookings</a>
        </div>

        @if ($rows->isEmpty())
            <x-empty title="No reservations yet">The list fills in as moviegoers book seats.</x-empty>
        @else
            <div class="toolbar">
                <div class="segmented" role="group" aria-label="Filter attendees">
                    <button type="button" data-filter="all" aria-pressed="true">All <span class="n"></span></button>
                    <button type="button" data-filter="{{ $isPast ? 'no-show' : 'to-admit' }}" aria-pressed="false">{{ $isPast ? 'No-show' : 'To admit' }} <span class="n"></span></button>
                    <button type="button" data-filter="admitted" aria-pressed="false">Admitted <span class="n"></span></button>
                    <button type="button" data-filter="pending" aria-pressed="false">Pending <span class="n"></span></button>
                    <button type="button" data-filter="cancelled" aria-pressed="false">Cancelled <span class="n"></span></button>
                </div>
                <label class="search">
                    <span class="sr-only">Find attendee</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" data-filter-input placeholder="Name, reference or seat" autocomplete="off">
                </label>
            </div>
            <div class="table-wrap">
                <table class="table checklist">
                    <thead>
                    <tr><th><span class="sr-only">Admit</span>✓</th><th>Name</th><th>Seat</th><th>Booking</th><th>Reservation</th><th>Payment</th><th>Attendance</th><th><span class="sr-only">More</span></th></tr>
                    </thead>
                    <tbody data-checklist>
                    @foreach ($rows as $rs)
                        @include('staff.screenings._attendee-row', ['rs' => $rs])
                    @endforeach
                    </tbody>
                </table>
                <div data-no-match hidden><x-empty title="No one matches" icon="doc">Try another name, reference or filter.</x-empty></div>
            </div>
        @endif
    </section>

    <p class="muted small" style="margin-top:16px">Created by {{ $screening->creator->full_name }} ({{ $screening->creator->position ?? 'Staff' }}).</p>
@endsection

@push('dialogs')
    @can('update', $screening)
        @include('staff.screenings._drawer', ['screening' => $screening])
    @endcan
@endpush
