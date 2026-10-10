@extends('layouts.app')

@section('title', 'Upcoming screenings')

@php
    $type = $filters['type'] ?? '';
    $q = $filters['q'] ?? '';
@endphp

@section('hero')
    {{-- Full-width looping spotlight straight under the header, on the page background (no band behind it). --}}
    @if ($featured->isNotEmpty())
        @include('public.screenings._spotlight')
        <div class="container spotlight-cta">
            <a class="btn btn--secondary" href="#showcase">See full schedule <x-arrow class="arrow" /></a>
        </div>
    @endif
@endsection

@section('content')
    {{-- Page heading and search on one row, above the tabs. --}}
    <div class="finder">
        <div>
            <span class="eyebrow">FDCP · Davao City · Philippine cinema</span>
            <h1 class="display finder__title">Screenings</h1>
        </div>
        <form class="finder__search" method="GET" action="{{ route('home') }}#showcase" role="search" data-no-loading>
            @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            <label class="sr-only" for="q">Search screenings</label>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" id="q" name="q" value="{{ $q }}" placeholder="Search films and programmes" autocomplete="off">
        </form>
    </div>

    {{-- Poster showcase: date tabs, admission filter, poster cards. --}}
    @include('public.screenings._showcase')
    @include('public.screenings._trailer-modal')
@endsection
