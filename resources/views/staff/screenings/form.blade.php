@extends('layouts.staff')

{{-- No-JavaScript fallback for the create/edit drawer. --}}
@section('title', $screening->exists ? 'Edit screening' : 'New screening')

@section('content')
    <div class="page-head">
        <div>
            <a class="crumb" href="{{ $screening->exists ? route('staff.screenings.show', $screening) : route('staff.screenings.index') }}">&larr; {{ $screening->exists ? $screening->event_title : 'Screenings' }}</a>
            <h1>{{ $screening->exists ? 'Edit screening' : 'New screening' }}</h1>
        </div>
    </div>

    <form class="card" style="max-width:720px" method="POST" action="{{ $screening->exists ? route('staff.screenings.update', $screening) : route('staff.screenings.store') }}">
        @csrf
        @if ($screening->exists) @method('PUT') @endif
        @include('staff.screenings._fields', ['prefix' => 'page'])
        <div class="form-actions">
            <button type="submit" class="btn btn--primary">{{ $screening->exists ? 'Save changes' : 'Create screening' }}</button>
            <a class="btn btn--ghost" href="{{ $screening->exists ? route('staff.screenings.show', $screening) : route('staff.screenings.index') }}">Cancel</a>
        </div>
    </form>
@endsection
