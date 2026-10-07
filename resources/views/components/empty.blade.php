@props(['title', 'icon' => 'screen'])
{{-- Empty state with a small line illustration (cinema screen + seats, or a document). --}}
<div {{ $attributes->merge(['class' => 'empty']) }}>
    @if ($icon === 'screen')
        <svg viewBox="0 0 120 70" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M14 14 Q60 2 106 14" stroke="#ebbc00" stroke-width="3"/>
            <rect x="22" y="36" width="14" height="12" rx="3"/><rect x="42" y="36" width="14" height="12" rx="3"/>
            <rect x="64" y="36" width="14" height="12" rx="3"/><rect x="84" y="36" width="14" height="12" rx="3"/>
            <rect x="32" y="54" width="14" height="12" rx="3" opacity=".5"/><rect x="52" y="54" width="14" height="12" rx="3" opacity=".5"/><rect x="74" y="54" width="14" height="12" rx="3" opacity=".5"/>
        </svg>
    @else
        <svg viewBox="0 0 120 70" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <rect x="38" y="6" width="44" height="58" rx="5"/><path d="M48 22h24M48 32h24M48 42h14"/>
            <circle cx="84" cy="54" r="10" stroke="#ebbc00" stroke-width="3"/><path d="m91 61 7 7" stroke="#ebbc00" stroke-width="3"/>
        </svg>
    @endif
    <h3>{{ $title }}</h3>
    @if (trim($slot) !== '')
        <p>{{ $slot }}</p>
    @endif
</div>
