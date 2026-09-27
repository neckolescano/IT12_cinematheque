<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>Staff sign in · CCD Admin</title>
    @include('partials.theme')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="auth-page">
    <main class="auth-page__form" style="position:relative">
        <div style="position:absolute;top:20px;right:20px">@include('partials.theme-toggle')</div>
        <div style="width:100%;max-width:400px">
            <h1 style="margin-bottom:6px">Staff sign in</h1>
            <p class="muted">Cinematheque Centre Davao admin. Staff accounts only.</p>

            @include('partials.flash')

            <form method="POST" action="{{ route('login') }}" style="margin-top:24px">
                @csrf
                <div class="field @error('email') has-error @enderror">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn--primary btn--block" style="margin-top:8px">Sign in</button>
            </form>
        </div>
    </main>
    <aside class="auth-page__art" aria-hidden="true">
        <div style="font-weight:700;letter-spacing:.12em;font-size:22px">CINEMATHEQUE</div>
        <div style="color:#ebbc00;letter-spacing:.3em;font-size:12px;font-weight:600;margin-bottom:28px">CENTRE DAVAO</div>
        <p style="max-width:34ch;color:#c9c4d1;font-size:15px">Screenings, reservations, payments and admission — in one place for Cinematheque staff.</p>
    </aside>
</div>
</body>
</html>
