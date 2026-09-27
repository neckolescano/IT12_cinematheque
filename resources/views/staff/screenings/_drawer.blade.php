{{-- Create/edit screening drawer. Reopens automatically after a validation error. --}}
@php($editing = $screening->exists)
@php($id = $editing ? 'edit-screening' : 'new-screening')
<dialog class="drawer" id="{{ $id }}" aria-labelledby="{{ $id }}-title"
        @if ($errors->any() && old('_drawer') === $id) data-open-on-load @endif>
    <form method="POST" action="{{ $editing ? route('staff.screenings.update', $screening) : route('staff.screenings.store') }}" style="display:contents">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="_drawer" value="{{ $id }}">
        <div class="drawer__head">
            <h2 id="{{ $id }}-title">{{ $editing ? 'Edit screening' : 'New screening' }}</h2>
            <button type="button" class="icon-btn" data-close-dialog aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="drawer__body">
            @if ($errors->any() && old('_drawer') === $id)
                <div class="alert alert--error">Please fix the highlighted fields.</div>
            @endif
            @include('staff.screenings._fields', ['prefix' => $id])
        </div>
        <div class="drawer__foot">
            <button type="button" class="btn btn--ghost" data-close-dialog>Cancel</button>
            <button type="submit" class="btn btn--primary">{{ $editing ? 'Save changes' : 'Create screening' }}</button>
        </div>
    </form>
</dialog>
