@extends('layouts.app')

{{-- What "Find my booking" opens: the ticket only (the booking flow's page lives under Screenings). --}}
@section('title', 'Ticket '.$reservation->booking_reference)
@section('main_class', 'page page--narrow')

@section('content')
    <x-crumbs :items="['Find my booking' => route('bookings.lookup'), $reservation->booking_reference => null]" class="no-print" />

    @include('public.bookings._eticket')

    <div class="actions no-print">
        @if ($canPay)
            <a class="btn btn--gold" href="{{ route('bookings.show', $reservation) }}">Complete payment</a>
        @elseif ($reservation->status === 'confirmed')
            <button type="button" class="btn btn--gold" onclick="window.print()">Print e-ticket</button>
        @endif
        <a class="btn btn--secondary" href="{{ route('bookings.lookup') }}">Find another booking</a>
    </div>
@endsection
