@extends('layouts.app')

@section('title', $screening->event_title)

@php($past = $screening->event_date->lt(today()))

@section('hero')
    <section class="hero hero--compact tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            <a class="crumb" href="{{ route('home') }}">&larr; All screenings</a>
            <div class="feature" style="margin-top:var(--s-2)">
                <x-poster :screening="$screening" :tall="true">
                    @if ($screening->isPaid())
                        <span class="badge badge--gold badge--plain">₱{{ number_format($screening->price, 2) }}</span>
                    @else
                        <span class="badge badge--dark badge--plain">Free admission</span>
                    @endif
                </x-poster>
                <div>
                    <h1>{{ $screening->event_title }}</h1>
                    <div class="cluster" style="gap:var(--s-4);margin:var(--s-4) 0">
                        <x-date-badge :date="$screening->event_date" />
                        <div>
                            <div style="font-weight:600;color:#fff">{{ $screening->event_date->format('l, F j, Y') }}</div>
                            <div class="muted">{{ substr($screening->start_time, 0, 5) }} – {{ substr($screening->end_time, 0, 5) }}</div>
                        </div>
                    </div>
                    <div class="cluster" style="margin-bottom:var(--s-5)">
                        <span class="badge {{ $screening->isPaid() ? 'badge--gold' : 'badge--success' }} badge--plain">{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2).' per seat' : 'Free admission' }}</span>
                        <span class="badge badge--dark badge--plain">{{ $available }} of {{ $screening->total_seats }} seats left</span>
                    </div>
                    @if ($past)
                        <p><strong>This screening has already taken place.</strong></p>
                    @elseif ($available > 0)
                        <a class="btn btn--primary" href="{{ route('bookings.create', $screening) }}">Reserve seats <span class="arrow">&rarr;</span></a>
                    @else
                        <p><strong>Fully booked.</strong></p>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@section('content')
    <div class="grid grid-2" style="align-items:start">
        <section class="card reveal">
            @if ($movie = $screening->movie)
                <span class="eyebrow">About the film</span>
                <h2>{{ $movie->title }}</h2>
                <p class="muted small">
                    {{ $movie->release_year }}
                    @if ($movie->runtime_minutes) · {{ $movie->runtime_minutes }} min @endif
                    @if ($movie->rating) · {{ $movie->rating }} @endif
                </p>
                @if ($movie->genres->isNotEmpty())
                    <div class="cluster" style="margin-bottom:var(--s-4)">
                        @foreach ($movie->genres as $genre)
                            <span class="badge badge--plain">{{ $genre->genre_name }}</span>
                        @endforeach
                    </div>
                @endif
                @if ($movie->synopsis)
                    <p>{{ $movie->synopsis }}</p>
                @endif
                <dl class="kv">
                    @if ($movie->directors->isNotEmpty())
                        <dt>Directed by</dt><dd>{{ $movie->directors->pluck('full_name')->join(', ') }}</dd>
                    @endif
                    @if ($movie->actors->isNotEmpty())
                        <dt>Cast</dt><dd>{{ $movie->actors->pluck('full_name')->join(', ') }}</dd>
                    @endif
                </dl>
            @else
                <span class="eyebrow">Programme</span>
                <h2>{{ $screening->event_title }}</h2>
                <p class="muted">This is a special programme — such as a festival block, a talk or a shorts selection — rather than a single cataloged film.</p>
            @endif
        </section>

        <aside class="card reveal">
            <span class="eyebrow">Your visit</span>
            <dl class="kv">
                <dt>Date</dt><dd>{{ $screening->event_date->format('l, F j, Y') }}</dd>
                <dt>Time</dt><dd>{{ substr($screening->start_time, 0, 5) }} – {{ substr($screening->end_time, 0, 5) }}</dd>
                <dt>Admission</dt><dd>{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2).' per seat, paid via QR' : 'Free' }}</dd>
                <dt>Seats left</dt><dd>{{ $available }} of {{ $screening->total_seats }}</dd>
            </dl>
            @if (! $past && $available > 0)
                <div class="form-actions"><a class="btn btn--primary btn--block" href="{{ route('bookings.create', $screening) }}">Reserve seats</a></div>
            @endif
            @can('view', $screening)
                <p class="small" style="margin:var(--s-4) 0 0"><a href="{{ route('staff.screenings.show', $screening) }}">Staff view of this screening &rarr;</a></p>
            @endcan
        </aside>
    </div>
@endsection
