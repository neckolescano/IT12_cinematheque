@props(['current', 'paid' => true])
{{-- Reservation progress: Seats → Details → Payment (paid only) → Confirmed. --}}
@php
    $steps = $paid ? ['Seats', 'Details', 'Payment', 'Confirmed'] : ['Seats', 'Details', 'Confirmed'];
@endphp
<ol {{ $attributes->merge(['class' => 'stepper']) }} aria-label="Reservation progress">
    @foreach ($steps as $i => $label)
        @php($n = $i + 1)
        <li class="{{ $n < $current ? 'is-done' : ($n === $current ? 'is-current' : '') }}" @if ($n === $current) aria-current="step" @endif>
            <span>{{ $label }}</span>
        </li>
    @endforeach
</ol>
