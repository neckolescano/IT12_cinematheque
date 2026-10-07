@props(['current', 'paid' => true])
{{-- Booking progress as in the mobile app: three gold bars — Seats, Details, Pay / Confirm. --}}
@php($steps = ['Seats', 'Details', $paid ? 'Pay' : 'Confirm'])
<ol {{ $attributes->merge(['class' => 'steps']) }} aria-label="Booking progress">
    @foreach ($steps as $i => $label)
        <li @class(['is-done' => $i + 1 <= $current]) @if ($i + 1 === $current) aria-current="step" @endif>
            <span>{{ $i + 1 }}. {{ $label }}</span>
        </li>
    @endforeach
</ol>
