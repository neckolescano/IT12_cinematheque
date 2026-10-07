@extends('layouts.app')

@section('title', 'Find my booking')
@section('main_class', 'page page--narrow')

@section('content')
    <header class="page-head">
        <span class="eyebrow">No account needed</span>
        <h1 class="display">Find my booking</h1>
    </header>

    @isset($results)
        <p class="muted small">{{ $results->count() }} upcoming bookings for that email.</p>
        <div class="ticket-list">
            @foreach ($results as $r)
                <a class="mini-ticket" href="{{ route('bookings.ticket', $r) }}">
                    <span class="mini-ticket__stub"><x-date-stub :date="$r->screening->event_date" size="sm" /></span>
                    <span class="mini-ticket__body">
                        <strong>{{ $r->screening->event_title }}</strong>
                        <span class="muted small">{{ \Carbon\Carbon::parse($r->screening->start_time)->format('g:i A') }} · {{ ucfirst($r->statusLabel()) }}</span>
                        <span class="ref small">{{ $r->booking_reference }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        <p><a href="{{ route('bookings.lookup') }}">Search again</a></p>
    @else
        {{-- The lookup is a ticket: the reference above the tear, OR the email below it. --}}
        <form method="GET" action="{{ route('bookings.lookup') }}" class="lookup-ticket" novalidate>
            <div class="lookup-ticket__part field @error('reference') has-error @enderror">
                <label for="reference">Booking reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference') }}" placeholder="CCD-XXXXXXXX"
                       maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false" class="lookup-ticket__ref">
                @error('reference') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="lookup-ticket__tear" aria-hidden="true"><span>or</span></div>
            <div class="lookup-ticket__part field @error('email') has-error @enderror">
                <label for="email">Email used for the booking <span class="optional">upcoming bookings</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="100" autocomplete="email" placeholder="you@example.com">
                @error('email') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="btn btn--gold btn--lg btn--block">Find booking</button>
        </form>
    @endisset

    {{-- Bookings opened on this device (kept in this browser only). --}}
    <section class="section-gap" data-saved-bookings hidden aria-labelledby="saved-title">
        <h2 class="section-title" id="saved-title">On this device</h2>
        <div class="ticket-list" data-saved-list></div>
    </section>

    <section class="section-gap" aria-labelledby="how-title">
        <h2 class="section-title" id="how-title">How booking works</h2>
        <ol class="how">
            <li><strong>Choose your seats</strong><span>Pick a screening and up to {{ \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION }} seats, then enter who is using each one.</span></li>
            <li><strong>Paid screenings</strong><span>Pay online through PayMongo within {{ \App\Models\Reservation::PAYMENT_WINDOW_MINUTES }} minutes, or the seats are released.</span></li>
            <li><strong>Free screenings</strong><span>Cinematheque staff review your reservation. Your e-ticket is emailed once it is approved.</span></li>
            <li><strong>At the cinema</strong><span>Show your e-ticket or booking reference at the entrance.</span></li>
        </ol>
    </section>
@endsection
