@extends('layouts.staff')

@section('title', $movie->exists ? 'Edit film' : 'Add film')

@section('content')
    @php
        $editing = $movie->exists;
        $rating = old('rating', $movie->rating);
    @endphp

    <div class="page-head">
        <div>
            <a class="back-link" href="{{ $editing ? route('staff.movies.show', $movie) : route('staff.movies.index') }}"><x-arrow dir="left" /> {{ $editing ? $movie->title : 'Film catalog' }}</a>
            <h1>{{ $editing ? 'Edit film' : 'Add film' }}</h1>
        </div>
    </div>

    <form method="POST" action="{{ $editing ? route('staff.movies.update', $movie) : route('staff.movies.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-layout">
            <div class="stack">
                <section class="panel">
                    <h2 class="panel__title">Details</h2>
                    <div class="movie-form__top">
                        {{-- Drag-and-drop poster uploader, beside the metadata --}}
                        <div class="poster-upload">
                            <div class="dropzone @error('poster') has-error @enderror" data-dropzone>
                                <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp" class="dropzone__input" aria-describedby="poster-hint">
                                <img class="dropzone__preview" src="{{ $movie->posterUrl() ?? '' }}" alt="" @unless ($movie->posterUrl()) hidden @endunless>
                                <div class="dropzone__prompt" @if ($movie->posterUrl()) hidden @endif>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                                    <strong>Drag &amp; drop poster</strong>
                                    <span>or click to browse</span>
                                </div>
                            </div>
                            <label for="poster" class="sr-only">Poster image</label>
                            <p class="hint" id="poster-hint">JPG, PNG or WebP · max 2 MB · portrait 2:3</p>
                            @error('poster') <span class="field__error">{{ $message }}</span> @enderror
                            @if ($movie->poster_path)
                                <label class="check"><input type="checkbox" name="remove_poster" value="1"> Remove poster</label>
                            @endif
                        </div>

                        <div class="movie-form__fields">
                            <div class="field @error('title') has-error @enderror">
                                <label for="title">Title <span class="req">*</span></label>
                                <input type="text" id="title" name="title" maxlength="150" required value="{{ old('title', $movie->title) }}">
                                @error('title') <span class="field__error">{{ $message }}</span> @enderror
                            </div>
                            <div class="form-grid">
                                <div class="field @error('runtime_minutes') has-error @enderror">
                                    <label for="runtime_minutes">Runtime (minutes)</label>
                                    <input type="number" id="runtime_minutes" name="runtime_minutes" min="1" value="{{ old('runtime_minutes', $movie->runtime_minutes) }}">
                                    @error('runtime_minutes') <span class="field__error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field @error('rating') has-error @enderror">
                                    <label for="rating">Content rating</label>
                                    <select id="rating" name="rating">
                                        <option value="">Not rated</option>
                                        @foreach (App\Models\Movie::RATINGS as $r)
                                            <option value="{{ $r }}" @selected($rating === $r)>{{ $r }}</option>
                                        @endforeach
                                    </select>
                                    @error('rating') <span class="field__error">{{ $message }}</span> @enderror
                                </div>
                                <div class="field @error('release_year') has-error @enderror">
                                    <label for="release_year">Release year</label>
                                    <input type="number" id="release_year" name="release_year" value="{{ old('release_year', $movie->release_year) }}">
                                    @error('release_year') <span class="field__error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            @include('staff.partials.genre-field', ['p' => 'movie', 'movie' => $editing ? $movie : null])

                            {{-- A film can be in several programs (a festival may screen it again). --}}
                            @include('staff.partials.program-tags', [
                                'chosen' => collect(old('programs', $movie->programs->pluck('name')->all()))->map(fn ($n) => App\Models\Program::clean($n))->filter()->unique(fn ($n) => mb_strtolower($n))->values(),
                                'suggestions' => $programs,
                            ])
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <h2 class="panel__title">Synopsis</h2>
                    <div class="field" style="margin:0">
                        <label for="synopsis" class="sr-only">Synopsis</label>
                        <textarea id="synopsis" name="synopsis" rows="6">{{ old('synopsis', $movie->synopsis) }}</textarea>
                    </div>
                </section>

                {{-- Shown on the public home page: the poster card, the spotlight and the trailer button. --}}
                <section class="panel">
                    <h2 class="panel__title">Programme notes</h2>
                    <div class="field @error('logline') has-error @enderror">
                        <label for="logline">Logline</label>
                        <input type="text" id="logline" name="logline" maxlength="200" value="{{ old('logline', $movie->logline) }}" aria-describedby="logline-hint">
                        <p class="hint" id="logline-hint">Shown on the home banner. If blank, the synopsis's first sentence is used.</p>
                        @error('logline') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field @error('curator_note') has-error @enderror">
                        <label for="curator_note">Curator's note</label>
                        <input type="text" id="curator_note" name="curator_note" maxlength="200" value="{{ old('curator_note', $movie->curator_note) }}" placeholder="e.g. Nora Aunor's defining performance, newly restored in 4K." aria-describedby="curator-hint">
                        <p class="hint" id="curator-hint">One sentence on why to see it. Marks the film "Curator's Pick" on the home page.</p>
                        @error('curator_note') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field @error('trailer_url') has-error @enderror" style="margin:0">
                        <label for="trailer_url">Trailer link</label>
                        <input type="url" id="trailer_url" name="trailer_url" maxlength="255" value="{{ old('trailer_url', $movie->trailer_url) }}" placeholder="https://www.youtube.com/watch?v=…" aria-describedby="trailer-hint">
                        <p class="hint" id="trailer-hint">YouTube and Vimeo links play on the page; other links open in a new tab.</p>
                        @error('trailer_url') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                </section>
            </div>

            <aside class="form-layout__side">
                <section class="panel">
                    <h2 class="panel__title">Credits</h2>
                    <div class="field @error('directors') has-error @enderror">
                        <label for="directors">Director</label>
                        <input type="text" id="directors" name="directors" maxlength="255" value="{{ old('directors', $editing ? $movie->directorNames() : '') }}" placeholder="e.g. Ishmael Bernal">
                        @error('directors') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field @error('actors') has-error @enderror" style="margin-bottom:8px">
                        <label for="actors">Actors</label>
                        <textarea id="actors" name="actors" rows="4" maxlength="1000" placeholder="e.g. Nora Aunor, Veronica Palileo, Spanky Manikan">{{ old('actors', $editing ? $movie->castNames() : '') }}</textarea>
                        @error('actors') <span class="field__error">{{ $message }}</span> @enderror
                    </div>
                    <p class="hint" style="margin:0">Separate names with commas.</p>
                </section>
                {{-- Save as draft (staff only) · Review (the customer page as it will look) · Publish --}}
                <section class="panel">
                    <h2 class="panel__title">Publishing</h2>
                    <p class="status-line">
                        @if ($movie->isDraft())<span class="state state--warning">Draft</span> Customers can't see this film.
                        @else<span class="state state--success">Published</span> Live on the customer site.@endif
                    </p>
                    <div class="save-actions">
                        <button type="submit" name="intent" value="publish" class="btn btn--primary btn--block">{{ $editing && ! $movie->isDraft() ? 'Save and keep published' : 'Publish' }}</button>
                        <div class="save-actions__row">
                            <button type="submit" name="intent" value="draft" class="btn btn--secondary" formnovalidate>Save as draft</button>
                            <button type="submit" name="intent" value="review" class="btn btn--secondary">Review</button>
                        </div>
                        <a class="btn btn--ghost btn--block" href="{{ $editing ? route('staff.movies.show', $movie) : route('staff.movies.index') }}">Cancel</a>
                    </div>
                    @if ($editing)
                        @can('delete', $movie)
                            <button type="submit" form="delete-film" class="btn btn--danger btn--block" style="margin-top:14px">Delete film</button>
                        @endcan
                    @endif
                </section>
            </aside>
        </div>
    </form>

    @if ($editing)
        @can('delete', $movie)
            <form id="delete-film" method="POST" action="{{ route('staff.movies.destroy', $movie) }}" data-confirm-danger
                  data-confirm="Delete “{{ $movie->title }}”? Its screenings are kept but no longer linked to it." data-confirm-label="Delete film">
                @csrf @method('DELETE')
            </form>
        @endcan
    @endif
@endsection
