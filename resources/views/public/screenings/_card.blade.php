{{-- "This week" card: the whole poster, then genre, title, when, admission and Book Now. No hover movement. --}}
@php
    $left = max(0, $screening->total_seats - $screening->held_seats_count);
    $movie = $screening->movie;
    $eyebrow = match (true) {
        ! $movie => 'Special programme',
        $movie->title !== $screening->event_title => $movie->title,
        $movie->genres->isNotEmpty() => $movie->genres->pluck('genre_name')->take(2)->join(' · '),
        default => 'Film screening',
    };
@endphp
<article class="movie-card">
    <a class="movie-card__poster" href="{{ route('screenings.show', $screening) }}" tabindex="-1" aria-hidden="true">
        <x-poster :screening="$screening" />
    </a>
    <div class="movie-card__body">
        <span class="eyebrow">{{ $eyebrow }}</span>
        <h3 class="movie-card__title"><a href="{{ route('screenings.show', $screening) }}">{{ $screening->event_title }}</a></h3>
        <p class="movie-card__meta">
            <strong>{{ $screening->event_date->format('D, M j') }}</strong> · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}
            · @if ($screening->isPaid())<span class="money">₱{{ number_format($screening->price, 0) }}</span>@else Free @endif
            @if ($movie?->runtime_minutes) · {{ intdiv($movie->runtime_minutes, 60) }}h {{ $movie->runtime_minutes % 60 }}m @endif
        </p>
        <div class="movie-card__foot">
            <span @class(['seats-left', 'seats-left--low' => $left > 0 && $left <= 10, 'seats-left--none' => $left === 0])>{{ $left === 0 ? 'Fully booked' : $left.' seats left' }}</span>
            @if ($left > 0)
                <a class="btn btn--gold btn--sm" href="{{ route('screenings.show', $screening) }}">Book Now</a>
            @endif
        </div>
    </div>
</article>
