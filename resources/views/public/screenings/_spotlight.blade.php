{{-- Spotlight carousel (Ayala-style hero): the soonest films as wide black "screen" banners, the current one centred
     with its neighbours peeking in on both sides. Each slide: a big outlined index number, the title, the logline,
     "Directed by", Programme – Rating – Genres, and the poster on a yellow offset block (the same mark as the film
     page). With nothing upcoming the slides are recent screenings, marked "Recently screened". Without JS it is a swipeable row; cinematheque.js makes it
     loop (clones at both ends, so the last film peeks in left of the first), and adds autoplay (the active dot fills
     as the slide's time runs), the side arrows, pause and the dots. Expects $featured. --}}
<section class="spotlight" aria-roledescription="carousel" aria-label="Featured films" data-spotlight>
    <div class="spotlight__track" data-spotlight-track>
        @foreach ($featured as $i => $film)
            @php
                $tagger = \App\Support\ShowcaseTags::class;
                $movie = $film->movie;
                $lead = $film->shows->first();
                $next = $film->shows->first(fn ($s) => $tagger::seatsLeft($s) > 0);
                $past = $lead->event_date->lt(today());
                $logline = $movie?->loglineText();
                $meta = collect([$lead->program?->name, $movie?->rating, $movie?->genres->pluck('genre_name')->join(', ')])->filter();
            @endphp
            <article class="spotlight__slide" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $featured->count() }}: {{ $film->title }}" data-spotlight-slide="{{ $i }}">
                <span class="spotlight__num" aria-hidden="true">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>

                <div class="spotlight__copy">
                    @if ($past)<span class="spotlight__kicker">Recently screened</span>@endif
                    <h2 class="spotlight__title">{{ $film->title }}</h2>
                    @if ($logline)<p class="spotlight__note">{{ $logline }}</p>@endif
                    @if ($movie?->directors->isNotEmpty())
                        <p class="spotlight__director">Directed by <strong>{{ $movie->directors->pluck('full_name')->join(', ') }}</strong></p>
                    @endif
                    @if ($meta->isNotEmpty())
                        <p class="spotlight__facts">{{ $meta->join(' - ') }}</p>
                    @endif
                    <div class="spotlight__actions">
                        <a class="btn btn--gold" href="{{ route('screenings.show', $next ?? $lead) }}">{{ $next && ! $past ? 'Book Seats' : 'Details' }}<span class="sr-only">: {{ $film->title }}</span></a>
                        @if ($movie?->trailer_url)
                            @include('public.screenings._trailer-button', ['class' => 'btn btn--glass'])
                        @endif
                    </div>
                </div>

                <a class="spotlight__poster" href="{{ route('screenings.show', $next ?? $lead) }}" tabindex="-1" aria-hidden="true">
                    <x-poster :screening="$lead" />
                </a>
            </article>
        @endforeach
    </div>

    @if ($featured->count() > 1)
        <button type="button" class="spotlight__side spotlight__side--prev" data-spotlight-step="-1" aria-label="Previous film"><x-arrow dir="left" /></button>
        <button type="button" class="spotlight__side spotlight__side--next" data-spotlight-step="1" aria-label="Next film"><x-arrow /></button>
        <div class="spotlight__controls">
            <button type="button" class="spotlight__pause" data-spotlight-pause aria-label="Pause slideshow">
                <svg class="icon-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                <svg class="icon-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l11-6.5a1 1 0 0 0 0-1.72l-11-6.5A1 1 0 0 0 8 5.5Z"/></svg>
            </button>
            @foreach ($featured as $i => $film)
                <button type="button" class="spotlight__dot" data-spotlight-dot="{{ $i }}" aria-label="Show {{ $film->title }}" @if ($i === 0) aria-current="true" @endif></button>
            @endforeach
        </div>
    @endif
</section>
