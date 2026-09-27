@props(['value', 'label' => null])
{{-- One status vocabulary for reservations, payments, proofs and attendance. --}}
@php
    $tone = match ($value) {
        'confirmed', 'approved', 'verified', 'paid', 'accepted', 'admitted', 'checked in', 'active' => 'success',
        'pending', 'unpaid', 'awaiting payment', 'awaiting approval', 'awaiting review' => 'warning',
        'cancelled', 'rejected', 'no-show', 'inactive' => 'error',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge badge--'.$tone]) }}>@if ($label){{ $label }}: @endif{{ ucfirst($value) }}</span>
