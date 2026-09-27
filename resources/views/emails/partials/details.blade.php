{{-- Booking details shared by every reservation email. Only data the system already stores. --}}
@php
    $movieTitle = $screening->movie?->title;
    $seats = $reservation->reservationSeats->sortBy('seat.seat_label', SORT_NATURAL);
    $row = 'padding:6px 0;border-bottom:1px solid #f0eee9;vertical-align:top;';
    $label = $row.'color:#6b6674;width:40%;font-size:13px;';
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
    <tr><td style="{{ $label }}">Booking reference</td><td style="{{ $row }}font-weight:bold;font-size:17px;letter-spacing:1px;">{{ $reservation->booking_reference }}</td></tr>
    <tr><td style="{{ $label }}">Event</td><td style="{{ $row }}font-weight:bold;">{{ $screening->event_title }}</td></tr>
    @if ($movieTitle && $movieTitle !== $screening->event_title)
        <tr><td style="{{ $label }}">Film</td><td style="{{ $row }}">{{ $movieTitle }}</td></tr>
    @endif
    <tr><td style="{{ $label }}">Date</td><td style="{{ $row }}">{{ $screening->event_date->format('l, F j, Y') }}</td></tr>
    <tr><td style="{{ $label }}">Time</td><td style="{{ $row }}">{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</td></tr>
    <tr><td style="{{ $label }}">Booked by</td><td style="{{ $row }}">{{ $reservation->lead_full_name }}</td></tr>
    @if ($payment)
        <tr><td style="{{ $label }}">Amount</td><td style="{{ $row }}">₱{{ number_format($payment->amount, 2) }}</td></tr>
        <tr><td style="{{ $label }}">Payment</td><td style="{{ $row }}">
            @if ($payment->isPaid())
                Paid{{ $payment->payment_channel ? ' via '.strtoupper($payment->payment_channel) : '' }}{{ $payment->paid_at ? ' on '.$payment->paid_at->format('M j, Y g:i A') : '' }}
            @else
                Not yet paid
            @endif
        </td></tr>
    @else
        <tr><td style="{{ $label }}">Admission</td><td style="{{ $row }}">Free</td></tr>
    @endif
</table>

<div style="font-size:13px;color:#6b6674;font-weight:bold;letter-spacing:.5px;margin:0 0 6px;">SEATS ({{ $seats->count() }})</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border:1px solid #ece9e4;border-radius:8px;">
    @foreach ($seats as $rs)
        <tr>
            <td style="padding:8px 12px;border-bottom:1px solid #f0eee9;width:70px;"><span style="display:inline-block;background:#ebbc00;color:#141219;font-weight:bold;border-radius:12px;padding:2px 10px;font-size:13px;">{{ $rs->seat->seat_label }}</span></td>
            <td style="padding:8px 12px;border-bottom:1px solid #f0eee9;">{{ $rs->attendee?->full_name }}@if ($rs->attendee?->is_lead_reserver) <span style="color:#6b6674;font-size:12px;">(booker)</span>@endif</td>
        </tr>
    @endforeach
</table>
