<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>Staff sign in · CCD Admin</title>
    {{-- Always light (no theme toggle here): white form panel, black wordmark panel. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Oswald:wght@600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body class="signin-body">
{{-- Staff sign in: a white form panel and a black wordmark panel. Everything on the left shares one column
     (logo, form, footer on the same left edge); both panels use the same top/bottom padding, so the two
     footers sit on one line. --}}
<div class="signin">
    <main class="signin__form">
        <div class="signin__col">
            <div class="signin__brand">
                <span class="signin__mark" aria-hidden="true" style="--brand-mask: url('{{ asset('images/brand/cinematheque-serpent-mask.png') }}')"></span>
                <span><b>Cinematheque</b><small>Centre Davao</small></span>
            </div>

            <div class="signin__box">
                <h1>Staff sign in</h1>
                <p class="signin__lead">Manage screenings, reservations and door check-in.</p>

                @include('partials.flash')

                <form method="POST" action="{{ route('login') }}" class="signin__fields">
                    @csrf
                    <div class="field @error('email') has-error @enderror">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="signin__password">
                            <input type="password" id="password" name="password" required autocomplete="current-password">
                            <button type="button" class="signin__reveal" data-reveal-password aria-controls="password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn--primary btn--block signin__submit">Sign in</button>
                </form>

                <p class="signin__help">Forgot your password? Another staff member can set a new one under Staff accounts.</p>
            </div>

            <footer class="signin__foot">
                <span>&copy; {{ date('Y') }} Cinematheque Centre Davao · Staff only</span>
            </footer>
        </div>
    </main>

    <aside class="signin__art" aria-hidden="true">
        <div class="signin__wordmark" style="--wordmark-photo: url('{{ asset('images/about/ccd_facade.jpg') }}')"><span>Cinema</span><span>theque</span></div>
        <div class="signin__art-foot">
            <img src="{{ asset('images/brand/fdcp-reel.png') }}" alt="" width="84" height="95">
            <span>Film Development Council of the Philippines<small>Palma Gil St., Davao City</small></span>
        </div>
    </aside>
</div>

<script>
    // Show / hide the password (the form works without this).
    document.querySelectorAll('[data-reveal-password]').forEach(function (btn) {
        var input = document.getElementById(btn.getAttribute('aria-controls'));
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
            btn.setAttribute('aria-pressed', String(show));
            input.focus();
        });
    });
</script>
</body>
</html>
