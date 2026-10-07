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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@500;600&family=Geist:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="auth-page">
    <main class="auth-page__form" style="position:relative">
        <div style="position:absolute;top:20px;right:20px">@include('partials.theme-toggle')</div>
        <div style="width:100%;max-width:400px">
            <h1 style="margin-bottom:6px">Staff sign in</h1>
            <p class="muted">Cinematheque Centre Davao</p>

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
        <div style="font-weight:600;letter-spacing:.08em;font-size:22px">CINEMATHEQUE</div>
        <div style="color:#9ca3af;letter-spacing:.2em;font-size:12px;font-weight:500">CENTRE DAVAO</div>
    </aside>
</div>
</body>
</html>
