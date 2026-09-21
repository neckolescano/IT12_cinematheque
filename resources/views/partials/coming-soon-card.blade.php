<article class="movie-card soon">
    <div class="poster">
        <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }} poster" loading="lazy">
    </div>
    <div class="movie-info">
        <h3>{{ $movie->title }}</h3>
        <p class="muted">{{ $movie->genre }}</p>
        @if ($movie->release_date)
            <p class="muted">Release: {{ $movie->release_date->format('j F Y') }}</p>
        @endif
        <span class="btn btn-block" aria-disabled="true">Coming Soon</span>
    </div>
</article>
