@extends('layouts.app')

@section('title', 'Admin · Cinematheque Davao')

@section('content')
    <div class="container" style="padding-top:48px;padding-bottom:80px">
        <h1 class="display" style="font-size:3rem">ADMIN</h1>
        <p class="muted">Signed in as {{ auth()->user()->email }}. Movies, cinemas (with payment QR upload), showtimes and bookings come next.</p>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="btn">Log out</button>
        </form>
    </div>
@endsection
