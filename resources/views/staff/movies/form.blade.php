@extends('layouts.staff')

@section('title', $movie->exists ? 'Edit movie' : 'Add movie')

@section('content')
    @php
        $editing = $movie->exists;
        $selActors = old('actor_ids', $editing ? $movie->actors->pluck('actor_id')->all() : []);
        $selDirectors = old('director_ids', $editing ? $movie->directors->pluck('director_id')->all() : []);
        $selGenres = old('genre_ids', $editing ? $movie->genres->pluck('genre_id')->all() : []);
    @endphp

    <div class="page-head">
        <div>
            <a class="crumb" href="{{ route('staff.movies.index') }}">&larr; Film catalog</a>
            <h1>{{ $editing ? 'Edit movie' : 'Add movie' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $editing ? route('staff.movies.update', $movie) : route('staff.movies.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-layout">
            <div class="stack">
                <section class="card">
                    <h2 class="section-title">Movie details</h2>
                    <div class="field @error('title') has-error @enderror">
                        <label for="title">Title <span class="req">*</span></label>
                        <input type="text" id="title" name="title" maxlength="150" required value="{{ old('title', $movie->title) }}" placeholder="Enter the film title">
                        @error('title') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-grid">
                        <div class="field @error('runtime_minutes') has-error @enderror">
                            <label for="runtime_minutes">Runtime (minutes)</label>
                            <input type="number" id="runtime_minutes" name="runtime_minutes" min="1" value="{{ old('runtime_minutes', $movie->runtime_minutes) }}" placeholder="e.g. 142">
                            @error('runtime_minutes') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field @error('rating') has-error @enderror">
                            <label for="rating">Rating</label>
                            <input type="text" id="rating" name="rating" maxlength="10" value="{{ old('rating', $movie->rating) }}" placeholder="e.g. PG-13">
                            @error('rating') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field @error('release_year') has-error @enderror">
                            <label for="release_year">Release year</label>
                            <input type="number" id="release_year" name="release_year" value="{{ old('release_year', $movie->release_year) }}" placeholder="e.g. 1982">
                            @error('release_year') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2 class="section-title">Synopsis</h2>
                    <div class="field" style="margin:0">
                        <label for="synopsis" class="sr-only">Synopsis</label>
                        <textarea id="synopsis" name="synopsis" rows="6" placeholder="A short description shown on the public screening page">{{ old('synopsis', $movie->synopsis) }}</textarea>
                    </div>
                </section>
            </div>

            <aside class="form-layout__side">
                <section class="card">
                    <h2 class="section-title">Genres &amp; credits</h2>
                    <p class="hint" style="margin-top:-6px">Hold Ctrl (Cmd on Mac) to select more than one.</p>
                    <div class="field">
                        <label for="genre_ids">Genres</label>
                        <select id="genre_ids" name="genre_ids[]" multiple style="min-height:110px">
                            @foreach ($genres as $g)
                                <option value="{{ $g->genre_id }}" @selected(in_array($g->genre_id, $selGenres))>{{ $g->genre_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="director_ids">Directors</label>
                        <select id="director_ids" name="director_ids[]" multiple style="min-height:110px">
                            @foreach ($directors as $d)
                                <option value="{{ $d->director_id }}" @selected(in_array($d->director_id, $selDirectors))>{{ $d->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-bottom:8px">
                        <label for="actor_ids">Cast</label>
                        <select id="actor_ids" name="actor_ids[]" multiple style="min-height:140px">
                            @foreach ($actors as $a)
                                <option value="{{ $a->actor_id }}" @selected(in_array($a->actor_id, $selActors))>{{ $a->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="hint" style="margin:0">Missing a name? Add it under <a href="{{ route('staff.actors.index') }}">Actors</a>, <a href="{{ route('staff.directors.index') }}">Directors</a> or <a href="{{ route('staff.genres.index') }}">Genres</a>.</p>
                </section>
                <div class="form-footer">
                    <a class="btn btn--ghost" href="{{ route('staff.movies.index') }}">Cancel</a>
                    <button type="submit" class="btn btn--primary">{{ $editing ? 'Save changes' : 'Add movie' }}</button>
                </div>
            </aside>
        </div>
    </form>
@endsection
