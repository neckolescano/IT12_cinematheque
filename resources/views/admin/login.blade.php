@extends('layouts.app')

@section('title', 'Admin login · Cinematheque Davao')
@section('body_class', 'login-page')
@section('no_chrome', '1')

@section('content')
    <div class="login-wrap">
        <form method="POST" action="{{ route('admin.login.submit') }}" class="login-card">
            @csrf
            <h1 class="display">ADMIN LOGIN</h1>
            <p class="muted" style="margin:0 0 24px">Sign in to manage movies, cinemas and bookings.</p>

            @include('partials.alerts')

            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="admin@email.com" required autofocus autocomplete="username">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
            </div>

            <label class="remember"><input type="checkbox" name="remember" value="1" style="accent-color:var(--gold)"> Remember me</label>

            <button type="submit" class="btn btn-gold btn-block btn-display">LOG IN</button>
            <p style="text-align:center;margin:20px 0 0"><a href="{{ route('home') }}" class="muted">← Back to the cinema</a></p>
        </form>
    </div>
@endsection
