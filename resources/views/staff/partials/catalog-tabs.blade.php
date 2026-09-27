{{-- Film catalog = one sidebar item; its four lists are tabs here instead of four menu entries. --}}
<nav class="tabs" aria-label="Film catalog">
    <a href="{{ route('staff.movies.index') }}" @if (request()->routeIs('staff.movies.*')) aria-current="page" @endif>Movies</a>
    <a href="{{ route('staff.actors.index') }}" @if (request()->routeIs('staff.actors.*')) aria-current="page" @endif>Actors</a>
    <a href="{{ route('staff.directors.index') }}" @if (request()->routeIs('staff.directors.*')) aria-current="page" @endif>Directors</a>
    <a href="{{ route('staff.genres.index') }}" @if (request()->routeIs('staff.genres.*')) aria-current="page" @endif>Genres</a>
</nav>
