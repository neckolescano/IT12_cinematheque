{{-- Screening fields, shared by the create/edit drawers and the no-JS form page. --}}
@php($p = $prefix ?? 'f')
<div class="field @error('event_title') has-error @enderror">
    <label for="{{ $p }}_event_title">Event title <span class="req">*</span></label>
    <input type="text" id="{{ $p }}_event_title" name="event_title" maxlength="150" required value="{{ old('event_title', $screening->event_title) }}" placeholder="e.g. Shorts Night: Mindanao Filmmakers">
    @error('event_title') <span class="field__error">{{ $message }}</span> @enderror
</div>

<div class="field @error('movie_id') has-error @enderror">
    <label for="{{ $p }}_movie_id">Cataloged film <span class="hint">(optional)</span></label>
    <select id="{{ $p }}_movie_id" name="movie_id">
        <option value="">None — festival, talk or shorts programme</option>
        @foreach ($movies as $movie)
            <option value="{{ $movie->movie_id }}" @selected(old('movie_id', $screening->movie_id) == $movie->movie_id)>{{ $movie->title }}@if ($movie->release_year) ({{ $movie->release_year }})@endif</option>
        @endforeach
    </select>
    @error('movie_id') <span class="field__error">{{ $message }}</span> @enderror
</div>

<div class="form-grid">
    <div class="field @error('event_date') has-error @enderror">
        <label for="{{ $p }}_event_date">Date <span class="req">*</span></label>
        <input type="date" id="{{ $p }}_event_date" name="event_date" required value="{{ old('event_date', $screening->event_date?->format('Y-m-d')) }}">
        @error('event_date') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('start_time') has-error @enderror">
        <label for="{{ $p }}_start_time">Starts <span class="req">*</span></label>
        <input type="time" id="{{ $p }}_start_time" name="start_time" required value="{{ old('start_time', $screening->start_time ? substr($screening->start_time, 0, 5) : '') }}">
        @error('start_time') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('end_time') has-error @enderror">
        <label for="{{ $p }}_end_time">Ends <span class="req">*</span></label>
        <input type="time" id="{{ $p }}_end_time" name="end_time" required value="{{ old('end_time', $screening->end_time ? substr($screening->end_time, 0, 5) : '') }}">
        @error('end_time') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>

<div class="field @error('type') has-error @enderror">
    <span class="label">Admission <span class="req">*</span></span>
    <div class="type-toggle">
        @foreach (App\Models\Screening::TYPES as $type)
            <label><input type="radio" name="type" value="{{ $type }}" @checked(old('type', $screening->type ?? 'free') === $type)> {{ $type === 'paid' ? 'Paid (PayMongo)' : 'Free' }}</label>
        @endforeach
    </div>
    @error('type') <span class="field__error">{{ $message }}</span> @enderror
</div>

<div class="form-grid">
    <div class="field @error('price') has-error @enderror">
        <label for="{{ $p }}_price">Price per seat (₱)</label>
        <input type="number" id="{{ $p }}_price" name="price" step="0.01" min="0" value="{{ old('price', $screening->price) }}" placeholder="Paid screenings only">
        @error('price') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field @error('total_seats') has-error @enderror">
        <label for="{{ $p }}_total_seats">Total seats <span class="req">*</span></label>
        <input type="number" id="{{ $p }}_total_seats" name="total_seats" min="1" required value="{{ old('total_seats', $screening->total_seats) }}">
        @error('total_seats') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>
