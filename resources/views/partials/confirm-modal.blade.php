{{-- Shared confirmation dialog. Any <form data-confirm="Message"> opens it (see cinematheque.js). --}}
<dialog class="modal" id="confirm-modal" aria-labelledby="confirm-modal-title">
    <div class="modal__body">
        <h2 id="confirm-modal-title">Are you sure?</h2>
        <p class="muted" data-modal-message></p>
    </div>
    <div class="modal__actions">
        <button type="button" class="btn btn--secondary" data-modal-cancel>Go back</button>
        <button type="button" class="btn btn--dark" data-modal-confirm>Confirm</button>
    </div>
</dialog>
<div class="page-progress" aria-hidden="true"></div>
