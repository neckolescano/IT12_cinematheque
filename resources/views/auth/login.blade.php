@extends('layouts.app')

@section('title', 'Staff login')

@section('hero')
    <section class="hero hero--compact tex-grid on-dark">
        @include('partials.skyline')
        <div class="container">
            <span class="eyebrow">Staff only</span>
            <h1>Staff login</h1>
            <p class="hero__lead">For Cinematheque Centre Davao staff. Moviegoers don't need an account.</p>
        </div>
    </section>
@endsection

@section('content')
    <div class="card reveal" style="max-width:440px;margin:0 auto">
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field @error('email') has-error @enderror">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('password') has-error @enderror">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
                @error('password') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <button type="submit" class="btn btn--dark btn--block">Log in</button>
        </form>
    </div>
@endsection
