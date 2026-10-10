@extends('layouts.staff')

@section('title', 'Film catalog')

{{-- Poster tiles grouped by program, with program filter chips on top. A tile opens the film's panel. --}}
@section('content')
    <div class="page-head">
        <div>
            <h1>Film catalog</h1>
            <p>{{ $total }} {{ Str::plural('film', $total) }}{{ $current instanceof App\Models\Program ? ' in '.$current->name : ($current === 'none' ? ' not in a program' : '') }}</p>
        </div>
        @can('create', App\Models\Movie::class)
            <a class="btn btn--primary" href="{{ route('staff.movies.create', $current instanceof App\Models\Program ? ['program' => $current->program_id] : []) }}">Add film</a>
        @endcan
    </div>

    <nav class="chips catalog-chips" aria-label="Filter by program">
        <a href="{{ route('staff.movies.index') }}" @if (! $current) aria-current="page" @endif>All programs</a>
        @foreach ($programs as $program)
            <a href="{{ route('staff.movies.index', ['program' => $program->program_id]) }}" @if ($current instanceof App\Models\Program && $current->is($program)) aria-current="page" @endif>
                {{ $program->name }} <span class="n">{{ $program->movies_count }}</span>
            </a>
        @endforeach
        @if ($unfiled)
            <a href="{{ route('staff.movies.index', ['program' => 'none']) }}" @if ($current === 'none') aria-current="page" @endif>No program <span class="n">{{ $unfiled }}</span></a>
        @endif
    </nav>

    @forelse ($groups as $group)
        <section class="catalog-group" aria-labelledby="group-{{ $loop->index }}">
            <div class="catalog-group__head">
                <h2 id="group-{{ $loop->index }}">{{ $group->program?->name ?? 'No program' }}</h2>
                <span class="muted small">{{ $group->movies->count() }} {{ Str::plural('film', $group->movies->count()) }}</span>
            </div>
            <ul class="poster-grid">
                @foreach ($group->movies as $movie)
                    <li>
                        <a class="poster-tile" href="{{ route('staff.movies.show', $movie) }}">
                            <span class="poster-tile__frame">
                                <x-poster :movie="$movie" />
                                @if ($movie->isDraft())<span class="poster-tile__badge">Draft</span>@endif
                            </span>
                            <span class="poster-tile__title">{{ $movie->title }}</span>
                            <span class="poster-tile__meta">{{ $movie->release_year ?? 'Year —' }} · {{ $movie->screenings_count }} {{ Str::plural('screening', $movie->screenings_count) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <x-empty title="No films here yet" />
    @endforelse
@endsection
