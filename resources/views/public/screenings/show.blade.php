@extends('layouts.app')

@section('title', $screening->event_title)

{{-- Film page: a centred hero (poster on a yellow offset block | the film: title, tags, curator's quote, story,
     "at a glance" fact sheet, cast), then the showtimes card, where every showtime is a row with its own
     seat indicator and its own "Reserve seats" / "Get tickets" button. Phone: one column. --}}
@php
    $tagger = \App\Support\ShowcaseTags::class;
    $movie = $screening->movie;
    $runtime = $movie?->runtime_minutes ? intdiv($movie->runtime_minutes, 60).'h '.($movie->runtime_minutes % 60).'m' : null;
    $genres = $movie?->genres->pluck('genre_name') ?? collect();
    $tags = array_values(array_unique([
        ...$tagger::forTitle($screening->event_title),
        ...($movie?->curator_note ? ["Curator's Pick"] : []),
        ...($movie?->release_year && $movie->release_year <= now()->year - $tagger::CLASSIC_AGE ? ['Restored Classic'] : []),
    ]));
    $levels = ['plenty' => 'Plenty of seats', 'fast' => 'Selling fast', 'full' => 'Sold out'];
    $upcoming = $showtimes->filter(fn ($s) => $s->event_date->gte(today()));
    $first = $upcoming->first()?->event_date;
    $last = $upcoming->last()?->event_date;
    $prices = $upcoming->filter->isPaid()->pluck('price')->unique();
    // At a glance: only what we know about this film, plus when/where/admission (always known).
    $glance = array_filter([
        'Released' => $movie?->release_year,
        'Running time' => $runtime,
        'Genre' => $genres->join(', ') ?: null,
        'Directed by' => $movie?->directors->pluck('full_name')->join(', ') ?: null,
        'Showing' => $first ? ($first->eq($last) ? $first->format('M j') : $first->format('M j').' – '.$last->format('M j')).' · '.$upcoming->count().' '.Str::plural('screening', $upcoming->count()) : null,
        'Admission' => $prices->isEmpty() ? 'Free' : ($upcoming->contains(fn ($s) => ! $s->isPaid()) ? 'Free or ' : '').'₱'.number_format($prices->min(), 0).($prices->count() > 1 ? '–₱'.number_format($prices->max(), 0) : ''),
        'Venue' => 'Cinematheque Centre Davao',
    ]);
@endphp

