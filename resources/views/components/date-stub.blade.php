@props(['date', 'size' => 'md'])
{{-- The cream ticket stub from the mobile app: month, day, weekday. --}}
<span {{ $attributes->merge(['class' => 'date-stub date-stub--'.$size]) }} aria-label="{{ $date->format('l, F j') }}">
    <span class="date-stub__m" aria-hidden="true">{{ strtoupper($date->format('M')) }}</span>
    <span class="date-stub__d" aria-hidden="true">{{ $date->format('j') }}</span>
    <span class="date-stub__w" aria-hidden="true">{{ strtoupper($date->format('D')) }}</span>
</span>
