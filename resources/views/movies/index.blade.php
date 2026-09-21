@extends('layouts.app')

@section('title', 'Movies · Cinematheque Davao')

@section('content')
    <div class="container">
        <div class="page-title">
            <h1 class="display">MOVIES</h1>
            <p class="muted">Discover and book your favorite movies</p>
        </div>

        @include('partials.alerts')

        <form method="GET" action="{{ route('movies.index') }}" class="filters" role="search">
            <input class="search" type="search" name="q" value="{{ request('q') }}" placeholder="Search for movies…" aria-label="Search for movies">

            <select name="language" onchange="this.form.submit()" aria-label="Language">
                <option value="">Language</option>
                @foreach ($languages as $language)
                    <option value="{{ $language }}" @selected(request('language') === $language)>{{ $language }}</option>
                @endforeach
            </select>

            <select name="genre" onchange="this.form.submit()" aria-label="Genre">
                <option value="">Genre</option>
                @foreach ($genres as $genre)
                    <option value="{{ $genre }}" @selected(request('genre') === $genre)>{{ $genre }}</option>
                @endforeach
            </select>

            <select name="rating" onchange="this.form.submit()" aria-label="Minimum rating">
                <option value="">Rating</option>
                @foreach (['4.5', '4.0', '3.5', '3.0'] as $r)
                    <option value="{{ $r }}" @selected(request('rating') === $r)>{{ $r }}+ stars</option>
                @endforeach
            </select>
        </form>
    </div>

    <section class="section">
        <div class="container">
            <h2 class="display">NOW SHOWING</h2>
            @if ($nowShowing->isEmpty())
                <p class="empty">No movies match your search.</p>
            @else
                <div class="movie-grid">
                    @foreach ($nowShowing as $movie)
                        @include('partials.movie-card', ['movie' => $movie, 'detailed' => true])
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="section" style="padding-bottom: 64px;">
        <div class="container">
            <h2 class="display">COMING SOON</h2>
            @if ($comingSoon->isEmpty())
                <p class="empty">No upcoming releases match your search.</p>
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
