@extends('emails.layout')

@section('title', 'Your e-ticket')
@section('preheader', 'Your reservation is confirmed. Show this e-ticket at the entrance.')
@section('status_bg', '#e6f2ec')
@section('status_fg', '#154c37')
@section('status', 'APPROVED · E-TICKET')

@section('content')
    <p style="margin:0 0 16px;">Hi {{ $reservation->lead_first_name }},</p>
    <p style="margin:0 0 20px;">Your reservation is <strong>confirmed</strong>. This email is your <strong>e-ticket</strong>: show it, or just the booking reference, at the entrance.</p>

    {{-- Ticket stub --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;border:2px dashed #ebbc00;border-radius:12px;">
        <tr>
            <td align="center" style="padding:18px 12px;">
                <div style="font-size:11px;letter-spacing:3px;color:#6b6674;font-weight:bold;">E-TICKET · ADMIT {{ $reservation->reservationSeats->count() }}</div>
                <div style="font-size:30px;font-weight:bold;letter-spacing:3px;margin:6px 0;color:#141219;">{{ $reservation->booking_reference }}</div>
                <div style="font-size:14px;font-weight:bold;">{{ $screening->event_title }}</div>
                <div style="font-size:13px;color:#6b6674;">{{ $screening->event_date->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</div>
            </td>
        </tr>
    </table>

    @include('emails.partials.details')

    <div style="background:#faf9f7;border-radius:8px;padding:14px 16px;font-size:13px;line-height:1.55;margin:0 0 16px;">
        <strong>At the venue</strong><br>
        Each attendee listed above is admitted individually by Cinematheque staff, who check names against this booking. Please arrive before the start time.
    </div>

    <p style="margin:0;font-size:13px;color:#6b6674;">View your booking online: <a href="{{ $bookingUrl }}" style="color:#580076;">{{ $bookingUrl }}</a></p>
@endsection
