{{-- usage: @include('partials.movie-card', ['movie' => $movie, 'detailed' => true]) --}}
<article class="movie-card">
    <div class="poster">
        <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }} poster" loading="lazy">
    </div>
    <div class="movie-info">
        <h3>{{ $movie->title }}</h3>
        <p class="muted">{{ $movie->genre }} &nbsp;•&nbsp; {{ $movie->duration_label }}</p>

        @if (!empty($detailed))
            @if (count($movie->cinema_names))
                <div>
                    @foreach ($movie->cinema_names as $name)
                        <span class="badge">{{ $name }}</span>
                    @endforeach
                </div>
            @endif
            @if ($movie->rating)
                <div class="stars" aria-label="Rated {{ $movie->rating }} out of 5">
                    @for ($i = 1; $i <= 5; $i++)
                        <span class="{{ $i <= floor($movie->rating) ? 'on' : '' }}">★</span>
                    @endfor
                    <small>{{ number_format($movie->rating, 1) }}</small>
                </div>
            @endif
        @endif

        <a class="btn btn-gold btn-block" href="{{ route('booking.showtimes', $movie) }}">Book Now</a>
    </div>
</article>
