@props(['current', 'paid' => true])
{{-- Booking progress: four gold bars — Seats, Details, Review, Pay / Confirmed. --}}
@php($steps = ['Seats', 'Details', 'Review', $paid ? 'Pay' : 'Confirmed'])
<ol {{ $attributes->merge(['class' => 'steps']) }} aria-label="Booking progress">
    @foreach ($steps as $i => $label)
        <li @class(['is-done' => $i + 1 <= $current]) @if ($i + 1 === $current) aria-current="step" @endif>
            <span>{{ $i + 1 }}. {{ $label }}</span>
        </li>
    @endforeach
</ol>
