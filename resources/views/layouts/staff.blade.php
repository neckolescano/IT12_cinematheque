<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    @include('partials.head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

@php
    $pendingProofs = \App\Models\PaymentProof::where('status', 'pending')->count();
    $icons = [
        'dash' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'film' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M13 4v14" stroke-dasharray="2 2"/>',
        'cash' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/>',
        'movie' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="7" r="1.6"/><circle cx="12" cy="17" r="1.6"/><circle cx="7" cy="12" r="1.6"/><circle cx="17" cy="12" r="1.6"/>',
        'people' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><path d="M16 4a3.5 3.5 0 0 1 0 7M22 20c0-3-2-5-5-5.7"/>',
        'tag' => '<path d="M20 12 12 20 3 11V3h8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'seat' => '<path d="M6 11V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/><path d="M4 11h16v5H4zM6 16v4M18 16v4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
    ];
    $groups = [
        'Operations' => [
            ['staff.dashboard', 'staff.dashboard', 'Dashboard', 'dash'],
            ['staff.screenings.index', 'staff.screenings.*', 'Screenings', 'film'],
            ['staff.reservations.index', 'staff.reservations.*', 'Reservations & check-in', 'ticket'],
            ['staff.payment-proofs.index', 'staff.payment-proofs.*', 'Payment proofs', 'cash', $pendingProofs],
            ['staff.qr-codes.index', 'staff.qr-codes.*', 'Payment QR code', 'qr'],
            ['staff.reports.index', 'staff.reports.*', 'Reports', 'chart'],
        ],
        'Catalog' => [
            ['staff.movies.index', 'staff.movies.*', 'Movies', 'movie'],
            ['staff.actors.index', 'staff.actors.*', 'Actors', 'people'],
            ['staff.directors.index', 'staff.directors.*', 'Directors', 'people'],
            ['staff.genres.index', 'staff.genres.*', 'Genres', 'tag'],
        ],
        'Venue' => [
            ['staff.seats.index', 'staff.seats.*', 'Seats', 'seat'],
            ['staff.users.index', 'staff.users.*', 'Staff accounts', 'user'],
        ],
    ];
@endphp

<div class="staff-shell">
    <aside class="sidebar tex-grid" aria-label="Staff navigation">
        @include('partials.brand', ['href' => route('staff.dashboard'), 'sub' => 'Staff · Davao'])

        @foreach ($groups as $label => $links)
            <div class="sidebar__label">{{ $label }}</div>
            @foreach ($links as $link)
                <a class="side-link" href="{{ route($link[0]) }}" @if (request()->routeIs($link[1])) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$link[3]] !!}</svg>
                    {{ $link[2] }}
                    @if (! empty($link[4]))
                        <span class="side-count" title="Awaiting review">{{ $link[4] }}</span>
                    @endif
                </a>
            @endforeach
        @endforeach

        <div class="sidebar__foot">
            <div style="color:#fff;font-weight:600">{{ auth()->user()->full_name }}</div>
            <div>{{ auth()->user()->position ?? 'Staff' }} · {{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" data-no-loading>
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm">Log out</button>
            </form>
        </div>
    </aside>
    <div class="scrim" aria-hidden="true"></div>

    <div class="staff-main">
        <div class="staff-topbar">
            <button type="button" class="staff-toggle" data-toggle-class="sidebar-open" aria-expanded="false" aria-label="Open navigation">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            <span class="muted small">Cinematheque Centre Davao · Staff</span>
            <a class="btn btn--ghost btn--sm" style="margin-left:auto" href="{{ route('home') }}">View public site <span class="arrow">&rarr;</span></a>
        </div>

        <main id="main" class="staff-content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

@include('partials.confirm-modal')
</body>
</html>
