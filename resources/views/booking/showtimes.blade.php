@extends('layouts.app')

@section('title', 'Choose date & time · ' . $movie->title)

@section('content')
    @include('partials.stepper', ['step' => 2])

    <div class="container">
        <div class="step-nav">
            <a href="{{ route('movies.index') }}" class="link-btn">← Back</a>
            <a href="{{ route('movies.index') }}" class="link-btn">Cancel Booking ✕</a>
        </div>

        <div class="booking-layout">
            <aside class="card side-movie">
                <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }} poster">
                <h2>{{ $movie->title }}</h2>
                <div class="chips">
                    @if ($movie->age_rating)<span class="tag">{{ $movie->age_rating }}</span>@endif
                    <span>{{ $movie->genre }}</span>
                    <span>{{ $movie->duration_minutes }} min</span>
                    <span>{{ $movie->language }}</span>
                </div>
                @if ($movie->synopsis)<p>{{ $movie->synopsis }}</p>@endif
            </aside>

            <div class="stack">
                @if ($dates->isEmpty())
                    <div class="card">
                        <p class="empty" style="padding:0">No upcoming showtimes for this movie yet. Please check back later.</p>
                        <a href="{{ route('movies.index') }}" class="btn btn-ghost">Browse other movies</a>
                    </div>
                @else
                    <section class="card">
                        <h2 class="card-title"><span class="icon-box">▦</span> Select Date</h2>
                        <div class="date-strip">
                            @foreach ($dates as $date)
                                @php $d = \Carbon\Carbon::parse($date); @endphp
                                <a href="{{ route('booking.showtimes', [$movie, 'date' => $date]) }}"
                                   class="date-chip {{ $date === $selectedDate ? 'active' : '' }}"
                                   @if($date === $selectedDate) aria-current="date" @endif>
                                    <span>{{ $d->format('D') }}</span>
                                    <strong>{{ $d->format('j') }}</strong>
                                    <span>{{ $d->format('M') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>

                    <section class="card">
                        <h2 class="card-title"><span class="icon-box">◷</span> Select Showtime</h2>
                        <div class="time-pills" id="time-pills">
                            @foreach ($times as $showtime)
                                @php $soldOut = $showtime->seats_left <= 0; @endphp
                                <label class="time-pill {{ $soldOut ? 'sold' : '' }}">
                                    <input type="radio" name="showtime" value="{{ $showtime->id }}"
                                           data-url="{{ route('booking.seats', $showtime) }}" @disabled($soldOut)>
                                    <span>
                                        <b>{{ $showtime->starts_at->format('g:i A') }}</b>
                                        <small>{{ $soldOut ? 'Sold Out' : $showtime->seats_left . ' seats' }}</small>
                                        <small>
                                            {{ $showtime->cinema->name }}
                                            @if ($showtime->cinema->chargesCustomers())
                                                · <span class="pay-tag">{{ config('cinema.currency') }}{{ number_format($showtime->cinema->ticket_price, 0) }}</span>
                                            @endif
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <button type="button" id="continue-btn" class="btn btn-gold btn-block" style="padding:20px" disabled>Please select a showtime</button>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const btn = document.getElementById('continue-btn');
        if (!btn) return;
        let url = null;
        document.querySelectorAll('#time-pills input[type=radio]').forEach(radio => {
            radio.addEventListener('change', () => {
                url = radio.dataset.url;
                btn.disabled = false;
                btn.textContent = 'Continue to seat selection';
            });
        });
        btn.addEventListener('click', () => { if (url) window.location.href = url; });
    })();
</script>
@endpush
