{{-- One film in the showcase: 2:3 poster with badges (rating, opening date, cinematheque tags), then genre, title
     and admission. The whole card is one link to the film page (the title's link is stretched over the card);
     on hover the poster zooms in slightly. Expects $film (from PublicScreeningController::index). --}}
@php
    $tagger = \App\Support\ShowcaseTags::class;
    $movie = $film->movie;
    $lead = $film->shows->first();
    $next = $film->shows->first(fn ($s) => $tagger::seatsLeft($s) > 0); // soonest screening with seats; null = all full
    $paid = $film->shows->filter->isPaid();
    $genres = $movie?->genres->pluck('genre_name')->take(2)->join(' · ');
    $runtime = $movie?->runtime_minutes ? intdiv($movie->runtime_minutes, 60).'h '.($movie->runtime_minutes % 60).'m' : null;
    $tags = $tagger::for($film);
@endphp
<article class="film-card">
    <div class="film-card__frame">
        <x-poster :screening="$lead" />
        @if ($movie?->rating)
            <span class="badge-rating film-card__rating rating--{{ $tagger::ratingGroup($movie->rating) }}">{{ $movie->rating }}</span>
        @endif
        @if ($lead->event_date->gte(today()->addDays(\App\Http\Controllers\PublicScreeningController::NOW_SHOWING_DAYS)))
            <span class="film-card__opens">Opens {{ $lead->event_date->format('M j') }}</span>
        @endif
        @if ($tags)
            <span class="film-card__tags">@foreach ($tags as $tag)<span class="sc-tag">{{ $tag }}</span>@endforeach</span>
        @endif
    </div>

    <div class="film-card__body">
        <span class="eyebrow">{{ $movie ? ($genres ?: 'Film screening') : 'Special programme' }}</span>
        <h3 class="film-card__title"><a class="film-card__link" href="{{ route('screenings.show', $next ?? $lead) }}">{{ $film->title }}</a></h3>
        <p class="film-card__meta">
            @if (! $next)<span class="seats-left seats-left--none">Fully booked</span> ·@endif
            @if ($paid->isEmpty()) Free @else From <span class="money">₱{{ number_format($paid->min('price'), 0) }}</span> @endif
            @if ($runtime) · {{ $runtime }} @endif
        </p>
    </div>
</article>
