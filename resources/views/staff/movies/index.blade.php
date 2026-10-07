@extends('layouts.staff')

@section('title', 'Film catalog')

@section('content')
    <div class="page-head">
        <div>
            <h1>Film catalog</h1>
            <p>{{ $movies->total() }} {{ Str::plural('film', $movies->total()) }}</p>
        </div>
        @can('create', App\Models\Movie::class)
            <a class="btn btn--primary" href="{{ route('staff.movies.create') }}">Add film</a>
        @endcan
    </div>

    @if ($movies->isEmpty())
        <x-empty title="No films yet" />
    @else
        {{-- One row per film: film (rating beneath) · runtime · genre · director · year · screenings · Schedule. --}}
        <div class="table-wrap">
            <table class="table films">
                <thead>
                <tr><th>Film</th><th>Runtime</th><th>Genre</th><th>Director</th><th>Year</th><th class="center">Screenings</th><th class="actions">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($movies as $movie)
                    <tr @can('update', $movie) data-href="{{ route('staff.movies.edit', $movie) }}" @endcan>
                        <td>
                            <div class="film-cell">
                                @if ($movie->posterUrl())
                                    <img class="film-cell__poster" src="{{ $movie->posterUrl() }}" alt="" loading="lazy">
                                @else
                                    <span class="film-cell__poster" aria-hidden="true"></span>
                                @endif
                                <div>
                                    <a class="cell-title link-quiet" href="{{ route('staff.movies.edit', $movie) }}">{{ $movie->title }}</a>
                                    @if ($movie->rating)<div class="cell-sub">{{ $movie->rating }}</div>@endif
                                </div>
                            </div>
                        </td>
                        <td class="nowrap">{{ $movie->runtime_minutes ? $movie->runtime_minutes.' min' : '—' }}</td>
                        <td>{{ $movie->genres->pluck('genre_name')->join(', ') ?: '—' }}</td>
                        <td>{{ $movie->directors->pluck('full_name')->join(', ') ?: '—' }}</td>
                        <td class="num-inline">{{ $movie->release_year ?? '—' }}</td>
                        <td class="center">{{ $movie->screenings_count }}</td>
                        <td class="actions">
                            <div class="row-actions">
                                @can('create', App\Models\Screening::class)
                                    <a class="btn btn--secondary btn--sm" href="{{ route('staff.screenings.create', ['movie' => $movie->movie_id]) }}">Schedule</a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $movies->links() }}</div>
    @endif
@endsection
