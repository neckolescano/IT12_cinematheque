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

{{-- Footer (after fdcp.ph): link columns, then CINEMATHEQUE as a giant wordmark filled with yellow/black artwork. --}}
@php($social = array_filter(config('cinematheque.social', [])))
<footer class="site-footer">
    <div class="container">
        <div class="site-footer__top">
            <div class="site-footer__brand">
                @include('partials.brand')
                <p>Philippine and world cinema on the big screen, in the heart of Davao City.</p>
            </div>
            <nav class="site-footer__cols" aria-label="Footer">
                <div>
                    <h2>Explore</h2>
                    <ul>
                        <li><a href="{{ route('home') }}">Screenings</a></li>
                        <li><a href="{{ route('bookings.lookup') }}">Find my booking</a></li>
                        <li><a href="{{ route('about') }}">About</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Programmes</h2>
                    <ul>
                        <li><a href="{{ route('home') }}#showing-now">Now Showing</a></li>
                        <li><a href="{{ route('home') }}#showing-advance">Advance Booking</a></li>
                        <li><a href="{{ route('home') }}#showing-soon">Coming Soon</a></li>
                        <li><a href="{{ route('home') }}#showing-special">Special Screenings</a></li>
                    </ul>
                </div>
                <div>
                    <h2>Visit</h2>
                    <ul>
                        <li>Cinematheque Centre Davao</li>
                        <li>Palma Gil St., Davao City</li>
                    </ul>
                </div>
                @if ($social)
                    <div>
                        <h2>Follow</h2>
                        <ul>
                            @foreach ($social as $name => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener">{{ $name }}</a></li>@endforeach
                        </ul>
                    </div>
                @endif
            </nav>
        </div>
    </div>
    {{-- Full footer width; the letters are filled with the Cinematheque facade photo, toned in brand yellow. --}}
    <div class="footer-wordmark" aria-hidden="true" style="--wordmark-photo: url('{{ asset('images/about/ccd_facade.jpg') }}')">Cinematheque</div>
    <div class="container site-footer__bottom">
        <p class="site-footer__note">&copy; {{ date('Y') }} Cinematheque Centre Davao · Palma Gil St., Davao City</p>
        <a class="fdcp-badge" href="{{ config('cinematheque.fdcp_url') }}" target="_blank" rel="noopener">
            <img src="{{ asset('images/brand/fdcp-reel.png') }}" alt="" width="84" height="95">
            <span>An FDCP Cinematheque Centre<small>Film Development Council of the Philippines</small></span>
        </a>
    </div>
</footer>

@include('partials.confirm-modal')
</body>
</html>
