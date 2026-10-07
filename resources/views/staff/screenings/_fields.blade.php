{{-- The one Add/Edit Screening form (create and edit pages), in three sections.
     A film's details are entered right here; picking a title already in the catalog fills them in.
     Genres come from a fixed list plus "Other"; director and actors are typed (comma-separated). --}}
@php
    $p = $prefix ?? 'f';
    $movie = $screening->movie;
    $kind = old('kind', $screening->exists && ! $movie ? 'programme' : 'film');
    $catalog = $movies->map(fn ($m) => [
        'title' => $m->title, 'runtime' => $m->runtime_minutes, 'rating' => $m->rating, 'year' => $m->release_year,
        'genres' => $m->genres->pluck('genre_name')->all(), 'directors' => $m->directorNames(), 'actors' => $m->castNames(),
    ])->values();
@endphp
<div data-screening-form data-catalog='@json($catalog)'>

<section class="panel" aria-labelledby="{{ $p }}_s1">
<h2 class="panel__title" id="{{ $p }}_s1">What’s showing</h2>
<div class="field">
    <span class="label">Type</span>
    <div class="type-toggle">
        <label><input type="radio" name="kind" value="film" data-kind-input @checked($kind === 'film')> Film</label>
        <label><input type="radio" name="kind" value="programme" data-kind-input @checked($kind === 'programme')> Special programme</label>
    </div>
</div>

<fieldset class="film-fields" data-film-fields>
    <legend class="sr-only">Film details</legend>
    <div class="field @error('film_title') has-error @enderror">
        <label for="{{ $p }}_film_title">Film title <span class="req">*</span></label>
        <input type="text" id="{{ $p }}_film_title" name="film_title" maxlength="150" list="{{ $p }}_catalog" autocomplete="off"
               value="{{ old('film_title', $movie?->title) }}" data-film-title>
        <datalist id="{{ $p }}_catalog">@foreach ($movies as $m)<option value="{{ $m->title }}">{{ $m->release_year }}</option>@endforeach</datalist>
        <span class="hint" data-known-hint hidden>From the film catalog. Changes here update the film.</span>
        @error('film_title') <span class="field__error">{{ $message }}</span> @enderror
    </div>

    <div class="form-grid">
        <div class="field @error('runtime_minutes') has-error @enderror">
            <label for="{{ $p }}_runtime">Runtime (min)</label>
            <input type="number" id="{{ $p }}_runtime" name="runtime_minutes" min="1" max="600" value="{{ old('runtime_minutes', $movie?->runtime_minutes) }}" data-film="runtime">
            @error('runtime_minutes') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field @error('rating') has-error @enderror">
            <label for="{{ $p }}_rating">Content rating</label>
            <select id="{{ $p }}_rating" name="rating" data-film="rating">
                <option value="">Not rated</option>
                @foreach (App\Models\Movie::RATINGS as $r)
                    <option value="{{ $r }}" @selected(old('rating', $movie?->rating) === $r)>{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="field @error('release_year') has-error @enderror">
            <label for="{{ $p }}_year">Year</label>
            <input type="number" id="{{ $p }}_year" name="release_year" value="{{ old('release_year', $movie?->release_year) }}" data-film="year">
            @error('release_year') <span class="field__error">{{ $message }}</span> @enderror
        </div>
    </div>

    @include('staff.partials.genre-field', ['p' => $p, 'movie' => $movie])

    <div class="field @error('directors') has-error @enderror">
        <label for="{{ $p }}_directors">Director</label>
        <input type="text" id="{{ $p }}_directors" name="directors" maxlength="255" value="{{ old('directors', $movie?->directorNames()) }}" placeholder="e.g. Ishmael Bernal" data-film="directors">
        @error('directors') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('actors') has-error @enderror">
        <label for="{{ $p }}_actors">Actors</label>
        <textarea id="{{ $p }}_actors" name="actors" rows="2" maxlength="1000" placeholder="e.g. Nora Aunor, Veronica Palileo, Spanky Manikan" data-film="actors">{{ old('actors', $movie?->castNames()) }}</textarea>
        <span class="hint">Separate names with commas.</span>
        @error('actors') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</fieldset>

<div class="field @error('event_title') has-error @enderror">
    <label for="{{ $p }}_event_title"><span data-title-label-film @if ($kind !== 'film') hidden @endif>Screening title <span class="hint">(optional, uses the film title if blank)</span></span><span data-title-label-programme @if ($kind === 'film') hidden @endif>Programme title <span class="req">*</span></span></label>
    <input type="text" id="{{ $p }}_event_title" name="event_title" maxlength="150" data-event-title value="{{ old('event_title', $movie && $screening->event_title === $movie->title ? '' : $screening->event_title) }}">
    @error('event_title') <span class="field__error">{{ $message }}</span> @enderror
</div>
</section>

<section class="panel" aria-labelledby="{{ $p }}_s2">
<h2 class="panel__title" id="{{ $p }}_s2">Schedule</h2>
<div class="form-grid">
    <div class="field @error('event_date') has-error @enderror">
        <label for="{{ $p }}_event_date">Date <span class="req">*</span></label>
        <input type="date" id="{{ $p }}_event_date" name="event_date" required data-date-input value="{{ old('event_date', $screening->event_date?->format('Y-m-d')) }}">
        @error('event_date') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('start_time') has-error @enderror">
        <label for="{{ $p }}_start_time">Starts <span class="req">*</span></label>
        <input type="time" id="{{ $p }}_start_time" name="start_time" required value="{{ old('start_time', $screening->start_time ? substr($screening->start_time, 0, 5) : '') }}" data-start-input>
        @error('start_time') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('end_time') has-error @enderror">
        <label for="{{ $p }}_end_time">Ends <span class="req">*</span></label>
        <input type="time" id="{{ $p }}_end_time" name="end_time" value="{{ old('end_time', $screening->end_time ? substr($screening->end_time, 0, 5) : '') }}" data-end-input>
        <span class="hint" data-end-hint hidden>Calculated from the runtime.</span>
        @error('end_time') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>
</section>

<section class="panel" aria-labelledby="{{ $p }}_s3">
<h2 class="panel__title" id="{{ $p }}_s3">Admission</h2>
<div class="field @error('type') has-error @enderror">
    <span class="label">Admission type <span class="req">*</span></span>
    <div class="type-toggle">
        @foreach (App\Models\Screening::TYPES as $type)
            <label><input type="radio" name="type" value="{{ $type }}" data-type-input @checked(old('type', $screening->type ?? 'free') === $type)> {{ $type === 'paid' ? 'Paid' : 'Free' }}</label>
        @endforeach
    </div>
    @error('type') <span class="field__error">{{ $message }}</span> @enderror
</div>

<div class="form-grid">
    <div class="field @error('price') has-error @enderror" data-price-field>
        <label for="{{ $p }}_price">Price per seat (₱) <span class="req">*</span></label>
        <input type="number" id="{{ $p }}_price" name="price" step="0.01" min="0" data-price-input value="{{ old('price', $screening->price) }}">
        @error('price') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>
</section>
</div>
