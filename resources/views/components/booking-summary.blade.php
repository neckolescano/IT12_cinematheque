@props(['screening', 'seatLabels' => collect(), 'bookedBy' => null])
@php($count = $seatLabels->count())
<aside {{ $attributes->merge(['class' => 'card summary']) }} aria-labelledby="summary-title">
    <h2 class="card__title" id="summary-title" style="font-size:var(--fs-md)">Booking summary</h2>
    <div class="summary__film">
        <x-poster :screening="$screening" class="poster--thumb" />
        <div>
            <strong>{{ $screening->event_title }}</strong>
            <span class="small muted">{{ $screening->isPaid() ? '₱'.number_format($screening->price, 2).' per seat' : 'Free admission' }}</span>
        </div>
    </div>
    <dl>
        <div><dt>Date &amp; time</dt><dd>{{ $screening->event_date->format('D, M j, Y') }} · {{ \Carbon\Carbon::parse($screening->start_time)->format('g:i A') }}</dd></div>
        <div><dt>Seats</dt><dd data-seat-list>{{ $count ? $seatLabels->join(', ') : 'None selected yet' }}</dd></div>
        @if ($bookedBy)<div><dt>Booked by</dt><dd>{{ $bookedBy }}</dd></div>@endif
    </dl>
    <div class="summary__total">
        <span class="muted small" data-seat-count>{{ $count }} {{ Str::plural('seat', $count) }}</span>
        <strong data-seat-total>{{ $screening->isPaid() ? '₱'.number_format($screening->price * $count, 2) : 'Free' }}</strong>
    </div>
    {{ $slot }}
</aside>
