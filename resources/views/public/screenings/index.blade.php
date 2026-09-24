@extends('layouts.app')

@section('title', 'Upcoming screenings')

@php($featured = $screenings->onFirstPage() ? $screenings->first() : null)

@section('hero')
    <section class="hero tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            <span class="eyebrow">Davao City · Philippine cinema</span>
            <h1>Cinema for the <span class="accent">Davao</span> film community</h1>
            <p class="hero__lead">Reserve your seats for screenings, retrospectives and talks at Cinematheque Centre Davao. No account needed.</p>
            <div class="cluster">
                <a class="btn btn--primary" href="#now-showing">See what's showing <span class="arrow">&darr;</span></a>
                <a class="btn btn--ghost" href="{{ route('bookings.lookup') }}">Find my booking</a>
            </div>

            @if ($featured)
                @php($left = max(0, $featured->total_seats - $featured->reservation_seats_count))
                <div class="feature reveal">
                    <x-poster :screening="$featured" :tall="true">
                        <span class="badge badge--gold badge--plain">Next up</span>
                    </x-poster>
                    <div>
                        <span class="eyebrow">Next screening</span>
                        <h2 style="font-size:var(--fs-2xl);color:#fff">{{ $featured->event_title }}</h2>
                        <div class="cluster" style="gap:var(--s-4);margin:var(--s-4) 0">
                            <x-date-badge :date="$featured->event_date" />
                            <div>
                                <div style="font-weight:600;color:#fff">{{ $featured->event_date->format('l, F j, Y') }}</div>
                                <div class="muted">{{ substr($featured->start_time, 0, 5) }} – {{ substr($featured->end_time, 0, 5) }}</div>
                            </div>
                        </div>
                        <div class="cluster" style="margin-bottom:var(--s-5)">
                            <span class="badge {{ $featured->isPaid() ? 'badge--gold' : 'badge--success' }} badge--plain">{{ $featured->isPaid() ? '₱'.number_format($featured->price, 2).' per seat' : 'Free admission' }}</span>
                            <span class="badge badge--dark badge--plain">{{ $left === 0 ? 'Fully booked' : $left.' of '.$featured->total_seats.' seats left' }}</span>
                        </div>
                        <div class="cluster">
                            @if ($left > 0)
                                <a class="btn btn--primary" href="{{ route('bookings.create', $featured) }}">Reserve seats <span class="arrow">&rarr;</span></a>
                            @endif
                            <a class="btn btn--ghost" href="{{ route('screenings.show', $featured) }}">Details</a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
    <div class="filmstrip" aria-hidden="true"></div>
@endsection

@section('content')
    <section id="now-showing" style="scroll-margin-top:90px">
        <div class="section-head reveal">
            <div>
                <span class="eyebrow">Now scheduled</span>
                <h2>Upcoming screenings</h2>
            </div>
            @can('create', App\Models\Screening::class)
                <a class="btn btn--dark btn--sm" href="{{ route('staff.screenings.create') }}">+ New screening (staff)</a>
            @endcan
        </div>

        @if ($screenings->isEmpty())
            <div class="card">
                <x-empty title="No screenings scheduled yet">New programmes are posted here as soon as they're confirmed. Please check back soon.</x-empty>
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

    <section class="reveal">
        <div class="section-head">
            <div>
                <span class="eyebrow">How reservations work</span>
                <h2>Three steps to your seat</h2>
            </div>
        </div>
        <ol class="steps">
            <li><h3>Pick your seats</h3><p class="muted small">Choose up to {{ \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION }} seats and name the person in each one.</p></li>
            <li><h3>Pay if it's a paid screening</h3><p class="muted small">Scan the Cinematheque QR with your e-wallet or bank app, then upload a screenshot. Staff confirm it — we never process payments ourselves.</p></li>
            <li><h3>Show your reference</h3><p class="muted small">Give your booking reference at the door. Staff admit each attendee on arrival.</p></li>
        </ol>
    </section>
@endsection
