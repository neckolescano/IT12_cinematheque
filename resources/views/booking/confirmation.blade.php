@extends('layouts.app')

@section('title', 'Booking confirmed · Cinematheque Davao')

@php
    $showtime = $booking->showtime;
    $cinema = $showtime->cinema;
    $charges = $cinema->chargesCustomers();
    $currency = config('cinema.currency');
@endphp

@section('content')
    @include('partials.stepper', ['step' => 5])

    <div class="container">
        <div class="confirm-wrap">
            <div class="confirm-card">
                <div class="success-icon"><span>✓</span></div>
                <h1>Booking Confirmed!</h1>
                <p class="muted" style="margin:0">Your seats are reserved, {{ strtok($booking->customer_name, ' ') }}.</p>

                <div class="ticket-summary">
                    <div class="ticket-head">
                        <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }} poster">
                        <div>
                            <h2>{{ $movie->title }}</h2>
                            <span class="muted">{{ $movie->genre }}@if($movie->age_rating) • {{ $movie->age_rating }}@endif</span>
                        </div>
                        <div class="ref">
                            <small>Booking reference</small>
                            <code>{{ $booking->reference }}</code>
                        </div>
                    </div>
                    <div class="ticket-grid">
                        <div><small>Date</small><b>{{ $showtime->starts_at->format('l, F j, Y') }}</b></div>
                        <div><small>Time</small><b>{{ $showtime->starts_at->format('g:i A') }}</b></div>
                        <div><small>Cinema</small><b>{{ $cinema->name }}</b></div>
                        <div><small>Seats</small><b>{{ implode(', ', $booking->seatLabels()) }}</b></div>
                        <div><small>Total</small><b class="gold">{{ $charges ? $currency . number_format($booking->total_amount, 2) : 'Free' }}</b></div>
                        @if ($charges)
                            <div><small>Payment</small><b>{{ $booking->payment_status === 'paid' ? 'Verified' : 'Waiting for staff to verify' }}</b></div>
                        @endif
                    </div>
                    <div class="ticket-note">Save your booking reference. You can find your tickets any time under “My tickets”.</div>
                </div>

                <div class="confirm-actions">
                    <a href="{{ route('booking.tickets', $booking) }}" class="btn btn-gold">View Tickets</a>
                    <a href="{{ route('booking.tickets', [$booking, 'print' => 1]) }}" class="btn">Save as PDF</a>
                </div>
                <a href="{{ route('home') }}" class="back-home">Back to Home</a>
            </div>
        </div>

        @if ($similar->isNotEmpty())
            <div class="rec-head">
                <h2>You Might Also Like</h2>
                <p class="muted" style="margin:4px 0 0">Based on your booking of {{ $movie->title }}</p>
            </div>
            <div class="movie-grid" style="padding-bottom:64px">
                @foreach ($similar as $rec)
                    @include('partials.movie-card', ['movie' => $rec])
                @endforeach
            </div>
        @endif
    </div>
@endsection
