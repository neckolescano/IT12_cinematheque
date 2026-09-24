@props(['date'])
{{-- FDCP-style calendar tile: purple month pill, gradient day, year. --}}
<span {{ $attributes->merge(['class' => 'date-badge']) }} aria-label="{{ $date->format('F j, Y') }}">
    <span class="date-badge__m" aria-hidden="true">{{ strtoupper($date->format('M')) }}</span>
    <span class="date-badge__d" aria-hidden="true">{{ $date->format('j') }}</span>
    <span class="date-badge__y" aria-hidden="true">{{ $date->format('D') }}</span>
</span>
