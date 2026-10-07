<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#faf9f7">
<meta name="color-scheme" content="light dark">
<meta name="view-transition" content="same-origin">
<title>@yield('title', 'Screenings') · Cinematheque Centre Davao</title>
<script>document.documentElement.classList.replace('no-js', 'js');</script>
@include('partials.theme')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600&family=Poppins:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('css/cinematheque.css') }}?v={{ filemtime(public_path('css/cinematheque.css')) }}">
<script src="{{ asset('js/cinematheque.js') }}?v={{ filemtime(public_path('js/cinematheque.js')) }}" defer></script>
