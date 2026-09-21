@extends('layouts.app')

@section('title', 'Tickets · ' . $booking->reference)

@php
    $showtime = $booking->showtime;
    $movie = $showtime->movie;
@endphp

@section('content')
    <div class="container" style="padding-top:32px;padding-bottom:64px">
        <div class="step-nav no-print" style="padding-top:0">
            <a href="{{ route('booking.show', $booking) }}" class="link-btn">← Back to confirmation</a>
            <button type="button" class="btn btn-gold" onclick="window.print()">Print / Save as PDF</button>
        </div>

        @foreach ($booking->seatLabels() as $seat)
            <article class="ticket">
                <div>
                    <h2>{{ $movie->title }}</h2>
                    <p class="muted" style="margin:0 0 14px">{{ $showtime->cinema->name }} • {{ $showtime->starts_at->format('l, F j, Y') }} • {{ $showtime->starts_at->format('g:i A') }}</p>
                    <p style="margin:0">{{ $booking->customer_name }}</p>
                    <p class="muted" style="margin:6px 0 0">Reference: <b class="gold">{{ $booking->reference }}</b></p>
                </div>
                <div class="seat-big">
                    <span class="muted">SEAT</span>
                    <b>{{ $seat }}</b>
                </div>
            </article>
        @endforeach
    </div>
@endsection

@push('scripts')
<script>
    if (new URLSearchParams(location.search).has('print')) window.addEventListener('load', () => window.print());
</script>
@endpush
