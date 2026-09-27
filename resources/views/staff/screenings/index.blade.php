@extends('layouts.staff')

@section('title', 'Screenings')

@section('content')
    <div class="page-head">
        <div>
            <h1>Screenings</h1>
            <p>Open a screening to see everyone who reserved, and to admit them at the door.</p>
        </div>
        @can('create', App\Models\Screening::class)
            <a class="btn btn--primary" href="{{ route('staff.screenings.create') }}" data-open-dialog="new-screening">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                New screening
            </a>
        @endcan
    </div>

    <div class="card card--flush">
        <form class="toolbar" method="GET" action="{{ route('staff.screenings.index') }}" data-no-loading>
            <nav class="segmented" aria-label="When">
                @foreach (['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
                    <a href="{{ route('staff.screenings.index', array_filter(['when' => $key, 'q' => $q])) }}" @if ($when === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <input type="hidden" name="when" value="{{ $when }}">
            <label class="search">
                <span class="sr-only">Search screenings</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="q" value="{{ $q }}" placeholder="Search by title">
            </label>
        </form>

        @if ($screenings->isEmpty())
            <x-empty title="No screenings here">
                {{ $when === 'upcoming' ? 'Create one to open it for reservations.' : 'Try a different filter.' }}
            </x-empty>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                    <tr><th>Date</th><th>Screening</th><th>Admission</th><th>Seats reserved</th><th class="num">To approve</th><th>Admitted</th><th></th></tr>
                    </thead>
                    <tbody>
                    @foreach ($screenings as $s)
                        <tr data-href="{{ route('staff.screenings.show', $s) }}">
                            <td>
                                <div class="cluster" style="flex-wrap:nowrap;gap:12px">
                                    <x-date-badge :date="$s->event_date" />
                                    <span class="cell-sub">{{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }}</span>
                                </div>
                            </td>
                            <td>
                                <a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_title }}</a>
                                @if ($s->movie && $s->movie->title !== $s->event_title)<div class="cell-sub">{{ $s->movie->title }}</div>@endif
                            </td>
                            <td>
                                @if ($s->isPaid())
                                    <span class="badge badge--brand badge--plain">Paid · ₱{{ number_format($s->price, 2) }}</span>
                                @else
                                    <span class="badge badge--success badge--plain">Free</span>
                                @endif
                            </td>
                            <td>
                                <div class="meter">
                                    <div class="progress"><span style="width:{{ $s->total_seats ? min(100, $s->reserved_count / $s->total_seats * 100) : 0 }}%"></span></div>
                                    <b>{{ $s->reserved_count }}/{{ $s->total_seats }}</b>
                                </div>
                            </td>
                            <td class="num">
                                @if ($s->pending_count)
                                    <span class="badge badge--warning">{{ $s->pending_count }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td><span class="{{ $s->admitted_count ? '' : 'muted' }}">{{ $s->admitted_count }} / {{ $s->reserved_count }}</span></td>
                            <td class="actions"><a class="btn btn--ghost btn--sm" href="{{ route('staff.screenings.show', $s) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <div class="pagination">{{ $screenings->links() }}</div>
@endsection

@push('dialogs')
    @can('create', App\Models\Screening::class)
        @include('staff.screenings._drawer', ['screening' => $blank])
    @endcan
@endpush
