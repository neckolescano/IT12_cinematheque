<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Dashboard') · CCD Admin</title>
    @include('partials.theme')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

@php
    // Sidebar is organised by staff task, not by database table.
    $awaitingApproval = \App\Models\Reservation::where('status', 'pending')->doesntHave('payment')
        ->whereHas('screening', fn ($q) => $q->whereDate('event_date', '>=', today()))->count();
    $icons = [
        'dash' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'film' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M13 4v14" stroke-dasharray="2 2"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
        'book' => '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v16H6.5A2.5 2.5 0 0 0 4 21.5v-2z"/><path d="M8 7h8M8 11h6"/>',
        'seat' => '<path d="M6 11V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/><path d="M4 11h16v5H4zM6 16v4M18 16v4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
    ];
    $nav = [
        null => [
            ['staff.dashboard', ['staff.dashboard'], 'Dashboard', 'dash'],
            ['staff.screenings.index', ['staff.screenings.*'], 'Screenings', 'film'],
            ['staff.reservations.index', ['staff.reservations.*'], 'Reservations', 'ticket', $awaitingApproval],
            ['staff.reports.index', ['staff.reports.*'], 'Reports', 'chart'],
        ],
        'Settings' => [
            ['staff.movies.index', ['staff.movies.*', 'staff.actors.*', 'staff.directors.*', 'staff.genres.*'], 'Film catalog', 'book'],
            ['staff.seats.index', ['staff.seats.*'], 'Seats', 'seat'],
            ['staff.users.index', ['staff.users.*'], 'Staff accounts', 'user'],
        ],
    ];
    $user = auth()->user();
    $initials = strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1));
@endphp

<div class="admin-shell">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="sidebar__brand" href="{{ route('staff.dashboard') }}">
            <svg viewBox="0 0 40 40" aria-hidden="true">
                <rect x="1" y="1" width="38" height="38" rx="9" fill="#141219"/>
                <rect x="7" y="9" width="26" height="22" rx="4" fill="none" stroke="#ebbc00" stroke-width="2"/>
                <path d="M11 27 L16 19 L19 23 L23 15 L29 27 Z" fill="#fff"/>
            </svg>
            <div><b>CINEMATHEQUE</b><span>Centre Davao · Admin</span></div>
        </a>

        @foreach ($nav as $label => $links)
            @if ($label)<div class="side-label">{{ $label }}</div>@endif
            @foreach ($links as $link)
                <a class="side-link" href="{{ route($link[0]) }}" @if (request()->routeIs(...$link[1])) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$link[3]] !!}</svg>
                    {{ $link[2] }}
                    @if (! empty($link[4]))<span class="side-count" title="Free reservations awaiting approval">{{ $link[4] }}</span>@endif
                </a>
            @endforeach
        @endforeach

        <div class="sidebar__foot">Signed in as {{ $user->position ?? 'Staff' }}. AVT and PDO share the same access.</div>
    </aside>
    <div class="scrim" aria-hidden="true"></div>

    <div class="admin-main">
        <header class="topbar">
            <button type="button" class="topbar__toggle" data-sidebar-toggle aria-expanded="false" aria-label="Open navigation">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            <form class="topbar__search" method="GET" action="{{ route('staff.reservations.index') }}" role="search" data-no-loading>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <label class="sr-only" for="global-search">Find a booking</label>
                <input id="global-search" type="search" name="q" value="{{ request()->routeIs('staff.reservations.index') ? request('q') : '' }}" placeholder="Find booking by reference, name, email…  ( / )">
            </form>
            <div class="topbar__right">
                <a class="btn btn--ghost btn--sm topbar__site" href="{{ route('home') }}" target="_blank" rel="noopener">View site ↗</a>
                @include('partials.theme-toggle')
                <details class="usermenu">
                    <summary>
                        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                        <span class="usermenu__text"><span class="usermenu__name">{{ $user->full_name }}</span><br><span class="usermenu__role">{{ $user->position ?? 'Staff' }}</span></span>
                    </summary>
                    <div class="usermenu__panel">
                        <div class="small muted" style="padding:6px 10px">{{ $user->email }}</div>
                        <a href="{{ route('staff.users.edit', $user) }}">My account</a>
                        <form method="POST" action="{{ route('logout') }}" data-no-loading>
                            @csrf
                            <button type="submit">Log out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main id="main" class="content">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<dialog class="modal" id="confirm-modal" aria-labelledby="confirm-title">
    <div class="modal__body">
        <h2 id="confirm-title">Are you sure?</h2>
        <p class="muted" data-modal-message></p>
    </div>
    <div class="modal__actions">
        <button type="button" class="btn btn--ghost" data-modal-cancel>Go back</button>
        <button type="button" class="btn btn--primary" data-modal-confirm>Confirm</button>
    </div>
</dialog>
@stack('dialogs')
</body>
</html>
