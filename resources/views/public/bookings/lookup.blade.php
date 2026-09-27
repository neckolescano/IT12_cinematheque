@extends('layouts.app')

@section('title', 'Find my booking')

@section('content')
    <div class="card" style="max-width:520px;margin:var(--s-5) auto 0">
        <h1 class="card__title" style="font-size:var(--fs-xl)">
            <span class="card__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></span>
            Find my booking
        </h1>
        <p class="muted small">Enter your booking reference to see your seats, complete payment, or view your e-ticket.</p>
        <form method="GET" action="{{ route('bookings.lookup') }}">
            <div class="field @error('reference') has-error @enderror">
                <label for="reference">Booking reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference') }}" placeholder="CCD-XXXXXXXX" required
                       autocomplete="off" autocapitalize="characters" spellcheck="false" style="font-size:var(--fs-lg);letter-spacing:.06em">
                @error('reference') <span class="field__error">{{ $message }}</span> @enderror
                <span class="hint">It starts with CCD- and is in your confirmation email.</span>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Find booking</button>
        </form>
    </div>
@endsection
