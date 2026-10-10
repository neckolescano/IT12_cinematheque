@extends('layouts.app')

@section('title', ($selected->isEmpty() ? 'Choose seats' : "Who's coming?").' — '.$screening->event_title)

@php
    $max = \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION;
    $step = $selected->isEmpty() ? 1 : 2;
    // All 120 seats as rows A–J of 12, in seat order.
    // Sorted by label (row letter, then number), not by id: seats 11–12 of each row were added later (once rows K–L).
    $rows = $seats->sortBy(fn ($seat) => preg_match('/^([A-Za-z]+)(\d+)/', $seat->seat_label, $m) ? sprintf('%s%04d', strtoupper($m[1]), $m[2]) : $seat->seat_label)
        ->groupBy(fn ($seat) => preg_match('/^([A-Za-z]+)/', $seat->seat_label, $m) ? strtoupper($m[1]) : '·');
    $crumbs = ['Screenings' => route('home'), $screening->event_title => route('screenings.show', $screening)];
    $crumbs += $step === 1 ? ['Seats' => null] : ['Seats' => route('bookings.create', $screening), 'Details' => null];
@endphp

@section('content')
    <x-crumbs :items="$crumbs" />
    <x-stepper :current="$step" :paid="$screening->isPaid()" />

    {{-- Both steps: the action on the left (seat map / "Who's coming?"), the summary card with the step's button on the right. --}}
    @if ($step === 1)
        <form method="GET" action="{{ route('bookings.create', $screening) }}" class="booking" data-seat-picker data-max="{{ $max }}" data-price="{{ $screening->isPaid() ? $screening->price : 0 }}" data-no-loading>
            <section class="booking__main seat-map" aria-labelledby="seats-title">
                <div class="seat-map__head">
                    <h1 id="seats-title" class="booking__title">Select your seats</h1>
                    <span class="muted small">{{ $available }} of {{ $screening->total_seats }} left</span>
                </div>
                {{-- The hall as the venue's floor plan, markers only (no walls): the entrance at the top (the back of the
                     hall, where you walk left or right to the aisles), rows J down to A, and the screen at the bottom.
                     An exit on each side in line with row B, the second row from the screen. 10 × 12 = 120 seats.
                     Everything but the seats is for orientation only (aria-hidden). --}}
                <div class="hall">
                    <div class="hall__entrance" aria-hidden="true">
                        <x-arrow dir="left" />
                        <span class="entrance-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21h16M6 21V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v17"/><circle cx="14.5" cy="12" r="1" fill="currentColor"/></svg>Entrance</span>
                        <x-arrow />
                    </div>
                    <div class="seat-grid">
                        @foreach ($rows->reverse() as $row => $rowSeats)
                            <div class="seat-row">
                                <span class="seat-row__side" aria-hidden="true">@if ($row === 'B')<span class="exit-sign">Exit</span>@endif</span>
                                <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                                @foreach ($rowSeats as $seat)
                                    @php($taken = in_array($seat->seat_id, $takenSeatIds, true))
                                    <label class="seat" title="Seat {{ $seat->seat_label }}{{ $taken ? ' (taken)' : '' }}">
                                        <input type="checkbox" name="seats[]" value="{{ $seat->seat_id }}" data-label="{{ $seat->seat_label }}" @disabled($taken)>
                                        <span>{{ preg_replace('/^[A-Za-z]+/', '', $seat->seat_label) }}<span class="sr-only"> seat {{ $seat->seat_label }}{{ $taken ? ', taken' : '' }}</span></span>
                                    </label>
                                @endforeach
                                <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                                <span class="seat-row__side" aria-hidden="true">@if ($row === 'B')<span class="exit-sign">Exit</span>@endif</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="screen-bar screen-bar--bottom" aria-hidden="true">Screen</div>
                </div>
                <div class="seat-legend" aria-hidden="true">
                    <span><i></i>Available</span>
                    <span><i class="is-selected"></i>Selected</span>
                    <span><i class="is-taken"></i>Taken</span>
                </div>
            </section>

            <aside class="booking__side summary" aria-labelledby="summary-title">
                @include('public.bookings._summary', ['screening' => $screening])
                <div class="summary__actions">
                    <button type="submit" class="btn btn--gold btn--block btn--lg" data-seat-submit>Continue <x-arrow class="arrow" /></button>
                    <p class="muted small booking__hint">Up to {{ $max }} seats. The first seat you pick is yours.</p>
                </div>
            </aside>
        </form>
    @else
        {{-- Step 2: "Who's coming?" — one card per seat; the first seat is the primary booker. --}}
        <form method="POST" action="{{ route('bookings.review', $screening) }}" class="booking">
            @csrf
            @foreach ($selected as $seat)
                <input type="hidden" name="seat_ids[]" value="{{ $seat->seat_id }}">
            @endforeach

            <div class="booking__main">
                <h1 class="booking__title">Who's coming?</h1>
                <p class="muted small booking__lead">One person per seat. Your e-ticket goes to seat {{ $selected->first()->seat_label }}'s email.</p>
                @foreach ($selected as $seat)
                    @include('public.bookings._attendee-fields', ['seat' => $seat, 'primary' => $loop->first, 'booker' => $selected->first()])
                @endforeach
            </div>

            <aside class="booking__side summary" aria-labelledby="summary-title">
                @include('public.bookings._summary', ['screening' => $screening, 'seatLabels' => $selected->pluck('seat_label')])
                <div class="summary__actions">
                    <button type="submit" class="btn btn--gold btn--block btn--lg">Review booking <x-arrow class="arrow" /></button>
                    <p class="muted small booking__hint"><span class="req" aria-hidden="true">*</span> Required</p>
                </div>
            </aside>
        </form>
    @endif
@endsection
