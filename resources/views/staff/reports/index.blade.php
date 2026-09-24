@extends('layouts.staff')

@section('title', 'Reports')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Attendance & payments</span>
            <h1>Reports</h1>
            <p class="muted small" style="margin:0">{{ \Carbon\Carbon::parse($from)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}</p>
        </div>
        <a class="btn btn--primary" href="{{ route('staff.reports.export', ['from' => $from, 'to' => $to]) }}" download>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v3h16v-3"/></svg>
            Export CSV
        </a>
    </div>

    <form class="card reveal" style="margin-bottom:var(--s-5)" method="GET" action="{{ route('staff.reports.index') }}">
        <div class="filters">
            <div class="field">
                <label for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from }}">
            </div>
            <div class="field">
                <label for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to }}">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--dark">Run report</button>
            </div>
        </div>
    </form>

    <div class="stat-grid">
        <div class="stat reveal" data-stagger><div class="stat__n">{{ $screenings->sum('reserved') }}</div><div class="stat__l">Seats reserved</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n" style="color:var(--success)">{{ $screenings->sum('attended') }}</div><div class="stat__l">Checked in</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n" style="color:var(--warning)">{{ $screenings->sum('no_shows') }}</div><div class="stat__l">No-shows (past screenings)</div></div>
        <div class="stat reveal" data-stagger><div class="stat__n">₱{{ number_format($screenings->sum('verified_amount'), 2) }}</div><div class="stat__l">Verified payments</div></div>
    </div>

    <section class="card reveal">
        <div class="card__head">
            <h2>Reserved vs. attended</h2>
            <span class="muted small">Cancelled reservations excluded · no-shows counted after the screening date</span>
        </div>
        @if ($screenings->isEmpty())
            <x-empty title="No screenings in this range" icon="doc">Try a wider date range.</x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Event</th><th class="num">Reserved</th><th class="num">Checked in</th><th class="num">No-shows</th><th class="num">Verified</th></tr></thead>
                    <tbody>
                    @foreach ($screenings as $row)
                        <tr>
                            <td class="small">{{ $row['screening']->event_date->format('M j, Y') }}</td>
                            <td><a class="link-quiet" style="font-weight:600" href="{{ route('staff.screenings.show', $row['screening']) }}">{{ $row['screening']->event_title }}</a></td>
                            <td class="num">{{ $row['reserved'] }}</td>
                            <td class="num">{{ $row['attended'] }}</td>
                            <td class="num">{{ $row['no_shows'] ?? '—' }}</td>
                            <td class="num">₱{{ number_format($row['verified_amount'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="2">Total</th>
                        <th class="num">{{ $screenings->sum('reserved') }}</th>
                        <th class="num">{{ $screenings->sum('attended') }}</th>
                        <th class="num">{{ $screenings->sum('no_shows') }}</th>
                        <th class="num">₱{{ number_format($screenings->sum('verified_amount'), 2) }}</th>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </section>

    <section class="card reveal" style="margin-top:var(--s-5)">
        <div class="card__head"><h2>Check-ins by staff member</h2></div>
        @if ($checkIns->isEmpty())
            <x-empty title="No check-ins in this range" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Staff</th><th>Position</th><th class="num">Check-ins</th></tr></thead>
                    <tbody>
                    @foreach ($checkIns as $row)
                        <tr>
                            <td>{{ $row['user']->full_name }}</td>
                            <td>{{ $row['user']->position ?? '—' }}</td>
                            <td class="num">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
