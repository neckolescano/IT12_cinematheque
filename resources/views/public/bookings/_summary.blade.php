{{-- Booking summary (left column on the seat and details steps). On the seat step JS fills the
     seat list, count and total as seats are picked. Expects $screening, optional $seatLabels. --}}
@php
    $seatLabels = $seatLabels ?? collect();
    $count = $seatLabels->count();
@endphp
<aside class="summary" aria-labelledby="summary-title">
    <div class="summary__film">
        <x-poster :screening="$screening" class="poster--thumb" />
        <div>
            <h2 id="summary-title" class="summary__title">{{ $screening->event_title }}</h2>
            <span class="muted small">{{ $screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</span>
        </div>
    </div>
    <dl class="summary__rows">
        <div><dt>Seats</dt><dd data-seat-list>{{ $count ? $seatLabels->join(', ') : '—' }}</dd></div>
        <div><dt>Price</dt><dd>{{ $screening->isPaid() ? '₱'.number_format($screening->price, 0).' per seat' : 'Free' }}</dd></div>
    </dl>
    <div class="summary__total">
        <span data-seat-count>{{ $count }} {{ Str::plural('seat', $count) }}</span>
        <strong class="money" data-seat-total>{{ $screening->isPaid() ? '₱'.number_format($screening->price * $count, 2) : 'Free' }}</strong>
    </div>
</aside>
