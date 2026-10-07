<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    @include('partials.head')
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container site-header__inner">
        @include('partials.brand')

        <button type="button" class="nav-toggle" data-toggle-class="nav-open" aria-expanded="false" aria-controls="site-nav">
            <span></span><span></span><span></span>
            <span class="sr-only">Menu</span>
        </button>

        <nav class="nav" id="site-nav" aria-label="Main">
            <a href="{{ route('home') }}" @if (request()->routeIs('home', 'screenings.*', 'bookings.create', 'bookings.show')) aria-current="page" @endif>Screenings</a>
            <a href="{{ route('bookings.lookup') }}" @if (request()->routeIs('bookings.lookup', 'bookings.ticket')) aria-current="page" @endif>Find my booking</a>
            <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif>About</a>
            @include('partials.theme-toggle')
        </nav>
    </div>
</header>

@yield('hero')

<main id="main" class="@yield('main_class', 'page')">
    <div class="container">
        @include('partials.flash')
        @yield('content')
    </div>
</main>

<footer class="site-footer">
    <div class="container site-footer__inner">
        @include('partials.brand')
        <nav class="site-footer__nav" aria-label="Footer">
            <a href="{{ route('home') }}">Screenings</a>
            <a href="{{ route('bookings.lookup') }}">Find my booking</a>
            <a href="{{ route('about') }}">About</a>
        </nav>
        <p class="site-footer__note">An FDCP Cinematheque Centre · Palma Gil St., Davao City · &copy; {{ date('Y') }}</p>
    </div>
    @include('partials.skyline')
</footer>

@include('partials.confirm-modal')
</body>
</html>
