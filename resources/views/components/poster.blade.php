@props(['screening' => null, 'movie' => null])
{{-- Poster tile (2:3). A real poster is shown whole (contain) over a blurred copy of itself,
     so posters of any proportion fit the frame without cropping. Without one: a generated tile. --}}
@php
    $movie = $movie ?? $screening?->movie;
    $title = $movie?->title ?? $screening?->event_title ?? '';
    $image = $movie?->posterUrl();
    $seed = $screening?->screening_id ?? $movie?->movie_id ?? 0;
@endphp
@if ($image)
<div {{ $attributes->merge(['class' => 'poster poster--image']) }} role="img" aria-label="Poster for {{ $title }}">
    <img class="poster__bg" src="{{ $image }}" alt="" aria-hidden="true" loading="lazy">
    <img class="poster__img" src="{{ $image }}" alt="" loading="lazy">
</div>
@else
<div {{ $attributes->merge(['class' => 'poster poster--v'.(($seed % 4) + 1)]) }} role="img" aria-label="{{ $title }}">
    <svg class="poster__art" viewBox="0 0 200 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
        <circle cx="160" cy="60" r="70" fill="none" stroke="#ebbc00" stroke-opacity=".3"/>
        <circle cx="160" cy="60" r="44" fill="none" stroke="#ebbc00" stroke-opacity=".22"/>
        <path d="M0 300 V262 H22 V248 H40 V270 H62 V236 H80 V272 H104 V254 H128 V276 H150 V244 H166 V228 H178 V262 H200 V300 Z" fill="#000" fill-opacity=".35"/>
    </svg>
    <span class="poster__title">{{ $title }}</span>
</div>
@endif
