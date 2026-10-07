{{-- One shared trailer player. cinematheque.js loads the clicked trailer into the frame and empties it on close
     (so playback stops). --}}
<dialog class="trailer-modal" id="trailer-modal" aria-labelledby="trailer-modal-title">
    <div class="trailer-modal__head">
        <h2 id="trailer-modal-title" data-trailer-heading>Trailer</h2>
        <button type="button" class="trailer-modal__close" data-trailer-close aria-label="Close trailer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>
    <div class="trailer-modal__frame">
        <iframe title="Trailer" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen data-trailer-frame></iframe>
    </div>
</dialog>
