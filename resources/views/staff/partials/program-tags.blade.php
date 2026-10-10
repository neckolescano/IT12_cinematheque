{{-- Program tags (free text with suggestions): chips with a hidden programs[] each, plus a text box that also
     submits as programs[] (so a tag typed but not yet turned into a chip still counts). Enter or a comma adds
     a chip; × removes one. Tags that differ only in case or spacing are one program (Program::fromTag).
     Expects $chosen (names) and $suggestions (names). --}}
<div class="field @error('programs') has-error @enderror @error('programs.*') has-error @enderror" data-tags>
    <label for="program-tag-input">Programs</label>
    <div class="tags-input">
        @foreach ($chosen as $name)
            <span class="tag-chip" data-tag="{{ mb_strtolower($name) }}">{{ $name }}<input type="hidden" name="programs[]" value="{{ $name }}"><button type="button" data-tag-remove aria-label="Remove {{ $name }}">×</button></span>
        @endforeach
        <input type="text" id="program-tag-input" name="programs[]" list="program-suggestions" maxlength="100" autocomplete="off"
               placeholder="{{ $chosen->isEmpty() ? 'e.g. Cinemalaya 2026' : 'Add another' }}" aria-describedby="program-tags-hint" data-tag-input>
    </div>
    <datalist id="program-suggestions">@foreach ($suggestions as $name)<option value="{{ $name }}">@endforeach</datalist>
    <span class="hint" id="program-tags-hint">Press Enter after each program. A film can be in several.</span>
    @error('programs.*') <span class="field__error">{{ $message }}</span> @enderror
</div>
