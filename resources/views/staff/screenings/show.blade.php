@extends('layouts.staff')

@section('title', $screening->event_title)

@section('content')
    <div class="page-head">
        <div class="cluster" style="gap:var(--s-4);align-items:flex-start;flex-wrap:nowrap">
            <x-date-badge :date="$screening->event_date" />
            <div>
                <a class="crumb" style="color:var(--muted);margin-bottom:var(--s-1)" href="{{ route('staff.screenings.index') }}">&larr; Screenings</a>
                <h1>{{ $screening->event_title }}</h1>
                <div class="muted small">{{ $screening->event_date->format('l, F j, Y') }} · {{ substr($screening->start_time, 0, 5) }}–{{ substr($screening->end_time, 0, 5) }}</div>
            </div>
        </div>
        <div class="cluster">
            <a class="btn btn--ghost btn--sm" href="{{ route('screenings.show', $screening) }}">Public page</a>
            @can('update', $screening)
                <a class="btn btn--dark btn--sm" href="{{ route('staff.screenings.edit', $screening) }}">Edit</a>
            @endcan
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat reveal" data-stagger><div class="stat__n">{{ $screening->total_seats - $screening->availableSeatCount() }}</div><div class="stat__l">Seats reserved of {{ $screening->total_seats }}</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n" style="color:var(--success)">{{ $checkedIn }}</div><div class="stat__l">Checked in</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n">{{ $screening->availableSeatCount() }}</div><div class="stat__l">Seats available</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n" style="font-size:1.25rem;padding-top:.5rem">{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2) : 'Free' }}</div><div class="stat__l">{{ $screening->movie?->title ?? 'No cataloged movie' }}</div></div>
    </div>

    <section class="card reveal">
        <div class="card__head">
            <h2>Reservations</h2>
            <a class="btn btn--ghost btn--sm" href="{{ route('staff.reservations.index', ['screening_id' => $screening->screening_id]) }}">Search &amp; filter</a>
        </div>
        @if ($screening->reservations->isEmpty())
            <x-empty title="No reservations yet">They'll appear here as moviegoers book.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Booked by</th><th class="num">Seats</th><th>Reservation</th><th>Payment</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($screening->reservations as $reservation)
                        <tr>
                            <td style="font-weight:600;letter-spacing:.03em">{{ $reservation->booking_reference }}</td>
                            <td>{{ $reservation->lead_full_name }}<div class="muted small">{{ $reservation->lead_contact_no }}</div></td>
                            <td class="num">{{ $reservation->reservation_seats_count }}</td>
                            <td><x-status :value="$reservation->status" /></td>
                            <td>@if ($reservation->payment)<x-status :value="$reservation->payment->status" />@else<span class="muted">—</span>@endif</td>
                            <td class="actions"><a class="btn btn--dark btn--sm" href="{{ route('staff.reservations.show', $reservation) }}">Open / check in</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <p class="muted small" style="margin-top:var(--s-4)">Created by {{ $screening->creator->full_name }} ({{ $screening->creator->position ?? '—' }}).</p>
@endsection
