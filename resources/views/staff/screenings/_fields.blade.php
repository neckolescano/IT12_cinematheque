{{-- The one Add/Edit Screening form (create and edit pages), in four sections.
     Every screening is filed under a program and shows one film record (a shorts block or a talk is a film
     record too, with its number of films). The film's details are entered right here; picking a title already
     in the catalog fills them in. Genres come from a fixed list plus "Other"; director and actors are typed. --}}
@php
    $p = $prefix ?? 'f';
    $movie = $screening->movie;
    $catalog = $movies->map(fn ($m) => [
        'title' => $m->title, 'runtime' => $m->runtime_minutes, 'rating' => $m->rating, 'year' => $m->release_year,
        'genres' => $m->genres->pluck('genre_name')->all(), 'directors' => $m->directorNames(), 'actors' => $m->castNames(),
        'programs' => $m->programs->pluck('name')->all(),
    ])->values();
    $more = collect(old('more', []))->values();
@endphp
<div data-screening-form data-catalog='@json($catalog)'>

<section class="panel" aria-labelledby="{{ $p }}_s1">
<h2 class="panel__title" id="{{ $p }}_s1">What’s showing</h2>
{{-- The program this screening is reported under: one of the film's tags, or a new one typed here. --}}
<div class="field @error('program') has-error @enderror">
    <label for="{{ $p }}_program">Program <span class="req">*</span></label>
    <input type="text" id="{{ $p }}_program" name="program" maxlength="100" required list="{{ $p }}_programs" autocomplete="off"
           value="{{ old('program', $screening->program?->name) }}" placeholder="e.g. Cinemalaya 2026" data-program-input>
    <datalist id="{{ $p }}_programs">@foreach ($programs as $name)<option value="{{ $name }}">@endforeach</datalist>
    @error('program') <span class="field__error">{{ $message }}</span> @enderror
</div>

<fieldset class="film-fields" data-film-fields>
    <legend class="sr-only">Film details</legend>
    <div class="field @error('film_title') has-error @enderror">
        <label for="{{ $p }}_film_title">Film title <span class="req">*</span></label>
        <input type="text" id="{{ $p }}_film_title" name="film_title" maxlength="150" list="{{ $p }}_catalog" autocomplete="off" required
               value="{{ old('film_title', $movie?->title) }}" data-film-title aria-describedby="{{ $p }}_film_hint">
        <span class="hint" id="{{ $p }}_film_hint">Shorts block or talk: enter its title.</span>
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

<div class="form-grid">
    <div class="field @error('event_title') has-error @enderror">
        <label for="{{ $p }}_event_title">Screening title <span class="hint">(optional, uses the film title if blank)</span></label>
        <input type="text" id="{{ $p }}_event_title" name="event_title" maxlength="150" data-event-title value="{{ old('event_title', $movie && $screening->event_title === $movie->title ? '' : $screening->event_title) }}">
        @error('event_title') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('films_count') has-error @enderror">
        <label for="{{ $p }}_films_count">No. of films <span class="req">*</span></label>
        <input type="number" id="{{ $p }}_films_count" name="films_count" min="1" max="50" required value="{{ old('films_count', $screening->films_count ?? 1) }}" aria-describedby="{{ $p }}_films_hint">
        <span class="hint" id="{{ $p }}_films_hint">1 for a feature; a shorts block counts its films.</span>
        @error('films_count') <span class="field__error">{{ $message }}</span> @enderror
    </div>
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

{{-- Batch scheduling (new screenings only): repeat the showtime daily or weekly, and/or add other showtimes.
     Every one gets the same film, program, admission and length; none may overlap another screening. --}}
@unless ($screening->exists)
    <div class="form-grid">
        <div class="field @error('repeat') has-error @enderror">
            <label for="{{ $p }}_repeat">Repeat</label>
            <select id="{{ $p }}_repeat" name="repeat" data-repeat>
                @foreach (['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('repeat', 'none') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="field @error('repeat_until') has-error @enderror" data-repeat-until @if (old('repeat', 'none') === 'none') hidden @endif>
            <label for="{{ $p }}_repeat_until">Until <span class="req">*</span></label>
            <input type="date" id="{{ $p }}_repeat_until" name="repeat_until" value="{{ old('repeat_until') }}">
            @error('repeat_until') <span class="field__error">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="more-showtimes" data-more-showtimes>
        <span class="label">Other showtimes</span>
        <div data-more-rows>
            @foreach ($more->isEmpty() ? [['date' => '', 'start' => '']] : $more as $i => $row)
                <div class="more-showtimes__row" data-more-row>
                    <label class="sr-only" for="{{ $p }}_more_{{ $i }}_date">Date</label>
                    <input type="date" id="{{ $p }}_more_{{ $i }}_date" name="more[{{ $i }}][date]" value="{{ $row['date'] ?? '' }}">
                    <label class="sr-only" for="{{ $p }}_more_{{ $i }}_start">Starts</label>
                    <input type="time" id="{{ $p }}_more_{{ $i }}_start" name="more[{{ $i }}][start]" value="{{ $row['start'] ?? '' }}">
                    <button type="button" class="btn btn--ghost btn--sm" data-more-remove aria-label="Remove this showtime">Remove</button>
                </div>
            @endforeach
        </div>
        <button type="button" class="btn btn--secondary btn--sm" data-more-add>Add showtime</button>
        @error('more') <span class="field__error">{{ $message }}</span> @enderror
        @error('more.*') <span class="field__error">{{ $message }}</span> @enderror
    </div>
@endunless
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

{{-- Columns of the Manila report; they can also be filled in later on the program's report. --}}
<section class="panel" aria-labelledby="{{ $p }}_s4">
<h2 class="panel__title" id="{{ $p }}_s4">Report details <span class="optional">optional</span></h2>
<div class="form-grid">
    <div class="field @error('partner') has-error @enderror">
        <label for="{{ $p }}_partner">Partner</label>
        <input type="text" id="{{ $p }}_partner" name="partner" maxlength="150" value="{{ old('partner', $screening->partner) }}" placeholder="e.g. OWWA Region XI">
        @error('partner') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('agency_type') has-error @enderror">
        <label for="{{ $p }}_agency_type">Type of agency</label>
        <input type="text" id="{{ $p }}_agency_type" name="agency_type" maxlength="100" value="{{ old('agency_type', $screening->agency_type) }}" placeholder="e.g. Government, School, NGO">
        @error('agency_type') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>
<div class="field @error('notes') has-error @enderror" style="margin:0">
    <label for="{{ $p }}_notes">Notes</label>
    <textarea id="{{ $p }}_notes" name="notes" rows="2" maxlength="2000">{{ old('notes', $screening->notes) }}</textarea>
    @error('notes') <span class="field__error">{{ $message }}</span> @enderror
</div>
</section>
</div>
