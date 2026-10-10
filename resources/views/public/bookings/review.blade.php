@extends('layouts.app')

@section('title', 'Review booking — '.$screening->event_title)

{{-- Step 3, Review booking (compulsory): every detail read-only, one card per ticket with its price. The validated
     details ride along as hidden fields: "Edit details" sends them back to step 2, "Confirm" submits them (reviewed=1). --}}
@php
    $paid = $screening->isPaid();
    $discountLabels = ['pwd' => 'PWD', 'senior' => 'Senior citizen'];
    $fields = ['first_name', 'middle_name', 'last_name', 'age', 'sex', 'company_school', 'contact_no', 'email', 'senior_card_no', 'pwd_id_no'];
    $money = fn ($v) => '₱'.number_format($v, 2);
@endphp

@section('content')
    <x-crumbs :items="['Screenings' => route('home'), $screening->event_title => route('screenings.show', $screening), 'Seats' => route('bookings.create', $screening), 'Review' => null]" />
    <x-stepper :current="3" :paid="$paid" />

    <form method="POST" action="{{ route('bookings.store', $screening) }}" class="booking">
        @csrf
        <input type="hidden" name="reviewed" value="1">
        @foreach ($tickets as $ticket)
            <input type="hidden" name="seat_ids[]" value="{{ $ticket['seat']->seat_id }}">
            @foreach ($fields as $field)
                @if (filled($ticket['attendee'][$field] ?? null))
                    <input type="hidden" name="attendees[{{ $ticket['seat']->seat_id }}][{{ $field }}]" value="{{ $ticket['attendee'][$field] }}">
                @endif
            @endforeach
        @endforeach

        <div class="booking__main">
            <h1 class="booking__title">Review your booking</h1>
            <p class="muted small booking__lead">Names must match each guest's ID.</p>

            @foreach ($tickets as $ticket)
                @php($a = $ticket['attendee'])
                <section class="person review-card" @if ($loop->first) data-primary @endif aria-label="Seat {{ $ticket['seat']->seat_label }}">
                    <div class="person__head">
                        <span class="seat-chip">Seat {{ $ticket['seat']->seat_label }}</span>
                        @if ($loop->first)<span class="pill pill--ink">You · primary booker</span>@else<span class="muted small">Guest</span>@endif
                        @if ($ticket['discount_type'] !== 'none')<span class="pill pill--discount">{{ $discountLabels[$ticket['discount_type']] }}{{ $paid ? ' · 20% off' : '' }}</span>@endif
                        @if ($paid)<span class="review-card__price money">{{ $money($ticket['amount_due']) }}</span>@endif
                    </div>
                    <dl class="review-card__grid">
                        <div><dt>Name</dt><dd>{{ collect([$a['first_name'], $a['middle_name'] ?? null, $a['last_name']])->filter()->join(' ') }}</dd></div>
                        <div><dt>Age · Sex</dt><dd>{{ $a['age'] }} · {{ $a['sex'] === 'M' ? 'Male' : 'Female' }}</dd></div>
                        <div><dt>School or company</dt><dd>{{ $a['company_school'] }}</dd></div>
                        <div><dt>Mobile</dt><dd>{{ $a['contact_no'] }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $a['email'] }}</dd></div>
                        @if (filled($a['pwd_id_no'] ?? null))<div><dt>PWD ID no.</dt><dd>{{ $a['pwd_id_no'] }}</dd></div>@endif
                        @if (filled($a['senior_card_no'] ?? null))<div><dt>Senior citizen ID no.</dt><dd>{{ $a['senior_card_no'] }}</dd></div>@endif
                    </dl>
                </section>
            @endforeach
        </div>

        <aside class="booking__side summary" aria-labelledby="summary-title">
            @include('public.bookings._summary', ['screening' => $screening, 'seatLabels' => $tickets->pluck('seat.seat_label'), 'tickets' => $tickets])
            <div class="summary__actions">
                <button type="submit" class="btn btn--gold btn--block btn--lg">{{ $paid ? 'Confirm and pay' : 'Confirm reservation' }} <x-arrow class="arrow" /></button>
                <button type="submit" class="btn btn--secondary btn--block" formaction="{{ route('bookings.review', $screening) }}" name="edit" value="1" formnovalidate>Edit details</button>
                <p class="muted small booking__hint">
                    {{ $paid ? 'Seats are held for '.\App\Models\Reservation::PAYMENT_WINDOW_MINUTES.' minutes while you pay.' : 'Your reservation is confirmed and your e-ticket emailed right away.' }}
                </p>
            </div>
        </aside>
    </form>
@endsection
