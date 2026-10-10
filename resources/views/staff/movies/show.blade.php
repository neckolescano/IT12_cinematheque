@extends('layouts.staff')

@section('title', $movie->title)

{{-- The film panel: poster and details, Edit movie / Add screening schedule, and every screening of the film.
     A screening row opens its attendee list (Admit party / Admit individual). --}}
@section('content')
    @php($runtime = $movie->runtime_minutes ? intdiv($movie->runtime_minutes, 60).'h '.($movie->runtime_minutes % 60).'m' : null)

    <a class="back-link" href="{{ route('staff.movies.index') }}"><x-arrow dir="left" /> Film catalog</a>
    <div class="film-panel">
        <div class="film-panel__poster">
            <x-poster :movie="$movie" />
        </div>

        <div class="film-panel__info">
            <div class="film-panel__title">
                <h1>{{ $movie->title }}</h1>
                @if ($movie->isDraft())
                    <span class="state state--warning">Draft · hidden from customers</span>
                @else
                    <span class="state state--success">Published</span>
                @endif
            </div>
            <p class="film-panel__meta">
                {{ collect([$movie->release_year, $runtime, $movie->rating, $movie->genres->pluck('genre_name')->join(', ') ?: null])->filter()->join(' · ') ?: 'No details yet' }}
            </p>
            <dl class="facts film-panel__facts">
                <dt>Programs</dt>
                <dd>
                    @forelse ($movie->programs as $program)
                        <a class="tag" href="{{ route('staff.movies.index', ['program' => $program->program_id]) }}">{{ $program->name }}</a>
                    @empty
                        <span class="muted">Not in a program yet</span>
                    @endforelse
                </dd>
                @if ($movie->directors->isNotEmpty())<dt>Director</dt><dd>{{ $movie->directorNames() }}</dd>@endif
                @if ($movie->actors->isNotEmpty())<dt>Cast</dt><dd>{{ $movie->castNames() }}</dd>@endif
            </dl>
            @if ($movie->synopsis)<p class="film-panel__synopsis">{{ Str::limit($movie->synopsis, 420) }}</p>@endif

            <div class="cluster film-panel__actions">
                @can('update', $movie)
                    <a class="btn btn--secondary" href="{{ route('staff.movies.edit', $movie) }}">Edit movie</a>
                @endcan
                @can('create', App\Models\Screening::class)
                    <a class="btn btn--primary" href="{{ route('staff.screenings.create', ['movie' => $movie->movie_id]) }}">Add screening schedule</a>
                @endcan
                @if ($screenings->isNotEmpty())
                    <a class="btn btn--secondary" href="{{ route('staff.movies.preview', $movie) }}">Review</a>
                @endif
                @if ($movie->isDraft())
                    @can('update', $movie)
                        <form method="POST" action="{{ route('staff.movies.publish', $movie) }}">
                            @csrf
                            <button type="submit" class="btn btn--secondary">Publish</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </div>

    <section class="film-screenings" aria-labelledby="screenings-title">
        <h2 id="screenings-title" class="section-title">Screenings <span class="muted small">{{ $screenings->count() }}</span></h2>
        @if ($screenings->isEmpty())
            <x-empty title="No screenings yet" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Time</th><th>Program</th><th>Admission</th><th class="center">Booked</th><th class="center">Admitted</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($screenings as $s)
                        @php($past = $s->event_date->lt(today()))
                        <tr @class(['is-muted' => $past]) data-href="{{ route('staff.screenings.show', $s) }}">
                            <td class="nowrap"><a class="cell-title link-quiet" href="{{ route('staff.screenings.show', $s) }}">{{ $s->event_date->format('D, M j, Y') }}</a>
                                @if ($s->event_title !== $movie->title)<div class="cell-sub">{{ $s->event_title }}</div>@endif</td>
                            <td class="nowrap">{{ \Carbon\Carbon::parse($s->start_time)->format('g:i A') }}</td>
                            <td>{{ $s->program?->name ?? '—' }}</td>
                            <td>{{ $s->isPaid() ? '₱'.number_format($s->price, 0) : 'Free' }}</td>
                            <td class="center num-inline">{{ $s->booked_count }} / {{ $s->total_seats }}</td>
                            <td class="center num-inline">{{ $s->admitted_count }}</td>
                            <td>
                                @if ($s->status === 'draft')<span class="state state--warning">Draft</span>
                                @elseif ($past)<span class="state state--neutral">Done</span>
                                @else<span class="state state--success">Published</span>@endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
