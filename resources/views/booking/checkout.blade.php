@extends('layouts.app')

@section('title', 'Your details · ' . $booking->showtime->movie->title)

@php
    $showtime = $booking->showtime;
    $movie = $showtime->movie;
    $cinema = $showtime->cinema;
    $charges = $cinema->chargesCustomers();
    $currency = config('cinema.currency');
@endphp

@section('content')
    @include('partials.stepper', ['step' => 4])

    <div class="container">
        <div class="step-nav">
            {{-- Back and Cancel both release the locked seats --}}
            <form method="POST" action="{{ route('booking.cancel', $booking) }}">
                @csrf @method('DELETE')
                <input type="hidden" name="back" value="1">
                <button type="submit" class="link-btn">← Back</button>
            </form>
            <form method="POST" action="{{ route('booking.cancel', $booking) }}">
                @csrf @method('DELETE')
                <button type="submit" class="link-btn">Cancel Booking ✕</button>
            </form>
        </div>

        @include('partials.alerts')

        <div class="checkout-layout">
            <form method="POST" action="{{ route('booking.confirm', $booking) }}" class="card">
                @csrf
                <h1 class="card-title"><span class="icon-box">☰</span> {{ $charges ? 'Payment Details' : 'Your Details' }}</h1>

                <div class="field">
                    <label for="customer_name">Full name</label>
                    <input type="text" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Juan Dela Cruz" required autocomplete="name">
                </div>
                <div class="field">
                    <label for="customer_email">Email</label>
                    <input type="email" id="customer_email" name="customer_email" value="{{ old('customer_email') }}" placeholder="you@email.com" required autocomplete="email">
                    <small>You will use this and your booking reference to find your tickets later.</small>
                </div>
                <div class="field">
                    <label for="customer_phone">Mobile number <span class="muted">(optional)</span></label>
                    <input type="tel" id="customer_phone" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="09XX XXX XXXX" autocomplete="tel">
                </div>

                @if ($charges)
                    <div class="qr-box">
                        @if ($cinema->payment_qr_url)
                            <img src="{{ $cinema->payment_qr_url }}" alt="Payment QR code for {{ $cinema->name }}">
                            <p style="margin:0"><b>Scan to pay {{ $currency }}{{ number_format($booking->total_amount, 2) }}</b></p>
                            <p class="muted" style="margin:4px 0 0">Use your e-wallet or banking app.</p>
                        @else
                            <p style="margin:0">Payment for {{ $cinema->name }} is settled at the counter.<br>Amount due: <b class="gold">{{ $currency }}{{ number_format($booking->total_amount, 2) }}</b></p>
                        @endif
                    </div>
                    <label class="check">
                        <input type="checkbox" name="paid" value="1" required>
                        <span>I have paid {{ $currency }}{{ number_format($booking->total_amount, 2) }}. Staff will verify it at the cinema.</span>
                    </label>
                @else
                    <p class="muted" style="margin:0 0 20px">This booking is free. No payment needed.</p>
                @endif

                <button type="submit" class="btn btn-gold btn-block" style="padding:16px">Confirm Booking</button>
            </form>

            <aside class="card summary">
                <div class="timer" id="timer" data-expires="{{ $booking->expires_at->toIso8601String() }}" data-redirect="{{ route('booking.seats', $showtime) }}">
                    <span class="muted">Time Remaining</span>
                    <b id="timer-text">--:--</b>
                    <span class="muted" style="font-size:.8rem">Seats are currently locked</span>
                </div>

                <h2 style="margin:0 0 16px;font-size:1.25rem">Booking Summary</h2>
                <img class="summary-poster" src="{{ $movie->poster_url }}" alt="">
                <dl>
                    <dt>Movie</dt><dd style="font-size:1.15rem">{{ $movie->title }}</dd>
                    <dt>Cinema</dt><dd>{{ $cinema->name }}</dd>
                    <dt>Date &amp; Time</dt><dd>{{ $showtime->starts_at->format('M j, Y') }}<br>{{ $showtime->starts_at->format('g:i A') }}</dd>
                    <dt>Seats</dt><dd>{{ implode(', ', $booking->seatLabels()) }}</dd>
                </dl>
                <div class="totals">
                    <div class="line"><span>Tickets ({{ $booking->seats->count() }})</span><span>{{ $charges ? $currency . number_format($booking->total_amount, 2) : 'Free' }}</span></div>
                    <div class="line total"><b>Total</b><b class="gold">{{ $charges ? $currency . number_format($booking->total_amount, 2) : 'FREE' }}</b></div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const box = document.getElementById('timer');
    const text = document.getElementById('timer-text');
    const end = new Date(box.dataset.expires).getTime();

    function tick() {
        const left = Math.max(0, Math.floor((end - Date.now()) / 1000));
        text.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
        box.classList.toggle('low', left <= 60);
        if (left === 0) { window.location.href = box.dataset.redirect; return; }
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
@endpush
