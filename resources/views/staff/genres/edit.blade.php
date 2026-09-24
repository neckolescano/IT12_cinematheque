@extends('layouts.staff')

@section('title', 'Edit genre')

@section('content')
    <div class="page-head">
        <div>
            <a class="crumb" style="color:var(--muted)" href="{{ route('staff.genres.index') }}">&larr; Genres</a>
            <h1>Edit genre</h1>
        </div>
    </div>

    <form class="card reveal" style="max-width:480px" method="POST" action="{{ route('staff.genres.update', $genre) }}">
        @csrf @method('PUT')
        <div class="field @error('genre_name') has-error @enderror">
            <label for="genre_name">Genre name <span class="req">*</span></label>
            <input type="text" id="genre_name" name="genre_name" maxlength="50" required value="{{ old('genre_name', $genre->genre_name) }}">
            @error('genre_name') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Save</button>
            <a class="btn btn--ghost" href="{{ route('staff.genres.index') }}">Cancel</a>
        </div>
    </form>
@endsection
