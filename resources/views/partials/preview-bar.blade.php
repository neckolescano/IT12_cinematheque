{{-- Staff "Review" step: the customer page as it will look, under a bar with Back to edit and Publish.
     Expects $preview = ['label' => ..., 'back' => url, 'publish' => url|null, 'film' => showcase card data]. --}}
<div class="preview-bar" role="region" aria-label="Preview">
    <div class="container preview-bar__inner">
        <div class="preview-bar__text">
            <strong>Preview</strong>
            <span>{{ $preview['label'] }}</span>
        </div>
        <div class="preview-bar__actions">
            <a class="btn btn--ghost btn--sm" href="{{ $preview['back'] }}"><x-arrow dir="left" /> Back to edit</a>
            @if ($preview['publish'])
                <form method="POST" action="{{ $preview['publish'] }}">
                    @csrf
                    <button type="submit" class="btn btn--gold btn--sm">Publish</button>
                </form>
            @endif
        </div>
    </div>
</div>
