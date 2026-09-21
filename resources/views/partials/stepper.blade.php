{{-- usage: @include('partials.stepper', ['step' => 3]) --}}
@php
    $steps = ['Movies', 'Date & Time', 'Seats', 'Details', 'Confirmation'];
@endphp
<div class="stepper">
    <ol aria-label="Booking progress">
        @foreach ($steps as $i => $label)
            @php
                $n = $i + 1;
                $state = $n < $step ? 'done' : ($n === $step ? 'current' : 'upcoming');
            @endphp
            <li class="{{ $state }}" @if($state === 'current') aria-current="step" @endif>
                <span class="dot">{{ $state === 'done' ? '✓' : $n }}</span>
                <span class="label">{{ $label }}</span>
            </li>
        @endforeach
    </ol>
</div>
