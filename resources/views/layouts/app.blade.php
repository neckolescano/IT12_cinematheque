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
            <a href="{{ route('home') }}" @if (request()->routeIs('home', 'screenings.*', 'bookings.create')) aria-current="page" @endif>Screenings</a>
            <a href="{{ route('bookings.lookup') }}" @if (request()->routeIs('bookings.lookup', 'bookings.show')) aria-current="page" @endif>Find my booking</a>
            {{-- No staff/admin link: the admin area is not advertised to customers. --}}
            @include('partials.theme-toggle')
        </nav>
    </div>
</header>

@yield('hero')

<main id="main" class="@yield('main_class', 'section')">
    <div class="container">
        @include('partials.flash')
        @yield('content')
    </div>
</main>

<footer class="site-footer">
    @include('partials.skyline')
    <div class="container">
        <div class="site-footer__grid">
            <div>
                @include('partials.brand')
                <p class="small" style="margin-top:var(--s-4);max-width:36ch">A home for Philippine cinema in Davao City — screenings, retrospectives, talks and the local film community.</p>
            </div>
            <div>
                <h4>Moviegoers</h4>
                <ul>
                    <li><a href="{{ route('home') }}">Upcoming screenings</a></li>
                    <li><a href="{{ route('bookings.lookup') }}">Find my booking</a></li>
                </ul>
            </div>
            <div>
                <h4>How it works</h4>
                <ul>
                    <li>Reserve seats online — no account needed</li>
                    <li>Paid screenings: pay through PayMongo</li>
                    <li>Your e-ticket arrives by email</li>
                </ul>
            </div>
        </div>
        <div class="site-footer__base">
            <span>&copy; {{ date('Y') }} Cinematheque Centre Davao · Davao City</span>
            <span>An FDCP Cinematheque Centre</span>
        </div>
    </div>
</footer>

@include('partials.confirm-modal')
</body>
</html>
