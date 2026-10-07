@props(['dir' => 'right'])
{{-- Full-size arrow icon (replaces the old "←"/"→" text arrows). Sized by .icon-arrow in each stylesheet. --}}
<svg {{ $attributes->merge(['class' => 'icon-arrow']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@if ($dir === 'left')<path d="M19 12H5M11 18l-6-6 6-6"/>@else<path d="M5 12h14M13 6l6 6-6 6"/>@endif</svg>
