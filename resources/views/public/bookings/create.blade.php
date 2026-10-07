@extends('layouts.app')

@section('title', ($selected->isEmpty() ? 'Choose seats' : "Who's coming?").' — '.$screening->event_title)

@php
    $max = \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION;
    $step = $selected->isEmpty() ? 1 : 2;
    // All 120 seats as rows A–L, in seat order.
    // Sorted by label (row letter, then number), not by id: rows K–L were added after A–J.
    $rows = $seats->sortBy(fn ($seat) => preg_match('/^([A-Za-z]+)(\d+)/', $seat->seat_label, $m) ? sprintf('%s%04d', strtoupper($m[1]), $m[2]) : $seat->seat_label)
        ->groupBy(fn ($seat) => preg_match('/^([A-Za-z]+)/', $seat->seat_label, $m) ? strtoupper($m[1]) : '·');
    $crumbs = ['Screenings' => route('home'), $screening->event_title => route('screenings.show', $screening)];
    $crumbs += $step === 1 ? ['Seats' => null] : ['Seats' => route('bookings.create', $screening), 'Details' => null];
@endphp

@section('content')
    <x-crumbs :items="$crumbs" />
    <x-stepper :current="$step" :paid="$screening->isPaid()" />

    @if ($step === 1)
        {{-- Step 1: summary on the left, the 120-seat map on the right. --}}
        <form method="GET" action="{{ route('bookings.create', $screening) }}" class="booking" data-seat-picker data-max="{{ $max }}" data-price="{{ $screening->isPaid() ? $screening->price : 0 }}" data-no-loading>
            <div class="booking__side">
                @include('public.bookings._summary', ['screening' => $screening])
                <button type="submit" class="btn btn--gold btn--block btn--lg" data-seat-submit>Continue <x-arrow class="arrow" /></button>
                <p class="muted small booking__hint">Up to {{ $max }} seats. The first seat you pick is yours.</p>
            </div>

            <section class="booking__main seat-map" aria-labelledby="seats-title">
                <div class="seat-map__head">
                    <h1 id="seats-title" class="booking__title">Select your seats</h1>
                    <span class="muted small">{{ $available }} of {{ $screening->total_seats }} left</span>
                </div>
                <div class="screen-bar" aria-hidden="true">Screen</div>
                <div class="seat-grid">
                    @foreach ($rows as $row => $rowSeats)
                        <div class="seat-row">
                            <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                            @foreach ($rowSeats as $seat)
                                @php($taken = in_array($seat->seat_id, $takenSeatIds, true))
                                <label class="seat" title="Seat {{ $seat->seat_label }}{{ $taken ? ' (taken)' : '' }}">
                                    <input type="checkbox" name="seats[]" value="{{ $seat->seat_id }}" data-label="{{ $seat->seat_label }}" @disabled($taken)>
                                    <span>{{ preg_replace('/^[A-Za-z]+/', '', $seat->seat_label) }}<span class="sr-only"> seat {{ $seat->seat_label }}{{ $taken ? ', taken' : '' }}</span></span>
                                </label>
                            @endforeach
                            <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="seat-legend" aria-hidden="true">
                    <span><i></i>Available</span><span><i class="is-selected"></i>Selected</span><span><i class="is-taken"></i>Taken</span>
                </div>
            </section>
        </form>
    @else
        {{-- Step 2: "Who's coming?" — one card per seat; the first seat is the primary booker. --}}
        <form method="POST" action="{{ route('bookings.store', $screening) }}" class="booking">
            @csrf
            @foreach ($selected as $seat)
                <input type="hidden" name="seat_ids[]" value="{{ $seat->seat_id }}">
            @endforeach

            <div class="booking__side">
                @include('public.bookings._summary', ['screening' => $screening, 'seatLabels' => $selected->pluck('seat_label')])
                <button type="submit" class="btn btn--gold btn--block btn--lg">{{ $screening->isPaid() ? 'Continue to payment' : 'Submit reservation' }} <x-arrow class="arrow" /></button>
                <p class="muted small booking__hint">
                    {{ $screening->isPaid() ? 'Seats are held for '.\App\Models\Reservation::PAYMENT_WINDOW_MINUTES.' minutes while you pay.' : 'Staff approve free reservations; your e-ticket is then emailed.' }}
                </p>
            </div>

            <div class="booking__main">
                <h1 class="booking__title">Who's coming?</h1>
                <p class="muted small booking__lead">One person per seat. Seat {{ $selected->first()->seat_label }} is you, the primary booker: your booking reference and e-ticket go to your email.</p>
                @foreach ($selected as $seat)
                    @include('public.bookings._attendee-fields', ['seat' => $seat, 'primary' => $loop->first, 'booker' => $selected->first()])
                @endforeach
            </div>
        </form>
    @endif
@endsection
