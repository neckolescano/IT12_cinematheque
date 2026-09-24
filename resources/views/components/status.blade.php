@props(['value', 'label' => null])
{{-- One status vocabulary for reservations, payments, proofs and attendance. --}}
@php
    $tone = match ($value) {
        'confirmed', 'verified', 'accepted', 'checked in', 'active' => 'success',
        'pending', 'awaiting review' => 'warning',
        'cancelled', 'rejected', 'inactive' => 'error',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge badge--'.$tone]) }}>@if ($label){{ $label }}: @endif{{ ucfirst($value) }}</span>
