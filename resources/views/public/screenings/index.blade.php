@extends('layouts.app')

@section('title', 'Upcoming screenings')

@php
    $type = $filters['type'] ?? '';
    $q = $filters['q'] ?? '';
@endphp

@section('hero')
    <section class="hero">
        @include('partials.skyline')
        <div class="container hero__inner">
            <span class="eyebrow">FDCP · Davao City · Philippine cinema</span>
            <h1 class="display">Now showing</h1>
        </div>
        <div class="container">
            <form class="hero__search" method="GET" action="{{ route('home') }}" role="search" data-no-loading>
                @if ($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <label class="sr-only" for="q">Search screenings</label>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" id="q" name="q" value="{{ $q }}" placeholder="Search by title or film" autocomplete="off">
            </form>
        </div>
    </section>
@endsection

@section('content')
    <div class="filters">
        <nav class="tabs" aria-label="Admission">
            @foreach (['' => 'All', 'free' => 'Free', 'paid' => 'Paid'] as $key => $label)
                <a href="{{ route('home', array_filter(['q' => $q, 'type' => $key])) }}" @if ($type === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <span class="muted small">{{ $total }} {{ Str::plural('screening', $total) }}</span>
    </div>

    @if ($total === 0)
        <x-empty :title="($q || $type) ? 'No matching screenings' : 'No upcoming screenings'">
            @if ($q || $type)<a href="{{ route('home') }}">Clear search and filter</a>@endif
        </x-empty>
    @else
        @if ($nowShowing->isNotEmpty())
            <section aria-labelledby="week-title">
                <h2 class="section-title" id="week-title">This week</h2>
                <div class="movie-grid">
                    @foreach ($nowShowing as $screening)
                        @include('public.screenings._card')
                    @endforeach
                </div>
            </section>
        @endif

        @if ($upcoming->isNotEmpty())
            <section class="section-gap" aria-labelledby="upcoming-title">
                <h2 class="section-title" id="upcoming-title">Upcoming</h2>
                <div class="ticket-list">
                    @foreach ($upcoming as $screening)
                        @include('public.screenings._ticket')
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endsection
