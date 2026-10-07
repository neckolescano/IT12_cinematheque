{{--
    Genre chips from the fixed list, plus "Other" with a text box for a genre that isn't listed.
    Used by the screening form and the film form. Expects $p (id prefix) and $movie (nullable).
    Without JavaScript the "Other" box is always shown; with it, the box follows the chip.
--}}
@php
    $saved = $movie?->genres->pluck('genre_name')->all() ?? [];
    $selected = old('genres', array_values(array_intersect($saved, App\Models\Movie::GENRES)));
    $other = old('genre_other', $movie?->customGenres() ?? '');
    $otherOn = (bool) old('genre_other_on', filled($other));
@endphp
<div class="field @error('genre_other') has-error @enderror" data-genre-field>
    <span class="label" id="{{ $p }}_genres_label">Genres</span>
    <div class="chip-toggles" role="group" aria-labelledby="{{ $p }}_genres_label">
        @foreach (App\Models\Movie::GENRES as $g)
            <label class="chip-toggle"><input type="checkbox" name="genres[]" value="{{ $g }}" data-film-genre @checked(in_array($g, $selected, true))><span>{{ $g }}</span></label>
        @endforeach
        <label class="chip-toggle"><input type="checkbox" name="genre_other_on" value="1" data-genre-other-toggle @checked($otherOn)><span>Other</span></label>
    </div>
    <div class="genre-other" data-genre-other-box>
        <label for="{{ $p }}_genre_other" class="sr-only">Other genre</label>
        <input type="text" id="{{ $p }}_genre_other" name="genre_other" maxlength="100" value="{{ $other }}" placeholder="Other genre" data-genre-other>
    </div>
    @error('genres.*') <span class="field__error">{{ $message }}</span> @enderror
    @error('genre_other') <span class="field__error">{{ $message }}</span> @enderror
</div>
