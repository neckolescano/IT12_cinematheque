@props(['screening', 'tall' => false])
{{-- Generated poster tile. The schema has no poster image column, so each
     screening gets a consistent cinematic gradient + its title instead. --}}
@php
    $variant = 'poster--v'.(($screening->screening_id % 4) + 1);
    $title = $screening->movie?->title ?? $screening->event_title;
@endphp
<div {{ $attributes->merge(['class' => 'poster '.$variant.($tall ? ' poster--tall' : '')]) }} role="img" aria-label="Poster for {{ $title }}">
    <svg class="poster__art" viewBox="0 0 300 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <defs>
            <linearGradient id="beam-{{ $screening->screening_id }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#fff" stop-opacity=".22"/>
                <stop offset="1" stop-color="#fff" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <polygon points="300,0 300,26 40,200 0,200 0,170" fill="url(#beam-{{ $screening->screening_id }})"/>
        <circle cx="262" cy="36" r="46" fill="none" stroke="#ebbc00" stroke-opacity=".35" stroke-width="1"/>
        <circle cx="262" cy="36" r="30" fill="none" stroke="#ebbc00" stroke-opacity=".25" stroke-width="1"/>
        <path d="M0 200 V176 H24 V164 H40 V180 H62 V150 H78 V182 H104 V168 H128 V186 H150 V160 H164 V146 H176 V172 H204 V184 H232 V166 H256 V178 H300 V200 Z" fill="#000" fill-opacity=".35"/>
    </svg>
    <div class="poster__inner">
        <span class="poster__tag">{{ $slot }}</span>
        <span class="poster__title">{{ $title }}</span>
    </div>
</div>
