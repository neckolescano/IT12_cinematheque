@extends('layouts.app')

@section('title', 'My tickets · Cinematheque Davao')

@section('content')
    <div class="container" style="max-width:560px;padding-top:56px;padding-bottom:80px">
        <h1 class="display" style="font-size:3rem">MY TICKETS</h1>
        <p class="muted">No account needed. Enter the booking reference and email you used when booking.</p>

        @include('partials.alerts')

        <form method="POST" action="{{ route('tickets.find') }}" class="card" style="margin-top:24px">
            @csrf
            <div class="field">
                <label for="reference">Booking reference</label>
                <input type="text" id="reference" name="reference" value="{{ old('reference') }}" placeholder="CZDF5HHWIW" required autocomplete="off" style="text-transform:uppercase">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@email.com" required>
            </div>
            <button type="submit" class="btn btn-gold btn-block">Find my booking</button>
        </form>
    </div>
@endsection
