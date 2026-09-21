<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cinematheque Davao')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cinematheque.css') }}">
</head>
<body class="@yield('body_class')">
    @hasSection('no_chrome')
        <div class="brand-bar">
            <a href="{{ route('home') }}" class="brand">
                <img src="{{ asset('images/logo.png') }}" alt="" onerror="this.style.display='none'">
                <span>CINEMATHEQUE DAVAO</span>
            </a>
        </div>
    @else
        @include('partials.navbar')
        <div class="pattern-line"></div>
    @endif

    <main>
        @yield('content')
    </main>

    @hasSection('no_chrome')
    @else
        <div class="pattern-line"></div>
        @include('partials.footer')
    @endif

    @stack('scripts')
</body>
</html>
