{{-- Trailer link: YouTube/Vimeo play in the trailer modal (cinematheque.js); anything else, or no JS, opens in a new tab.
     Expects $movie and $class. --}}
<a class="{{ $class }}" href="{{ $movie->trailer_url }}" target="_blank" rel="noopener"
   @if ($embed = $movie->trailerEmbedUrl()) data-trailer="{{ $embed }}" data-trailer-title="{{ $movie->title }}" @endif>
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l11-6.5a1 1 0 0 0 0-1.72l-11-6.5A1 1 0 0 0 8 5.5Z"/></svg>
    Trailer<span class="sr-only">: {{ $movie->title }}</span>
</a>
