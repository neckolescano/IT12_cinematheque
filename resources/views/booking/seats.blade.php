@extends('layouts.app')

@section('title', 'Select seats · ' . $showtime->movie->title)

@php
    $cinema = $showtime->cinema;
    $movie = $showtime->movie;
    $half = intdiv($cinema->seats_per_row, 2);
    $price = $cinema->ticket_price;
@endphp

@section('content')
    @include('partials.stepper', ['step' => 3])

    <div class="container">
        <div class="step-nav">
            <a href="{{ route('booking.showtimes', $movie) }}" class="link-btn">← Back</a>
            <a href="{{ route('movies.index') }}" class="link-btn">Cancel Booking ✕</a>
        </div>

        @include('partials.alerts')

        <form method="POST" action="{{ route('booking.hold', $showtime) }}" class="seat-layout" id="seat-form">
            @csrf

            <section class="card" id="seat-app"
                     data-price="{{ $price }}"
                     data-currency="{{ config('cinema.currency') }}"
                     data-max="{{ config('cinema.max_tickets') }}"
                     data-per-row="{{ $cinema->seats_per_row }}"
                     data-rows="{{ json_encode($cinema->rowLetters()) }}">
                <h1 class="card-title"><span class="icon-box">▦</span> Select Your Seats</h1>
                <p class="muted" style="margin-top:-8px">{{ $movie->title }} • {{ $showtime->starts_at->format('M j') }} • {{ $showtime->starts_at->format('g:i A') }} • {{ $cinema->name }}</p>

                <div class="qty-bar">
                    <label for="ticket-qty" class="muted">Number of Tickets:</label>
                    <input type="number" id="ticket-qty" min="1" max="{{ config('cinema.max_tickets') }}" value="1">
                    <button type="button" id="auto-select" class="btn btn-slate">Auto Select Seats</button>
                </div>
                <p class="seat-msg" id="seat-msg" role="status"></p>

                <div class="screen"></div>
                <div class="screen-label">SCREEN</div>

                <div class="seat-map">
                    @foreach ($cinema->rowLetters() as $row)
                        <div class="seat-row">
                            <span class="row-label">{{ $row }}</span>
                            @for ($n = 1; $n <= $cinema->seats_per_row; $n++)
                                @php
                                    $label = $row . $n;
                                    $state = in_array($label, $taken) ? 'taken' : (in_array($label, $locked) ? 'locked' : 'available');
                                @endphp
                                @if ($n === $half + 1)<span class="aisle"></span>@endif
                                <button type="button" class="seat" data-seat="{{ $label }}" data-state="{{ $state }}"
                                        aria-label="Seat {{ $label }} ({{ $state }})" aria-pressed="false"
                                        @disabled($state !== 'available')></button>
                            @endfor
                            <span class="row-label">{{ $row }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="legend">
                    <span><i class="seat"></i> Available</span>
                    <span><i class="seat selected"></i> Selected</span>
                    <span><i class="seat" data-state="locked"></i> Locked</span>
                    <span><i class="seat" data-state="taken"></i> Taken</span>
                </div>
            </section>

            <aside class="card summary">
                <h2 style="margin:0 0 22px;font-size:1.35rem">Booking Summary</h2>
                <dl>
                    <dt>Movie</dt><dd>{{ $movie->title }}</dd>
                    <dt>Date &amp; Time</dt><dd>{{ $showtime->starts_at->format('M j, Y') }}<br>{{ $showtime->starts_at->format('g:i A') }}</dd>
                    <dt>Cinema</dt><dd>{{ $cinema->name }}</dd>
                    <dt>Selected Seats</dt><dd id="selected-seats" class="empty-seats">No seats selected</dd>
                </dl>
                <div class="totals">
                    <div class="line"><span>Tickets (<span id="ticket-count">0</span>)</span><span>{{ $price > 0 ? config('cinema.currency') . number_format($price, 2) . ' each' : 'Free' }}</span></div>
                    <div class="line total"><b>Total</b><b class="gold" id="total">{{ $price > 0 ? config('cinema.currency') . '0.00' : 'FREE' }}</b></div>
                </div>
                <button type="submit" id="continue-btn" class="btn btn-gold btn-block" style="margin-top:20px" disabled>Continue</button>
                <p class="hint" id="continue-hint">ⓘ Please select at least one seat to continue</p>
                <div id="seat-inputs"></div>
            </aside>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const app = document.getElementById('seat-app');
    const price = parseFloat(app.dataset.price);
    const currency = app.dataset.currency;
    const max = parseInt(app.dataset.max, 10);
    const perRow = parseInt(app.dataset.perRow, 10);
    const rows = JSON.parse(app.dataset.rows);

    const qty = document.getElementById('ticket-qty');
    const inputs = document.getElementById('seat-inputs');
    const selectedEl = document.getElementById('selected-seats');
    const countEl = document.getElementById('ticket-count');
    const totalEl = document.getElementById('total');
    const btn = document.getElementById('continue-btn');
    const hint = document.getElementById('continue-hint');
    const msg = document.getElementById('seat-msg');
    const seatEls = [...document.querySelectorAll('.seat[data-seat]')];
    const selected = new Set();

    const seatEl = label => document.querySelector(`.seat[data-seat="${label}"]`);
    const isFree = el => el && el.dataset.state === 'available';
    const sortSeats = list => list.sort((a, b) => a[0].localeCompare(b[0]) || parseInt(a.slice(1)) - parseInt(b.slice(1)));

    function render() {
        seatEls.forEach(el => {
            const on = selected.has(el.dataset.seat);
            el.classList.toggle('selected', on);
            el.setAttribute('aria-pressed', on);
        });
        const list = sortSeats([...selected]);
        inputs.innerHTML = list.map(s => `<input type="hidden" name="seats[]" value="${s}">`).join('');
        selectedEl.textContent = list.length ? list.join(', ') : 'No seats selected';
        selectedEl.classList.toggle('empty-seats', !list.length);
        countEl.textContent = list.length;
        totalEl.textContent = price === 0 ? 'FREE' : currency + (list.length * price).toFixed(2);
        btn.disabled = list.length === 0;
        hint.hidden = list.length > 0;
        if (list.length) qty.value = list.length;
    }

    seatEls.forEach(el => el.addEventListener('click', () => {
        if (!isFree(el)) return;
        const s = el.dataset.seat;
        msg.textContent = '';
        if (selected.has(s)) {
            selected.delete(s);
        } else if (selected.size >= max) {
            msg.textContent = `You can book up to ${max} seats at a time.`;
            return;
        } else {
            selected.add(s);
        }
        render();
    }));

    // Best seats = a bit past the middle row, centred, together.
    function findBest(n) {
        const targetRow = (rows.length - 1) * 0.6;
        const center = (perRow + 1) / 2;
        const order = rows.map((_, i) => i).sort((a, b) => Math.abs(a - targetRow) - Math.abs(b - targetRow));

        for (const i of order) {
            let best = null, bestDist = Infinity;
            for (let start = 1; start + n - 1 <= perRow; start++) {
                const run = Array.from({ length: n }, (_, k) => rows[i] + (start + k));
                if (run.every(s => isFree(seatEl(s)))) {
                    const dist = Math.abs(start + (n - 1) / 2 - center);
                    if (dist < bestDist) { bestDist = dist; best = run; }
                }
            }
            if (best) return best;
        }

        // No row has n seats together: take the free seats closest to the middle.
        const free = seatEls.filter(isFree).map(el => ({
            s: el.dataset.seat,
            d: Math.abs(rows.indexOf(el.dataset.seat[0]) - targetRow) * 2 + Math.abs(parseInt(el.dataset.seat.slice(1)) - center),
        })).sort((a, b) => a.d - b.d);
        return free.length >= n ? free.slice(0, n).map(x => x.s) : null;
    }

    document.getElementById('auto-select').addEventListener('click', () => {
        const n = Math.min(Math.max(parseInt(qty.value, 10) || 1, 1), max);
        qty.value = n;
        selected.clear();
        msg.textContent = '';
        const pick = findBest(n);
        if (pick) {
            pick.forEach(s => selected.add(s));
        } else {
            msg.textContent = `Only fewer than ${n} seats are left for this showtime.`;
        }
        render();
    });

    render();
})();
</script>
@endpush
