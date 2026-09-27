@extends('layouts.staff')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}</h1>
            <p>{{ now()->format('l, F j, Y') }}</p>
        </div>
        <a class="btn btn--primary" href="{{ route('staff.screenings.create') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New screening
        </a>
    </div>

    @unless ($paymentsEnabled)
        <div class="alert alert--warning"><div><strong>PayMongo is not configured.</strong> Customers can reserve paid screenings but cannot pay yet. Set <code>PAYMONGO_SECRET_KEY</code> in <code>.env</code>.</div></div>
    @endunless
    @unless ($mailConfigured)
        <div class="alert alert--warning"><div><strong>Email is not configured.</strong> Bookings still work, but e-tickets can't be sent. Set the <code>MAIL_*</code> values in <code>.env</code>.</div></div>
    @endunless

    <div class="stat-grid">
        <a class="stat" href="{{ route('staff.screenings.index') }}">
            <div class="stat__l">Upcoming screenings</div>
            <div class="stat__n">{{ $stats['upcoming'] }}</div>
        </a>
        <a @class(['stat', 'stat--warn' => $stats['awaiting_approval']]) href="{{ route('staff.reservations.index', ['status' => 'pending', 'payment' => 'free']) }}">
            <div class="stat__l">Free bookings to approve</div>
            <div class="stat__n">{{ $stats['awaiting_approval'] }}</div>
        </a>
        <a class="stat" href="{{ route('staff.reservations.index', ['status' => 'pending', 'payment' => 'unpaid']) }}">
            <div class="stat__l">Awaiting PayMongo payment</div>
            <div class="stat__n">{{ $stats['awaiting_payment'] }}</div>
        </a>
        <a class="stat" href="{{ route('staff.reports.index') }}">
            <div class="stat__l">Paid in the last 7 days</div>
            <div class="stat__n">₱{{ number_format($stats['paid_7d'], 2) }}</div>
        </a>
    </div>

    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));align-items:start">
        <section class="card card--flush">
            <div class="card__head"><h2>Today · admission</h2><span class="cell-sub">Open a screening to admit people</span></div>
            @if ($today->isEmpty())
                <x-empty title="No screenings today" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                        @foreach ($today as $s)
                            <tr data-href="{{ route('staff.screenings.show', $s) }}">
                                <td style="width:1%;white-space:nowrap" class="cell-sub">{{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }}</td>
                                <td><a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_title }}</a></td>
                                <td style="min-width:170px">
                                    <div class="meter">
                                        <div class="progress progress--ok"><span style="width:{{ $s->reserved_count ? min(100, $s->admitted_count / $s->reserved_count * 100) : 0 }}%"></span></div>
                                        <b>{{ $s->admitted_count }}/{{ $s->reserved_count }} in</b>
                                    </div>
                                </td>
                                <td class="actions"><a class="btn btn--success btn--sm" href="{{ route('staff.screenings.show', $s) }}">Admit</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card card--flush">
            <div class="card__head"><h2>Next 14 days</h2><a class="btn btn--ghost btn--sm" href="{{ route('staff.screenings.index') }}">All screenings</a></div>
            @if ($upcoming->isEmpty())
                <x-empty title="Nothing scheduled in the next two weeks" />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <tbody>
                        @foreach ($upcoming as $s)
                            <tr data-href="{{ route('staff.screenings.show', $s) }}">
                                <td style="width:1%"><x-date-badge :date="$s->event_date" /></td>
                                <td>
                                    <a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_title }}</a>
                                    <div class="cell-sub">{{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }} · {{ $s->isPaid() ? '₱'.number_format($s->price, 2) : 'Free' }}</div>
                                </td>
                                <td class="num" style="white-space:nowrap">{{ $s->reserved_count }}/{{ $s->total_seats }}</td>
                                <td class="num">@if ($s->pending_count)<span class="badge badge--warning">{{ $s->pending_count }} pending</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="card card--flush" style="margin-top:20px">
        <div class="card__head"><h2>Latest bookings</h2><a class="btn btn--ghost btn--sm" href="{{ route('staff.reservations.index') }}">All reservations</a></div>
        @if ($recent->isEmpty())
            <x-empty title="No bookings yet" icon="doc" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Reference</th><th>Booked by</th><th>Screening</th><th class="num">Seats</th><th>Reservation</th><th>Payment</th><th>When</th></tr></thead>
                    <tbody>
                    @foreach ($recent as $r)
                        <tr data-href="{{ route('staff.reservations.show', $r) }}">
                            <td><a style="font-weight:600" href="{{ route('staff.reservations.show', $r) }}">{{ $r->booking_reference }}</a></td>
                            <td>{{ $r->lead_full_name }}</td>
                            <td class="cell-sub">{{ $r->screening->event_title }}</td>
                            <td class="num">{{ $r->reservation_seats_count }}</td>
                            <td><x-status :value="$r->status === 'confirmed' ? 'approved' : $r->status" /></td>
                            <td>@if ($r->payment)<x-status :value="$r->payment->isPaid() ? 'paid' : 'unpaid'" />@else<span class="badge badge--neutral badge--plain">Free</span>@endif</td>
                            <td class="cell-sub">{{ $r->reservation_datetime->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
