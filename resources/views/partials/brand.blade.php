{{-- Wordmark: a film frame holding a mountain-ridge line (Davao's skyline backdrop). --}}
<a class="brand" href="{{ $href ?? route('home') }}" aria-label="Cinematheque Centre Davao — home">
    <svg class="brand__mark" viewBox="0 0 40 40" aria-hidden="true">
        <rect x="2" y="4" width="36" height="32" rx="6" fill="none" stroke="#ebbc00" stroke-width="2.4"/>
        <g fill="#ebbc00">
            <rect x="6" y="8" width="3" height="3" rx="1"/><rect x="6" y="18.5" width="3" height="3" rx="1"/><rect x="6" y="29" width="3" height="3" rx="1"/>
            <rect x="31" y="8" width="3" height="3" rx="1"/><rect x="31" y="18.5" width="3" height="3" rx="1"/><rect x="31" y="29" width="3" height="3" rx="1"/>
        </g>
        <path d="M11 28 L16.5 19 L19.5 23 L23 15 L29 28 Z" fill="#fff"/>
        <path d="M11 28 L16.5 19 L19.5 23 L23 15 L29 28" fill="none" stroke="#ebbc00" stroke-width="1.2" stroke-linejoin="round"/>
    </svg>
    <span class="brand__text">
        <span class="brand__name">Cinematheque</span>
        <span class="brand__sub">{{ $sub ?? 'Centre Davao' }}</span>
    </span>
</a>
