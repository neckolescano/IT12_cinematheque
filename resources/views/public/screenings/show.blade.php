@extends('layouts.app')

@section('title', $screening->event_title)

@php
    $movie = $screening->movie;
    $past = $screening->event_date->lt(today());
    $reserved = $screening->total_seats - $available;
@endphp

@section('content')
    <a class="crumb" href="{{ route('home') }}">&larr; All screenings</a>

    <div class="detail-layout">
        {{-- Left: the film --}}
        <aside class="card detail-film">
            <x-poster :screening="$screening">
                @if ($screening->isPaid())
                    <span class="badge badge--gold badge--plain">₱{{ number_format($screening->price, 2) }}</span>
                @else
                    <span class="badge badge--dark badge--plain">Free admission</span>
                @endif
            </x-poster>
            <h1 style="font-size:var(--fs-2xl);margin:var(--s-4) 0 var(--s-2)">{{ $screening->event_title }}</h1>
            @if ($movie)
                <div class="cluster" style="margin-bottom:var(--s-3)">
                    @if ($movie->rating)<span class="badge badge--neutral badge--plain">{{ $movie->rating }}</span>@endif
                    @foreach ($movie->genres as $genre)<span class="badge badge--plain">{{ $genre->genre_name }}</span>@endforeach
                </div>
                <div class="dot-list" style="margin-bottom:var(--s-4)">
                    @if ($movie->title !== $screening->event_title)<span>{{ $movie->title }}</span>@endif
                    @if ($movie->release_year)<span>{{ $movie->release_year }}</span>@endif
                    @if ($movie->runtime_minutes)<span>{{ $movie->runtime_minutes }} min</span>@endif
                </div>
                @if ($movie->synopsis)<p class="small">{{ $movie->synopsis }}</p>@endif
                <dl class="kv">
                    @if ($movie->directors->isNotEmpty())<dt>Director</dt><dd>{{ $movie->directors->pluck('full_name')->join(', ') }}</dd>@endif
                    @if ($movie->actors->isNotEmpty())<dt>Cast</dt><dd>{{ $movie->actors->pluck('full_name')->join(', ') }}</dd>@endif
                </dl>
            @else
                <p class="small muted" style="margin:0">A special programme — such as a festival block, a talk or a shorts selection — rather than a single cataloged film.</p>
            @endif
        </aside>

        {{-- Right: this screening + the action --}}
        <div class="stack">
            <section class="card">
                <h2 class="card__title"><span class="card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg></span>Screening</h2>
                <div class="screening-card__meta" style="margin-bottom:var(--s-5)">
                    <x-date-badge :date="$screening->event_date" />
                    <div>
                        <div style="font-weight:700;font-size:var(--fs-lg)">{{ $screening->event_date->format('l, F j, Y') }}</div>
                        <div class="muted">{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</div>
                    </div>
                </div>
                <dl class="kv" style="margin-bottom:var(--s-5)">
                    <dt>Admission</dt><dd>{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2).' per seat · paid online via PayMongo' : 'Free · reservation approved by staff' }}</dd>
                    <dt>Seats left</dt><dd>{{ $available }} of {{ $screening->total_seats }}</dd>
                </dl>
                <div class="progress" style="margin-bottom:var(--s-5)" aria-hidden="true"><span style="width:{{ $screening->total_seats ? min(100, $reserved / $screening->total_seats * 100) : 0 }}%"></span></div>

                @if ($past)
                    <div class="alert alert--info" style="margin:0"><div>This screening has already taken place.</div></div>
                @elseif ($available > 0)
                    <a class="btn btn--primary btn--lg btn--block" href="{{ route('bookings.create', $screening) }}">Choose seats <span class="arrow" aria-hidden="true">&rarr;</span></a>
                @else
                    <div class="alert alert--warning" style="margin:0"><div>This screening is fully booked.</div></div>
                @endif
            </section>

            @if ($otherDates->isNotEmpty())
                <section class="card">
                    <h2 class="card__title" style="font-size:var(--fs-md)">Other dates for this film</h2>
                    <div class="chips">
                        @foreach ($otherDates as $other)
                            <a class="chip" href="{{ route('screenings.show', $other) }}">
                                <strong>{{ $other->event_date->format('D, M j') }}</strong>
                                <span>{{ \Carbon\Carbon::parse($other->start_time)->format('g:i A') }} · {{ $other->isPaid() ? '₱'.number_format($other->price, 2) : 'Free' }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @can('view', $screening)
                <p class="small" style="margin:0"><a href="{{ route('staff.screenings.show', $screening) }}">Staff view of this screening &rarr;</a></p>
            @endcan
        </div>
    </div>
@endsection
