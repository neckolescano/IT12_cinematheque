@extends('layouts.staff')

@php($editing = $screening->exists)

@section('title', $editing ? 'Edit screening' : 'New screening')

@section('content')
    <div class="page-head">
        <div>
            <a class="crumb" style="color:var(--muted)" href="{{ $editing ? route('staff.screenings.show', $screening) : route('staff.screenings.index') }}">&larr; {{ $editing ? $screening->event_title : 'Screenings' }}</a>
            <h1>{{ $editing ? 'Edit screening' : 'New screening' }}</h1>
        </div>
    </div>

    <form class="card reveal" style="max-width:860px" method="POST" action="{{ $editing ? route('staff.screenings.update', $screening) : route('staff.screenings.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="field @error('event_title') has-error @enderror">
            <label for="event_title">Event title <span class="req">*</span></label>
            <input type="text" id="event_title" name="event_title" maxlength="150" required value="{{ old('event_title', $screening->event_title) }}">
            @error('event_title') <span class="field__error">{{ $message }}</span> @enderror
        </div>

        <div class="field @error('movie_id') has-error @enderror">
            <label for="movie_id">Cataloged movie</label>
            <select id="movie_id" name="movie_id">
                <option value="">— None (festival, talk, shorts programme) —</option>
                @foreach ($movies as $movie)
                    <option value="{{ $movie->movie_id }}" @selected(old('movie_id', $screening->movie_id) == $movie->movie_id)>
                        {{ $movie->title }} @if ($movie->release_year) ({{ $movie->release_year }}) @endif
                    </option>
                @endforeach
            </select>
            <span class="hint">Optional. Link a film to show its cast, director and synopsis on the public page.</span>
            @error('movie_id') <span class="field__error">{{ $message }}</span> @enderror
        </div>

        <div class="form-grid">
            <div class="field @error('event_date') has-error @enderror">
                <label for="event_date">Date <span class="req">*</span></label>
                <input type="date" id="event_date" name="event_date" required value="{{ old('event_date', $screening->event_date?->format('Y-m-d')) }}">
                @error('event_date') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('start_time') has-error @enderror">
                <label for="start_time">Start time <span class="req">*</span></label>
                <input type="time" id="start_time" name="start_time" required value="{{ old('start_time', $screening->start_time ? substr($screening->start_time, 0, 5) : '') }}">
                @error('start_time') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('end_time') has-error @enderror">
                <label for="end_time">End time <span class="req">*</span></label>
                <input type="time" id="end_time" name="end_time" required value="{{ old('end_time', $screening->end_time ? substr($screening->end_time, 0, 5) : '') }}">
                @error('end_time') <span class="field__error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-grid">
            <div class="field @error('type') has-error @enderror">
                <label for="type">Admission <span class="req">*</span></label>
                <select id="type" name="type">
                    @foreach (App\Models\Screening::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('type', $screening->type) === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                @error('type') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('price') has-error @enderror">
                <label for="price">Price per seat (₱)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="{{ old('price', $screening->price) }}">
                <span class="hint">Required for paid screenings.</span>
                @error('price') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('total_seats') has-error @enderror">
                <label for="total_seats">Total seats <span class="req">*</span></label>
                <input type="number" id="total_seats" name="total_seats" min="1" required value="{{ old('total_seats', $screening->total_seats) }}">
                @error('total_seats') <span class="field__error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">{{ $editing ? 'Save changes' : 'Create screening' }}</button>
            <a class="btn btn--ghost" href="{{ $editing ? route('staff.screenings.show', $screening) : route('staff.screenings.index') }}">Cancel</a>
        </div>
    </form>
@endsection
