@extends('layouts.app')

@section('title', 'Find my booking')

@section('hero')
    <section class="hero hero--compact tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            <span class="eyebrow">Already reserved?</span>
            <h1>Find my booking</h1>
            <p class="hero__lead">Enter your booking reference to see your seats, pay, or upload proof of payment.</p>
        </div>
    </section>
@endsection

@section('content')
    <div class="card reveal" style="max-width:520px;margin:0 auto">
        <form method="GET" action="{{ route('bookings.lookup') }}">
            <div class="field @error('reference') has-error @enderror">
                <label for="reference">Booking reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference') }}" placeholder="CCD-XXXXXXXX" required
                       autocomplete="off" autocapitalize="characters" spellcheck="false" style="font-size:var(--fs-lg);letter-spacing:.06em">
                @error('reference') <span class="field__error">{{ $message }}</span> @enderror
                <span class="hint">It starts with CCD- and was shown when you reserved.</span>
            </div>
            <button type="submit" class="btn btn--primary btn--block">Find booking</button>
        </form>
    </div>
@endsection
