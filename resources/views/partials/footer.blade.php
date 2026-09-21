<footer class="site-footer">
    <div class="container">
        <a href="{{ route('home') }}" class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="" onerror="this.style.display='none'">
            <span>Cinematheque Davao</span>
        </a>
        <small>&copy; {{ date('Y') }} Cinematheque Davao. All rights reserved.</small>
        <div class="footer-links">
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
            <a href="{{ route('login') }}">Admin</a>
        </div>
    </div>
</footer>