@section('content')
    <div class="filmpage">
        <x-crumbs :items="['Screenings' => route('home'), $screening->event_title => null]" />

        @if (! empty($preview['film']))
            {{-- Staff preview: the poster card as it appears on the home page. --}}
            <section class="preview-card" aria-label="Home page card">
                <span class="eyebrow">Home page card</span>
                <div class="showcase__grid">@include('public.screenings._film-card', ['film' => $preview['film']])</div>
            </section>
        @endif

        <div class="filmpage__hero">
            <div class="filmpage__poster">
                <x-poster :screening="$screening" />
            </div>

            <article class="filmpage__info">
                <span class="eyebrow">{{ $movie ? ($movie->title !== $screening->event_title ? $movie->title : 'Film') : 'Special programme' }}</span>
                <h1 class="display filmpage__title">{{ $screening->event_title }}</h1>

                @if ($movie?->rating || $tags)
                    <div class="filmpage__tags">
                        @if ($movie?->rating)<span class="badge-rating rating--{{ $tagger::ratingGroup($movie->rating) }}" title="MTRCB rating">{{ $movie->rating }}</span>@endif
                        @foreach ($tags as $tag)<span class="sc-tag">{{ $tag }}</span>@endforeach
                    </div>
                @endif

                <div class="filmpage__actions">
                    <a class="btn btn--gold" href="#showtimes">See showtimes</a>
                    @if ($movie?->trailer_url)
                        @include('public.screenings._trailer-button', ['class' => 'btn btn--dark'])
                    @endif
                </div>

                @if ($movie?->curator_note)
                    <figure class="pullquote">
                        <blockquote>{{ $movie->curator_note }}</blockquote>
                        <figcaption>Cinematheque curator</figcaption>
                    </figure>
                @endif

                @if ($movie?->synopsis)
                    <section class="filmpage__section" aria-labelledby="story-title">
                        <h2 class="label-caps" id="story-title">The story</h2>
                        <p class="film__synopsis">{{ $movie->synopsis }}</p>
                    </section>
                @endif

                <section class="filmpage__section" aria-labelledby="glance-title">
                    <h2 class="label-caps" id="glance-title">At a glance</h2>
                    <dl class="glance">
                        @foreach ($glance as $label => $value)
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                @if ($movie?->actors->isNotEmpty())
                    <section class="filmpage__section" aria-labelledby="cast-title">
                        <h2 class="label-caps" id="cast-title">Starring</h2>
                        <ul class="cast">
                            @foreach ($movie->actors as $actor)<li>{{ $actor->full_name }}</li>@endforeach
                        </ul>
                    </section>
                @endif
            </article>
        </div>

        {{-- Showtimes: one group per date; each showtime is a ticket with its seats and its own button. --}}
        <section class="board" id="showtimes" aria-labelledby="showtimes-title">
            <div class="board__head">
                <h2 class="section-title" id="showtimes-title">Showtimes</h2>
                <ul class="dot-legend" aria-label="Seat availability">
                    <li class="seat-dot--plenty">Plenty</li>
                    <li class="seat-dot--fast">Selling fast</li>
                    <li class="seat-dot--full">Sold out</li>
                </ul>
            </div>
            <ol class="board__days">
                @foreach ($showtimes->groupBy(fn ($s) => $s->event_date->toDateString()) as $day)
                    <li class="board__day">
                        <div class="board__stub"><x-date-stub :date="$day->first()->event_date" size="sm" /></div>
                        <ul class="board__shows">
                            @foreach ($day as $show)
                                @php
                                    $level = $tagger::seatLevel($show);
                                    $left = $tagger::seatsLeft($show);
                                    $ended = $show->event_date->lt(today());
                                @endphp
                                {{-- A ticket: the time on a stub, a perforated tear line (notched edges), then admission,
                                     seats and the action — "Reserve seats" (free, staff-approved) or "Get tickets" (paid). --}}
                                <li @class(['showrow', 'is-chosen' => $show->is($screening)]) @if ($show->is($screening)) aria-current="true" @endif>
                                    <div class="showrow__stub">
                                        <strong class="showrow__time">{{ \Carbon\Carbon::parse($show->start_time)->format('g:i A') }}</strong>
                                        <span class="showrow__end">to {{ \Carbon\Carbon::parse($show->end_time)->format('g:i A') }}</span>
                                    </div>
                                    <div class="showrow__body">
                                        <div class="showrow__info">
                                            <span class="showrow__cell">
                                                <span class="showrow__label">Admission</span>
                                                <span class="showrow__price">@if ($show->isPaid())<span class="money">₱{{ number_format($show->price, 0) }}</span>@else Free @endif</span>
                                            </span>
                                            <span class="showrow__cell showrow__seats seat-dot--{{ $level }}">
                                                <span class="showrow__label">Seats</span>
                                                <span class="showrow__level">{{ $levels[$level] }} <span class="muted">· {{ $left }} of {{ $show->total_seats }} left</span></span>
                                            </span>
                                            @if ($show->event_title !== $screening->event_title || $tagger::forTitle($show->event_title))
                                                <span class="showrow__notes">
                                                    @if ($show->event_title !== $screening->event_title)<span class="small muted">{{ $show->event_title }}</span>@endif
                                                    @foreach ($tagger::forTitle($show->event_title) as $note)<span class="sc-tag">{{ $note }}</span>@endforeach
                                                </span>
                                            @endif
                                        </div>
                                        @if ($ended)
                                            <span class="btn btn--sm is-disabled">Ended</span>
                                        @elseif ($left > 0)
                                            <a class="btn btn--gold btn--sm" href="{{ route('bookings.create', $show) }}">{{ $show->isPaid() ? 'Get tickets' : 'Reserve seats' }}<span class="sr-only"> for {{ \Carbon\Carbon::parse($show->start_time)->format('g:i A') }}, {{ $show->event_date->format('F j') }}</span></a>
                                        @else
                                            <span class="btn btn--sm is-disabled">Fully booked</span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    @include('public.screenings._trailer-modal')
@endsection
