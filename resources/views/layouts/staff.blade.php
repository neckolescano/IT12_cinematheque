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
    <script>document.documentElement.classList.add('js')</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@500;600&family=Oswald:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

@php
    // Sidebar is organised by staff task, not by database table.
    $icons = [
        'dash' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'film' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 4v16M17 4v16M3 9h4M3 15h4M17 9h4M17 15h4"/>',
        'ticket' => '<path d="M3 8a2 2 0 0 0 0 4v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 1 0-4V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z"/><path d="M13 4v14" stroke-dasharray="2 2"/>',
        'check' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
        'book' => '<path d="M4 19.5V5a2 2 0 0 1 2-2h14v16H6.5A2.5 2.5 0 0 0 4 21.5v-2z"/><path d="M8 7h8M8 11h6"/>',
        'seat' => '<path d="M6 11V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v5"/><path d="M4 11h16v5H4zM6 16v4M18 16v4"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
    ];
    $user = auth()->user();
    // Super Admin: submitted reports waiting in the inbox (submitted = locked, sent for review).
    $reportInbox = $user->isSuperAdmin() ? \App\Models\Report::where('status', 'submitted')->count() : 0;
    // Three domains: Operations (daily work), Insights (reports), Settings (configuration only).
    $nav = [
        null => [
            ['staff.dashboard', ['staff.dashboard', 'staff.search'], 'Dashboard', 'dash'],
        ],
        'Operations' => [
            ['staff.movies.index', ['staff.movies.*', 'staff.screenings.create', 'staff.screenings.edit'], 'Films & schedule', 'film'],
            ['staff.screenings.index', ['staff.screenings.index', 'staff.screenings.show', 'staff.screenings.preview'], 'Screenings & check-in', 'check'],
        ],
        'Insights' => [
            ['staff.program-reports.index', ['staff.program-reports.*'], 'Program reports', 'file', $reportInbox],
            ['staff.reports.index', ['staff.reports.*'], 'Summary', 'chart'],
        ],
        'Settings' => $user->isSuperAdmin() ? [['staff.users.index', ['staff.users.*'], 'Staff accounts', 'user']] : [],
    ];
    $initials = strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1));
@endphp

<div class="admin-shell">
    <aside class="sidebar" aria-label="Admin navigation">
        <a class="sidebar__brand" href="{{ route('staff.dashboard') }}">
            <span class="sidebar__mark" aria-hidden="true" style="--brand-mask: url('{{ asset('images/brand/cinematheque-serpent-mask.png') }}')"></span>
            <div><b>CINEMATHEQUE</b><span>Centre Davao</span></div>
        </a>

        @foreach ($nav as $label => $links)
            @continue(empty($links))
            @if ($label)<div class="side-label">{{ $label }}</div>@endif
            @foreach ($links as $link)
                <a class="side-link" href="{{ route($link[0]) }}" @if (request()->routeIs(...$link[1])) aria-current="page" @endif>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$link[3]] !!}</svg>
                    {{ $link[2] }}
                    @if (! empty($link[4]))<span class="side-count" title="Submitted reports to review">{{ $link[4] }}</span>@endif
                </a>
            @endforeach
        @endforeach

    </aside>
    <div class="scrim" aria-hidden="true"></div>

    <div class="admin-main">
        <header class="topbar">
            <button type="button" class="topbar__toggle" data-sidebar-toggle aria-expanded="false" aria-label="Open navigation">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
            </button>
            {{-- One search for everything: bookings, attendees, screenings, films ("/" focuses it). --}}
            <form class="topbar__search" method="GET" action="{{ route('staff.search') }}" role="search" data-no-loading>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <label class="sr-only" for="global-search">Search bookings, people, screenings and films</label>
                <input id="global-search" type="search" name="q" value="{{ request()->routeIs('staff.search') ? request('q') : '' }}"
                       placeholder="Search bookings, people, films" autocomplete="off">
                <kbd aria-hidden="true">/</kbd>
            </form>
            <div class="topbar__right">
                <a class="btn btn--secondary btn--sm topbar__site" href="{{ route('home') }}" target="_blank" rel="noopener">Public site ↗</a>
                @include('partials.theme-toggle')
                <details class="usermenu">
                    <summary aria-label="Account menu for {{ $user->full_name }}">
                        <span class="avatar" aria-hidden="true">{{ $initials }}</span>
                        <svg class="usermenu__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="usermenu__panel">
                        <div class="usermenu__who">
                            <strong>{{ $user->full_name }}</strong>
                            <span class="small muted">{{ $user->position ?? 'Staff' }} · {{ $user->email }}</span>
                        </div>
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
        <h2 id="confirm-title">Confirm</h2>
        <p class="muted" data-modal-message></p>
    </div>
    <div class="modal__actions">
        <button type="button" class="btn btn--secondary" data-modal-cancel>Cancel</button>
        <button type="button" class="btn btn--primary" data-modal-confirm>Confirm</button>
    </div>
</dialog>
@stack('dialogs')
</body>
</html>
