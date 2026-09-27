@extends('emails.layout')

@section('title', 'Reservation cancelled')
@section('preheader', 'Your reservation '.$reservation->booking_reference.' has been cancelled.')
@section('status_bg', '#fdecea')
@section('status_fg', '#7a1a12')
@section('status', 'CANCELLED')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $reservation->lead_first_name }},</p>
    <p style="margin:0 0 20px;">Your reservation below has been <strong>cancelled</strong> and is no longer valid for admission.</p>

    @include('emails.partials.details')

    @if ($payment?->isPaid())
        <p style="margin:0 0 16px;">A payment was recorded for this booking. Please contact Cinematheque Centre Davao about your refund, quoting your booking reference.</p>
    @endif

    <p style="margin:0;font-size:13px;color:#6b6674;">If you think this is a mistake, please contact Cinematheque Centre Davao and quote your booking reference.</p>
@endsection
