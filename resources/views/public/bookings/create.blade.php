@extends('layouts.app')

@section('title', 'Reserve — '.$screening->event_title)
@section('main_class', 'section section--booking')

@php
    $max = \App\Http\Requests\StoreReservationRequest::MAX_SEATS_PER_RESERVATION;
    $step = $selected->isEmpty() ? 1 : 2;
    // Seat map: section → row letter → seats (labels like "A1", "J10").
    $sections = $seats->sortBy('seat_id')->groupBy('section')->map(
        fn ($group) => $group->groupBy(fn ($seat) => preg_match('/^([A-Za-z]+)/', $seat->seat_label, $m) ? strtoupper($m[1]) : '·')
    );
    $calIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 11V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/><path d="M4 11h16v5H4zM6 16v4M18 16v4"/></svg>';
    $userIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>';
@endphp

@section('content')
    <div class="cluster" style="justify-content:space-between;margin-bottom:var(--s-4)">
        <a class="crumb" style="margin:0" href="{{ $step === 1 ? route('screenings.show', $screening) : route('bookings.create', $screening) }}">&larr; {{ $step === 1 ? 'Back to screening' : 'Change seats' }}</a>
        <span class="small muted">{{ $available }} seats left</span>
    </div>
    <x-stepper :current="$step" :paid="$screening->isPaid()" />

    @if ($step === 1)
        {{-- Step 1: seat map + live summary --}}
        <form method="GET" action="{{ route('bookings.create', $screening) }}" class="booking-layout fade-swap"
              data-seat-picker data-max="{{ $max }}" data-price="{{ $screening->isPaid() ? $screening->price : 0 }}">
            <section class="card">
                <h1 class="card__title" style="font-size:var(--fs-xl)"><span class="card__icon">{!! $calIcon !!}</span>Select your seats</h1>
                <p class="small muted" style="margin-top:calc(-1 * var(--s-2))">{{ $screening->event_title }} · {{ $screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} · up to {{ $max }} seats</p>

                <div class="screen-bar" style="margin-top:var(--s-5)"><span>Screen</span></div>

                @foreach ($sections as $section => $rows)
                    <div class="seat-section">
                        @if ($sections->count() > 1)<h3>{{ $section ?: 'Seats' }}</h3>@endif
                        <div class="seat-rows">
                            @foreach ($rows as $row => $rowSeats)
                                <div class="seat-row" style="--n: {{ $rowSeats->count() }}">
                                    <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                                    @foreach ($rowSeats as $seat)
                                        <label class="seat" title="Seat {{ $seat->seat_label }}{{ in_array($seat->seat_id, $takenSeatIds, true) ? ' (taken)' : '' }}">
                                            <input type="checkbox" name="seats[]" value="{{ $seat->seat_id }}" data-label="{{ $seat->seat_label }}" @disabled(in_array($seat->seat_id, $takenSeatIds, true))>
                                            <span>{{ preg_replace('/^[A-Za-z]+/', '', $seat->seat_label) ?: $seat->seat_label }}<span class="sr-only"> seat {{ $seat->seat_label }}{{ in_array($seat->seat_id, $takenSeatIds, true) ? ', taken' : '' }}</span></span>
                                        </label>
                                    @endforeach
                                    <span class="seat-row__label" aria-hidden="true">{{ $row }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="seat-legend" aria-hidden="true">
                    <span><i></i>Available</span>
                    <span><i class="is-selected"></i>Selected</span>
                    <span><i class="is-taken"></i>Taken</span>
                </div>
            </section>

            <x-booking-summary :screening="$screening">
                <button type="submit" class="btn btn--primary btn--block btn--lg" data-seat-submit>Continue <span class="arrow" aria-hidden="true">&rarr;</span></button>
                <p class="summary__hint" data-seat-hint>Select at least one seat to continue.</p>
            </x-booking-summary>

            <div class="mobile-bar" aria-hidden="true">
                <span><strong data-seat-count>0 seats</strong></span>
                <button type="submit" class="btn btn--primary btn--sm" data-seat-submit tabindex="-1">Continue</button>
            </div>
        </form>
    @else
        {{-- Step 2: booker + one attendee per seat --}}
        <form method="POST" action="{{ route('bookings.store', $screening) }}" class="booking-layout fade-swap">
            @csrf
            @foreach ($selected as $seat)
                <input type="hidden" name="seat_ids[]" value="{{ $seat->seat_id }}">
            @endforeach

            <div class="stack">
                <fieldset class="card">
                    <legend><span class="card__icon">{!! $userIcon !!}</span>Your details</legend>
                    <div class="form-grid">
                        <div class="field @error('lead_first_name') has-error @enderror">
                            <label for="lead_first_name">First name <span class="req">*</span></label>
                            <input type="text" id="lead_first_name" name="lead_first_name" value="{{ old('lead_first_name') }}" maxlength="50" required autocomplete="given-name">
                            @error('lead_first_name') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="lead_middle_name">Middle name</label>
                            <input type="text" id="lead_middle_name" name="lead_middle_name" value="{{ old('lead_middle_name') }}" maxlength="50" autocomplete="additional-name">
                        </div>
                        <div class="field @error('lead_last_name') has-error @enderror">
                            <label for="lead_last_name">Last name <span class="req">*</span></label>
                            <input type="text" id="lead_last_name" name="lead_last_name" value="{{ old('lead_last_name') }}" maxlength="50" required autocomplete="family-name">
                            @error('lead_last_name') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="field @error('lead_email') has-error @enderror">
                            <label for="lead_email">Email <span class="req">*</span></label>
                            <input type="email" id="lead_email" name="lead_email" value="{{ old('lead_email') }}" maxlength="100" required autocomplete="email">
                            <span class="hint">Your confirmation and e-ticket are sent here.</span>
                            @error('lead_email') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field @error('lead_contact_no') has-error @enderror">
                            <label for="lead_contact_no">Contact no. <span class="req">*</span></label>
                            <input type="text" id="lead_contact_no" name="lead_contact_no" value="{{ old('lead_contact_no') }}" maxlength="20" required autocomplete="tel" inputmode="tel" placeholder="09XX XXX XXXX">
                            @error('lead_contact_no') <span class="field__error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="field" style="margin-bottom:0">
                        <label for="lead_seat_id">Are you one of the attendees?</label>
                        <select id="lead_seat_id" name="lead_seat_id">
                            <option value="">No, I'm booking for others</option>
                            @foreach ($selected as $seat)
                                <option value="{{ $seat->seat_id }}" @selected(old('lead_seat_id') == $seat->seat_id)>Yes — I'll sit in {{ $seat->seat_label }}</option>
                            @endforeach
                        </select>
                    </div>
                </fieldset>

                @foreach ($selected as $seat)
                    <fieldset class="card">
                        <legend><span class="badge badge--gold badge--plain">{{ $seat->seat_label }}</span>Attendee for seat {{ $seat->seat_label }}</legend>
                        @include('public.bookings._attendee-fields', ['seat' => $seat])
                    </fieldset>
                @endforeach
            </div>

            <x-booking-summary :screening="$screening" :seat-labels="$selected->pluck('seat_label')">
                <button type="submit" class="btn btn--primary btn--block btn--lg">{{ $screening->isPaid() ? 'Reserve & pay' : 'Submit reservation' }} <span class="arrow" aria-hidden="true">&rarr;</span></button>
                <p class="summary__hint">{{ $screening->isPaid() ? 'Next: secure payment through PayMongo. Your seats are held while you pay.' : 'Staff approve free reservations; your e-ticket is then emailed.' }}</p>
            </x-booking-summary>
        </form>
    @endif
@endsection
