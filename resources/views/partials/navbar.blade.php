<header class="site-header">
    <div class="container">
        <a href="{{ route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="" onerror="this.style.display='none'">
            <span>CINEMATHEQUE DAVAO</span>
        </a>
        <nav class="nav-links" aria-label="Main">
            <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active' : '' }}">Home</a>
            <a href="{{ route('movies.index') }}" class="{{ request()->routeIs('movies.*', 'booking.*') ? 'active' : '' }}">Movies</a>
            <a href="{{ route('tickets.lookup') }}" class="{{ request()->routeIs('tickets.*') ? 'active' : '' }}">My tickets</a>
        </nav>
    </div>
</header>
