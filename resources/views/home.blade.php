@extends('layouts.app')

@section('title', 'Cinematheque Davao')

@section('content')
    @if ($featured)
        <section class="hero" style="background-image: linear-gradient(90deg, rgba(10,10,10,.92) 0%, rgba(10,10,10,.6) 50%, rgba(10,10,10,.15) 100%), url('{{ $featured->backdrop_url }}');">
            <div class="container hero-inner">
                <span class="pill-outline">NOW FEATURED</span>
                <h1 class="display">{{ $featured->title }}</h1>
                <div class="meta">
                    @if ($featured->age_rating)<span class="tag">{{ $featured->age_rating }}</span>@endif
                    <span>{{ $featured->duration_label }}</span>
                    @if ($featured->release_date)<span>•</span><span>{{ $featured->release_date->format('Y') }}</span>@endif
                </div>
                @if ($featured->synopsis)<p>{{ $featured->synopsis }}</p>@endif
                <div class="hero-actions">
                    <a href="{{ route('booking.showtimes', $featured) }}" class="btn btn-gold btn-display">BOOK NOW</a>
                    <a href="{{ route('movies.index') }}" class="btn btn-ghost">More Info</a>
                </div>
            </div>
        </section>
    @endif

    <section class="section">
        <div class="container">
            <h2 class="display">NOW SHOWING</h2>
            @if ($nowShowing->isEmpty())
                <p class="empty">Nothing is showing right now. Check back soon.</p>
            @else
                <div class="movie-grid">
                    @foreach ($nowShowing as $movie)
                        @include('partials.movie-card', ['movie' => $movie])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="section" style="padding-bottom: 64px;">
        <div class="container">
            <h2 class="display">COMING SOON</h2>
            @if ($comingSoon->isEmpty())
                <p class="empty">No upcoming releases announced yet.</p>
            @else
                <div class="movie-grid">
                    @foreach ($comingSoon as $movie)
                        @include('partials.coming-soon-card', ['movie' => $movie])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
