@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">{{ now()->format('l, F j') }}</span>
            <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}</h1>
        </div>
        <a class="btn btn--primary" href="{{ route('staff.screenings.create') }}">+ New screening</a>
    </div>

    @unless ($hasActiveQr)
        <div class="alert alert--warning">
            <div><strong>No active payment QR code.</strong> Moviegoers with paid bookings can't pay yet. <a href="{{ route('staff.qr-codes.index') }}">Upload one &rarr;</a></div>
        </div>
    @endunless

    <div class="stat-grid">
        <a class="stat reveal" data-stagger href="{{ route('staff.payment-proofs.index') }}">
            <div class="stat__n {{ $pendingProofCount ? '' : '' }}" style="{{ $pendingProofCount ? 'color:var(--warning)' : '' }}">{{ $pendingProofCount }}</div>
            <div class="stat__l">Payment proofs awaiting review</div>
        </a>
        <a class="stat reveal" data-stagger href="{{ route('staff.reservations.index', ['status' => 'pending']) }}">
            <div class="stat__n">{{ $pendingReservationCount }}</div>
            <div class="stat__l">Pending reservations</div>
        </a>
        <a class="stat reveal" data-stagger href="{{ route('staff.screenings.index') }}">
            <div class="stat__n">{{ $upcomingCount }}</div>
            <div class="stat__l">Upcoming screenings</div>
        </a>
        <a class="stat reveal" data-stagger href="{{ route('staff.reports.index') }}">
            <div class="stat__n">{{ $todayScreenings->sum('reservation_seats_count') }}</div>
            <div class="stat__l">Seats reserved for today</div>
        </a>
    </div>

    <section class="card reveal">
        <div class="card__head">
            <h2>Today's screenings</h2>
            <span class="muted small">Open a screening for its check-in list</span>
        </div>
        @if ($todayScreenings->isEmpty())
            <x-empty title="No screenings today">Upcoming programmes are listed under Screenings.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Time</th><th>Event</th><th>Type</th><th class="num">Reserved</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($todayScreenings as $screening)
                        <tr>
                            <td>{{ substr($screening->start_time, 0, 5) }}–{{ substr($screening->end_time, 0, 5) }}</td>
                            <td style="font-weight:600">{{ $screening->event_title }}</td>
                            <td><span class="badge badge--plain {{ $screening->isPaid() ? 'badge--gold' : 'badge--success' }}">{{ $screening->isPaid() ? 'Paid' : 'Free' }}</span></td>
                            <td class="num">{{ $screening->reservation_seats_count }} / {{ $screening->total_seats }}</td>
                            <td class="actions"><a class="btn btn--dark btn--sm" href="{{ route('staff.screenings.show', $screening) }}">Check-in list</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
