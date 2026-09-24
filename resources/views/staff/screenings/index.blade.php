@extends('layouts.staff')

@section('title', 'Screenings')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Programme</span>
            <h1>Screenings</h1>
        </div>
        @can('create', App\Models\Screening::class)
            <a class="btn btn--primary" href="{{ route('staff.screenings.create') }}">+ New screening</a>
        @endcan
    </div>

    <nav class="tabs" aria-label="Filter screenings">
        <a href="{{ route('staff.screenings.index') }}" @if (request('when') !== 'all') aria-current="page" @endif>Upcoming</a>
        <a href="{{ route('staff.screenings.index', ['when' => 'all']) }}" @if (request('when') === 'all') aria-current="page" @endif>All</a>
    </nav>

    @if ($screenings->isEmpty())
        <div class="card"><x-empty title="No screenings here yet">Create one to open it for reservations.</x-empty></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead>
                <tr><th>Date</th><th>Event</th><th>Type</th><th class="num">Reserved</th><th>Created by</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($screenings as $screening)
                    <tr>
                        <td>
                            <div class="cluster" style="flex-wrap:nowrap">
                                <x-date-badge :date="$screening->event_date" />
                                <span class="small">{{ substr($screening->start_time, 0, 5) }}–{{ substr($screening->end_time, 0, 5) }}</span>
                            </div>
                        </td>
                        <td>
                            <a class="link-quiet" style="font-weight:600" href="{{ route('staff.screenings.show', $screening) }}">{{ $screening->event_title }}</a>
                            @if ($screening->movie)
                                <div class="muted small">{{ $screening->movie->title }}</div>
                            @endif
                        </td>
                        <td><span class="badge badge--plain {{ $screening->isPaid() ? 'badge--gold' : 'badge--success' }}">{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2) : 'Free' }}</span></td>
                        <td class="num">
                            {{ $screening->reservation_seats_count }} / {{ $screening->total_seats }}
                            <div class="muted small">{{ $screening->reservations_count }} {{ Str::plural('booking', $screening->reservations_count) }}</div>
                        </td>
                        <td class="small">{{ $screening->creator->full_name }}</td>
                        <td class="actions">
                            <a class="btn btn--ghost btn--sm" href="{{ route('staff.screenings.show', $screening) }}">Open</a>
                            @can('update', $screening)
                                <a class="btn btn--ghost btn--sm" href="{{ route('staff.screenings.edit', $screening) }}">Edit</a>
                            @endcan
                            @can('delete', $screening)
                                <form class="inline-form" method="POST" action="{{ route('staff.screenings.destroy', $screening) }}"
                                      data-confirm="Delete “{{ $screening->event_title }}”? This can't be undone." data-confirm-label="Delete screening">
                                    @csrf @method('DELETE')
                                    <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $screenings->links() }}</div>
    @endif
@endsection
