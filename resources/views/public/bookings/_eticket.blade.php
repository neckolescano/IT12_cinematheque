{{-- The booking as the mobile app's vertical ticket: details, tear line, stub.
     Shared by the booking page (end of the booking flow) and the ticket page (Find my booking).
     Expects $reservation, $payment. Saved to "On this device" via data-save-booking. --}}
@php
    $screening = $reservation->screening;
    $cancelled = $reservation->status === 'cancelled';
    $approved = $reservation->status === 'confirmed';
    $seats = $reservation->reservationSeats;
@endphp
        <article @class(['eticket', 'eticket--void' => $cancelled]) aria-label="{{ $approved ? 'E-ticket' : 'Booking details' }}" data-save-booking
                 data-ref="{{ $reservation->booking_reference }}" data-title="{{ $screening->event_title }}"
                 data-date="{{ $screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}"
                 data-start="{{ $screening->event_date->toDateString() }}" data-url="{{ route('bookings.ticket', $reservation) }}">
            <div class="eticket__main">
                <span class="eyebrow">{{ $approved ? 'E-ticket · ' : '' }}Cinematheque Centre Davao</span>
                <h1 class="display eticket__title">{{ $screening->event_title }}</h1>
                <p class="eticket__when"><strong>{{ $screening->event_date->format('l, F j, Y') }}</strong><br>
                    <span class="muted">{{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($screening->end_time)->format('g:i A') }}</span></p>
                <span class="label-caps">Seats</span>
                <ul class="eticket__seats">
                    @foreach ($seats as $rs)
                        <li><span class="eticket__seat">{{ $rs->seat->seat_label }}</span>{{ $rs->attendee?->full_name }}</li>
                    @endforeach
                </ul>
                <p class="muted small" style="margin:0">
                    @if ($payment)
                        <span class="money">₱{{ number_format($payment->amount, 2) }}</span> · {{ $payment->isPaid() ? ($cancelled ? 'Refund due' : 'Paid') : 'Not paid' }}
                    @else
                        Free admission
                    @endif
                </p>
            </div>
            <div class="eticket__stub">
                <div class="eticket__admit">
                    <strong>{{ $approved ? 'ADMIT '.$seats->count() : ($cancelled ? 'CANCELLED' : 'AWAITING PAYMENT') }}</strong>
                    @unless ($approved)<span>{{ $cancelled ? 'Not valid for entry' : 'Not yet valid for entry' }}</span>@endunless
                </div>
                <div class="eticket__ref">
                    <span class="label-caps">Reference</span>
                    <strong class="ref">{{ $reservation->booking_reference }}</strong>
                </div>
                <button type="button" class="icon-btn no-print" data-copy="{{ $reservation->booking_reference }}" aria-label="Copy booking reference" title="Copy reference">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                </button>
            </div>
        </article>

