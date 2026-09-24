@extends('layouts.staff')

@section('title', 'Movies')

@section('content')
    <div class="page-head">
        <div>
            <span class="eyebrow">Catalog</span>
            <h1>Movies</h1>
        </div>
        @can('create', App\Models\Movie::class)
            <a class="btn btn--primary" href="{{ route('staff.movies.create') }}">+ Add movie</a>
        @endcan
    </div>

    @if ($movies->isEmpty())
        <div class="card"><x-empty title="The catalog is empty">Add a film to link it to screenings.</x-empty></div>
    @else
        <div class="table-wrap reveal">
            <table class="table">
                <thead><tr><th>Title</th><th>Year</th><th>Runtime</th><th>Rating</th><th>Genres</th><th>Director</th><th class="num">Screenings</th><th></th></tr></thead>
                <tbody>
                @foreach ($movies as $movie)
                    <tr>
                        <td style="font-weight:600">{{ $movie->title }}</td>
                        <td>{{ $movie->release_year ?? '—' }}</td>
                        <td>{{ $movie->runtime_minutes ? $movie->runtime_minutes.' min' : '—' }}</td>
                        <td>{{ $movie->rating ?? '—' }}</td>
                        <td><div class="cluster">@forelse ($movie->genres as $g)<span class="badge badge--plain">{{ $g->genre_name }}</span>@empty<span class="muted">—</span>@endforelse</div></td>
                        <td class="small">{{ $movie->directors->pluck('full_name')->join(', ') ?: '—' }}</td>
                        <td class="num">{{ $movie->screenings_count }}</td>
                        <td class="actions">
                            @can('update', $movie)
                                <a class="btn btn--ghost btn--sm" href="{{ route('staff.movies.edit', $movie) }}">Edit</a>
                            @endcan
                            @can('delete', $movie)
                                <form class="inline-form" method="POST" action="{{ route('staff.movies.destroy', $movie) }}"
                                      data-confirm="Delete “{{ $movie->title }}”? Linked screenings keep their event title but lose the movie link." data-confirm-label="Delete movie">
                                    @csrf @method('DELETE')
                                    <button class="btn btn--danger btn--sm" type="submit">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $movies->links() }}</div>
    @endif
@endsection
