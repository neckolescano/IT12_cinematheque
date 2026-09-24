@extends('layouts.staff')

@section('title', 'Edit '.strtolower($label))

@section('content')
    <div class="page-head">
        <div>
            <a class="crumb" style="color:var(--muted)" href="{{ route($routePrefix.'.index') }}">&larr; {{ $label }}s</a>
            <h1>Edit {{ strtolower($label) }}</h1>
        </div>
    </div>

    <form class="card reveal" style="max-width:560px" method="POST" action="{{ route($routePrefix.'.update', $person) }}">
        @csrf @method('PUT')
        <div class="field @error('first_name') has-error @enderror">
            <label for="first_name">First name <span class="req">*</span></label>
            <input type="text" id="first_name" name="first_name" maxlength="50" required value="{{ old('first_name', $person->first_name) }}">
            @error('first_name') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="middle_name">Middle name</label>
            <input type="text" id="middle_name" name="middle_name" maxlength="50" value="{{ old('middle_name', $person->middle_name) }}">
        </div>
        <div class="field @error('last_name') has-error @enderror">
            <label for="last_name">Last name <span class="req">*</span></label>
            <input type="text" id="last_name" name="last_name" maxlength="50" required value="{{ old('last_name', $person->last_name) }}">
            @error('last_name') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Save</button>
            <a class="btn btn--ghost" href="{{ route($routePrefix.'.index') }}">Cancel</a>
        </div>
    </form>
@endsection
