@extends('layouts.app')

@section('title', 'Upcoming screenings')

@section('hero')
    <section class="hero tex-grid">
        @include('partials.skyline')
        <div class="container">
            <span class="eyebrow">Davao City · Philippine cinema</span>
            <h1>Cinema for the <span class="accent">Davao</span> film community</h1>
            <p class="hero__lead">Reserve seats for screenings, retrospectives and talks at Cinematheque Centre Davao. No account needed.</p>

            <form class="filter-bar" method="GET" action="{{ route('home') }}" role="search" data-no-loading>
                <label class="input-icon">
                    <span class="sr-only">Search screenings</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by title or film">
                </label>
                <label>
                    <span class="sr-only">Admission</span>
                    <select name="type">
                        <option value="">Free &amp; paid</option>
                        <option value="free" @selected(($filters['type'] ?? '') === 'free')>Free only</option>
                        <option value="paid" @selected(($filters['type'] ?? '') === 'paid')>Paid only</option>
                    </select>
                </label>
                <button type="submit" class="btn btn--dark">Search</button>
            </form>
        </div>
    </section>
@endsection

@section('content')
    <section id="now-showing">
        <div class="section-head">
            <div>
                <h2>Upcoming screenings</h2>
                <p class="muted small" style="margin:4px 0 0">
                    {{ $screenings->total() }} {{ Str::plural('screening', $screenings->total()) }}
                    @if (array_filter($filters)) matching your search · <a href="{{ route('home') }}">clear</a>@endif
                </p>
            </div>
        </div>

        @if ($screenings->isEmpty())
            <div class="card">
                <x-empty :title="array_filter($filters) ? 'No screenings match your search' : 'No screenings scheduled yet'">
                    {{ array_filter($filters) ? 'Try another title or show both free and paid screenings.' : 'New programmes are posted here as soon as they are confirmed.' }}
                </x-empty>
            </div>
        @else
            <div class="grid grid-3">
                @foreach ($screenings as $screening)
                    @include('public.screenings._card')
                @endforeach
            </div>
            <div class="pagination">{{ $screenings->links() }}</div>
        @endif
    </section>

    <hr class="divider-weave" aria-hidden="true">

    <section>
        <div class="section-head"><h2>How reservations work</h2></div>
        <ol class="steps">
            <li><h3>Pick your seats</h3><p class="muted small" style="margin:0">Choose up to {{ \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION }} seats and name the person in each one.</p></li>
            <li><h3>Pay, or wait for approval</h3><p class="muted small" style="margin:0">Paid screenings: pay securely through PayMongo (GCash, Maya or card). Free screenings: staff approve your booking.</p></li>
            <li><h3>Bring your e-ticket</h3><p class="muted small" style="margin:0">Your e-ticket is emailed once the booking is approved. Staff admit each attendee at the door.</p></li>
        </ol>
    </section>
@endsection
