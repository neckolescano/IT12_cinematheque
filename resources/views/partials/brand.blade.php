{{-- Wordmark: the Cinematheque Davao serpent (from its logo) beside the name. The serpent is a transparent mask
     (images/brand/cinematheque-serpent-mask.png) painted with --brand-mark, so it takes the theme's gold. --}}
<a class="brand" href="{{ $href ?? route('home') }}" aria-label="Cinematheque Centre Davao — home">
    <span class="brand__logo" aria-hidden="true" style="--brand-mask: url('{{ asset('images/brand/cinematheque-serpent-mask.png') }}')"></span>
    <span class="brand__text">
        <span class="brand__name">Cinematheque</span>
        <span class="brand__sub">{{ $sub ?? 'Centre Davao' }}</span>
    </span>
</a>
