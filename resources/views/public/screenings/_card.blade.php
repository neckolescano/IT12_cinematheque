{{-- Screening card. Priority: poster → title → film meta → date/time → admission → seats → reserve. --}}
@php
    $left = max(0, $screening->total_seats - $screening->reservation_seats_count);
    $movie = $screening->movie;
@endphp
<article class="card card--flush card--hover screening-card reveal" data-stagger>
    <x-poster :screening="$screening">
        @if ($screening->isPaid())
            <span class="badge badge--gold badge--plain">₱{{ number_format($screening->price, 2) }}</span>
        @else
            <span class="badge badge--dark badge--plain">Free admission</span>
        @endif
    </x-poster>
    <div class="screening-card__body">
        <div>
            <h3 class="screening-card__title"><a href="{{ route('screenings.show', $screening) }}">{{ $screening->event_title }}</a></h3>
            @if ($movie)
                <div class="dot-list" style="margin-top:4px">
                    @if ($movie->genres->isNotEmpty())<span>{{ $movie->genres->pluck('genre_name')->take(2)->join(', ') }}</span>@endif
                    @if ($movie->runtime_minutes)<span>{{ intdiv($movie->runtime_minutes, 60) }}h {{ $movie->runtime_minutes % 60 }}m</span>@endif
                    @if ($movie->rating)<span>{{ $movie->rating }}</span>@endif
                </div>
            @endif
        </div>
        <div class="screening-card__meta">
            <x-date-badge :date="$screening->event_date" />
            <div class="small">
                <div style="font-weight:600">{{ $screening->event_date->format('l, F j') }}</div>
                <div class="muted">{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</div>
            </div>
        </div>
        <div class="screening-card__foot">
            <span class="seats-left {{ $left === 0 ? 'seats-left--none' : ($left <= 10 ? 'seats-left--low' : '') }}">
                {{ $left === 0 ? 'Fully booked' : $left.' seats left' }}
            </span>
            @if ($left > 0)
                <a class="btn btn--primary btn--sm" href="{{ route('bookings.create', $screening) }}">Reserve <span class="arrow" aria-hidden="true">&rarr;</span></a>
            @endif
        </div>
    </div>
</article>
