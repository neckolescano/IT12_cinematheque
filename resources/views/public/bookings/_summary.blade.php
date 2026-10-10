{{-- Booking summary (right column on the seat, details and review steps): a black banner with the poster large on a
     yellow offset block, then seats, price and total. On the seat step JS fills the seat list, count and total as seats
     are picked; on the details step JS splits regular and PWD / Senior (20% off) tickets as ID numbers are typed. The
     step's button and hint go in the slot under it (see create.blade.php). Expects $screening, optional $seatLabels and
     $tickets (the review step's priced tickets, see ReservationSeat::priceFor). --}}
@php
    $seatLabels = $seatLabels ?? collect();
    $count = $seatLabels->count();
    $paid = $screening->isPaid();
    $price = (float) $screening->price;
    $discountPrice = round($price * (1 - \App\Models\ReservationSeat::DISCOUNT_RATE), 2);
    $tickets = $tickets ?? null;
    $discountedCount = $tickets ? $tickets->where('discount_type', '!=', 'none')->count() : 0;
    $regularCount = $count - $discountedCount;
    $total = $tickets ? $tickets->sum('amount_due') : $price * $count;
@endphp
<div class="summary__banner">
    <div class="summary__poster"><x-poster :screening="$screening" /></div>
    <div class="summary__film">
        <span class="summary__kicker">Your screening</span>
        <h2 id="summary-title" class="summary__title">{{ $screening->event_title }}</h2>
        <span class="summary__when">{{ $screening->event_date->format('D, M j') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</span>
        <span class="summary__venue">Cinematheque Centre Davao</span>
    </div>
</div>
<div class="summary__body" @if ($paid) data-price-summary data-price="{{ $price }}" data-discount-price="{{ $discountPrice }}" @endif>
    <dl class="summary__rows">
        <div><dt>Seats</dt><dd data-seat-list>{{ $count ? $seatLabels->join(', ') : '—' }}</dd></div>
        <div><dt>Price</dt><dd>{{ $paid ? '₱'.number_format($price, 0).' per seat' : 'Free' }}</dd></div>
        @if ($paid && $count)
            <div><dt>Regular</dt><dd><span data-regular-count>{{ $regularCount }}</span> × ₱{{ number_format($price, 2) }}</dd></div>
            <div data-discount-row @if (! $discountedCount) hidden @endif>
                <dt>PWD / Senior <span class="summary__off">20% off</span></dt>
                <dd><span data-discount-count>{{ $discountedCount }}</span> × ₱{{ number_format($discountPrice, 2) }}</dd>
            </div>
        @endif
    </dl>
    <div class="summary__total">
        <span data-seat-count>{{ $count }} {{ Str::plural('seat', $count) }}</span>
        <strong class="money" data-seat-total>{{ $paid ? '₱'.number_format($total, 2) : 'Free' }}</strong>
    </div>
</div>
