@extends('layouts.staff')

@section('title', 'Summary')

@section('content')
    @php
        $presets = [
            'Last 30 days' => [today()->subDays(30), today()],
            'This month' => [today()->startOfMonth(), today()->endOfMonth()],
            'Next 30 days' => [today(), today()->addDays(30)],
            'Last 30 + next 30' => [today()->subDays(30), today()->addDays(30)],
        ];
        $viewParam = $view === 'demographics' ? ['view' => 'demographics'] : [];
        $range = ['from' => $from, 'to' => $to];
    @endphp

    <header class="page-head">
        <div>
            <h1>Summary</h1>
            <p>{{ \Carbon\Carbon::parse($from)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($to)->format('M j, Y') }}</p>
        </div>
        <a class="btn btn--primary" href="{{ route('staff.reports.export', $range + $viewParam) }}" download>Export CSV</a>
    </header>

    <nav class="tabs" aria-label="Report">
        <a href="{{ route('staff.reports.index', $range) }}" @if ($view === 'attendance') aria-current="page" @endif>Attendance</a>
        <a href="{{ route('staff.reports.index', $range + ['view' => 'demographics']) }}" @if ($view === 'demographics') aria-current="page" @endif>Demographics</a>
    </nav>

    <form class="filterbar" method="GET" action="{{ route('staff.reports.index') }}" data-no-loading>
        @if ($viewParam)<input type="hidden" name="view" value="demographics">@endif
        <nav class="chips" aria-label="Date range">
            @foreach ($presets as $label => [$f, $t])
                <a href="{{ route('staff.reports.index', ['from' => $f->toDateString(), 'to' => $t->toDateString()] + $viewParam) }}"
                   @if ($from === $f->toDateString() && $to === $t->toDateString()) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="filterbar__inputs">
            <label class="range"><span>From</span><input type="date" name="from" value="{{ $from }}"></label>
            <label class="range"><span>To</span><input type="date" name="to" value="{{ $to }}"></label>
            <button type="submit" class="btn btn--dark btn--sm">Apply</button>
        </div>
    </form>

    <dl class="kpis" aria-label="Totals for this range">
        <div><dt>Admitted</dt><dd>{{ number_format($kpis['total']) }}</dd></div>
        <div><dt>Male</dt><dd>{{ number_format($kpis['male']) }}</dd></div>
        <div><dt>Female</dt><dd>{{ number_format($kpis['female']) }}</dd></div>
        <div><dt>PWD</dt><dd>{{ number_format($kpis['pwd']) }}</dd></div>
        <div><dt>Senior</dt><dd>{{ number_format($kpis['senior']) }}</dd></div>
        <div><dt>Income</dt><dd>₱{{ number_format($kpis['income'], 2) }}</dd></div>
    </dl>

    @if ($view === 'demographics')
        {{-- Everyone actually admitted, with the details from the logsheet. --}}
        <p class="figures figures--lg">
            <span><b>{{ $summary['total'] }}</b> admitted</span>
            <span><b>{{ $summary['male'] }}</b> male</span>
            <span><b>{{ $summary['female'] }}</b> female</span>
            <span><b>{{ $summary['senior'] }}</b> senior citizens</span>
            <span><b>{{ $summary['pwd'] }}</b> PWD</span>
        </p>

        <section aria-labelledby="admitted-title">
            <div class="block__head">
                <h2 id="admitted-title">Admitted moviegoers</h2>
                <span class="muted small">Excludes no-shows and cancelled bookings</span>
            </div>
            @if ($admitted->isEmpty())
                <x-empty title="No one admitted in this range" icon="doc" />
            @else
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead>
                        <tr>
                            <th>Date</th><th>Event</th><th>Name</th><th class="num">Age</th><th>Sex</th>
                            <th>Company / school</th><th>Contact no.</th><th>Email</th><th>Senior ID</th><th>PWD</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($admitted as $rs)
                            @php $a = $rs->attendee; @endphp
                            <tr>
                                <td class="nowrap">{{ $rs->screening->event_date->format('M j') }}</td>
                                <td><a class="link-quiet" href="{{ route('staff.screenings.show', $rs->screening) }}">{{ $rs->screening->event_title }}</a></td>
                                <td class="cell-title">{{ $a?->full_name ?? '—' }}</td>
                                <td class="num">{{ $a?->age ?? '—' }}</td>
                                <td>{{ $a?->sex ?? '—' }}</td>
                                <td>{{ $a?->company_school ?: '—' }}</td>
                                <td class="nowrap">{{ $a?->contact_no ?: '—' }}</td>
                                <td>{{ $a?->email ?: '—' }}</td>
                                <td>{{ $a?->senior_card_no ?: '—' }}</td>
                                <td>{{ $a?->pwd_id_no ?: ($a?->pwd_indicator ? 'Yes' : '—') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination">{{ $admitted->links() }}</div>
            @endif
        </section>
    @else
        @php
            $reserved = $screenings->sum('reserved');
            $attended = $screenings->sum('attended');
        @endphp
        <p class="figures figures--lg">
            <span><b>{{ $reserved }}</b> seats reserved</span>
            <span><b>{{ $attended }}</b> admitted{{ $reserved ? ' ('.round($attended / $reserved * 100).'%)' : '' }}</span>
            <span class="figures__warn"><b>{{ $screenings->sum('no_shows') }}</b> no-shows</span>
            <span><b>₱{{ number_format($screenings->sum('verified_amount'), 0) }}</b> paid</span>
        </p>

            <section aria-labelledby="per-screening">
                <div class="block__head">
                    <h2 id="per-screening">Reserved vs. attended</h2>
                    <span class="muted small">Excludes cancelled bookings</span>
                </div>
                @if ($screenings->isEmpty())
                    <x-empty title="No screenings in this range" icon="doc" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Screening</th><th class="num">Reserved</th><th class="num">Admitted</th><th class="num">No-shows</th><th class="num">Payments</th></tr></thead>
                            <tbody>
                            @foreach ($screenings as $row)
                                <tr data-href="{{ route('staff.screenings.show', $row['screening']) }}">
                                    <td>
                                        <a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $row['screening']) }}">{{ $row['screening']->event_title }}</a>
                                        <div class="cell-sub">{{ $row['screening']->event_date->format('D, M j') }}</div>
                                    </td>
                                    <td class="num">{{ $row['reserved'] }}</td>
                                    <td class="num">{{ $row['attended'] }}</td>
                                    <td class="num">{{ $row['no_shows'] ?? '—' }}</td>
                                    <td class="num">{{ $row['verified_amount'] > 0 ? '₱'.number_format($row['verified_amount'], 0) : '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="num">{{ $reserved }}</th>
                                <th class="num">{{ $attended }}</th>
                                <th class="num">{{ $screenings->sum('no_shows') }}</th>
                                <th class="num">₱{{ number_format($screenings->sum('verified_amount'), 0) }}</th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
    @endif
@endsection
