{{-- A screening as the mobile app's ticket: cream date stub, perforation, details, Reserve. --}}
@php
    $left = max(0, $screening->total_seats - ($screening->held_seats_count ?? $screening->heldSeats()->count()));
    $movie = $screening->movie;
    $eyebrow = match (true) {
        ! $movie => 'Special programme',
        $movie->title !== $screening->event_title => $movie->title,
        $movie->genres->isNotEmpty() => $movie->genres->pluck('genre_name')->take(2)->join(' · '),
        default => 'Film screening',
    };
@endphp
<article class="ticket-card">
    <a class="ticket-card__link" href="{{ route('screenings.show', $screening) }}" aria-label="{{ $screening->event_title }}, {{ $screening->event_date->format('l, F j') }}"></a>
    <div class="ticket-card__stub"><x-date-stub :date="$screening->event_date" /></div>
    <div class="ticket-card__body">
        <x-poster :screening="$screening" class="poster--thumb ticket-card__poster" />
        <div>
        <span class="eyebrow">{{ $eyebrow }}</span>
        <h3 class="ticket-card__title">{{ $screening->event_title }}</h3>
        <p class="ticket-card__meta">
            <strong>{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</strong>
            · @if ($screening->isPaid())<span class="money">₱{{ number_format($screening->price, 0) }}</span>@else Free @endif
            @if ($movie?->runtime_minutes) · {{ intdiv($movie->runtime_minutes, 60) }}h {{ $movie->runtime_minutes % 60 }}m @endif
        </p>
        </div>
    </div>
    <div class="ticket-card__end">
        <span @class(['seats-left', 'seats-left--low' => $left > 0 && $left <= 10, 'seats-left--none' => $left === 0])>{{ $left === 0 ? 'Fully booked' : $left.' seats left' }}</span>
        @if ($left > 0)<span class="btn btn--gold btn--sm" aria-hidden="true">Reserve</span>@endif
    </div>
</article>
