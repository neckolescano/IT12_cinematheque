{{-- Screening card. Priority: poster → title → date → time → type → seats → reserve. --}}
@php($left = max(0, $screening->total_seats - $screening->reservation_seats_count))
<article class="card card--flush card--hover screening-card reveal" data-stagger>
    <x-poster :screening="$screening">
        @if ($screening->isPaid())
            <span class="badge badge--gold badge--plain">₱{{ number_format($screening->price, 2) }}</span>
        @else
            <span class="badge badge--dark badge--plain">Free admission</span>
        @endif
    </x-poster>
    <div class="screening-card__body">
        <h3 class="screening-card__title">
            <a href="{{ route('screenings.show', $screening) }}">{{ $screening->event_title }}</a>
        </h3>
        @if ($screening->movie && $screening->movie->title !== $screening->event_title)
            <div class="muted small" style="margin-top:-.4rem">{{ $screening->movie->title }}</div>
        @endif
        <div class="screening-card__meta">
            <x-date-badge :date="$screening->event_date" />
            <div class="small">
                <div style="font-weight:600">{{ $screening->event_date->format('l, F j') }}</div>
                <div class="muted">{{ substr($screening->start_time, 0, 5) }} – {{ substr($screening->end_time, 0, 5) }}</div>
            </div>
        </div>
        <div class="screening-card__foot">
            <span class="seats-left {{ $left === 0 ? 'seats-left--none' : ($left <= 10 ? 'seats-left--low' : '') }}">
                {{ $left === 0 ? 'Fully booked' : $left.' seats left' }}
            </span>
            @if ($left > 0)
                <a class="btn btn--primary btn--sm" href="{{ route('bookings.create', $screening) }}">Reserve <span class="arrow">&rarr;</span></a>
            @endif
        </div>
        @can('update', $screening)
            <a class="staff-link small" href="{{ route('staff.screenings.edit', $screening) }}">Edit (staff)</a>
        @endcan
    </div>
</article>
