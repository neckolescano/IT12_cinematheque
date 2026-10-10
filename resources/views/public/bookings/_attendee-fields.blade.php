{{-- One person, keyed by seat id: attendees[<seat_id>][field]. The logsheet details, all required
     except middle name, senior citizen ID and PWD ID. Expects $seat, $primary, $booker (first seat). --}}
@php
    $p = 'attendees.'.$seat->seat_id.'.';
    $n = 'attendees['.$seat->seat_id.']';
    $id = 'a'.$seat->seat_id.'_';
    // The mobile field holds the 10 digits after +63, shown as 9XX XXX XXXX.
    $digits = preg_match('/(9\d{2})(\d{3})(\d{4})$/', preg_replace('/\D/', '', (string) old($p.'contact_no')), $m) ? "{$m[1]} {$m[2]} {$m[3]}" : '';
    $idsOpen = filled(old($p.'senior_card_no')) || filled(old($p.'pwd_id_no')) || $errors->has($p.'pwd_id_no') || $errors->has($p.'senior_card_no');
@endphp
<fieldset class="person" data-person @if ($primary) data-primary @endif>
    <legend class="sr-only">Attendee for seat {{ $seat->seat_label }}</legend>
    <div class="person__head">
        <span class="seat-chip">Seat {{ $seat->seat_label }}</span>
        @if ($primary)
            <span class="pill pill--ink">You · primary booker</span>
        @else
            <span class="muted small">Guest</span>
            <label class="check person__same"><input type="checkbox" data-same-contact> Same mobile and email as seat {{ $booker->seat_label }}</label>
        @endif
    </div>

    <div class="grid grid--name">
        <div class="field @error($p.'first_name') has-error @enderror">
            <label for="{{ $id }}first">First name <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="{{ $id }}first" name="{{ $n }}[first_name]" value="{{ old($p.'first_name') }}" maxlength="50" required @if ($primary) autocomplete="given-name" @endif>
            @error($p.'first_name') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field">
            <label for="{{ $id }}middle">Middle name <span class="optional">optional</span></label>
            <input type="text" id="{{ $id }}middle" name="{{ $n }}[middle_name]" value="{{ old($p.'middle_name') }}" maxlength="50" @if ($primary) autocomplete="additional-name" @endif>
        </div>
        <div class="field @error($p.'last_name') has-error @enderror">
            <label for="{{ $id }}last">Last name <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="{{ $id }}last" name="{{ $n }}[last_name]" value="{{ old($p.'last_name') }}" maxlength="50" required @if ($primary) autocomplete="family-name" @endif>
            @error($p.'last_name') <span class="field__error">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="grid grid--about">
        <div class="field @error($p.'age') has-error @enderror">
            <label for="{{ $id }}age">Age <span class="req" aria-hidden="true">*</span></label>
            <input type="number" id="{{ $id }}age" name="{{ $n }}[age]" value="{{ old($p.'age') }}" min="1" max="120" inputmode="numeric" required>
            @error($p.'age') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field @error($p.'sex') has-error @enderror">
            <span class="label" id="{{ $id }}sex">Sex <span class="req" aria-hidden="true">*</span></span>
            <div class="segmented" role="radiogroup" aria-labelledby="{{ $id }}sex">
                <label><input type="radio" name="{{ $n }}[sex]" value="M" @checked(old($p.'sex') === 'M') required><span>Male</span></label>
                <label><input type="radio" name="{{ $n }}[sex]" value="F" @checked(old($p.'sex') === 'F')><span>Female</span></label>
            </div>
            @error($p.'sex') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field @error($p.'company_school') has-error @enderror">
            <label for="{{ $id }}company">School or company <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="{{ $id }}company" name="{{ $n }}[company_school]" value="{{ old($p.'company_school') }}" maxlength="150" required @if ($primary) autocomplete="organization" @endif>
            @error($p.'company_school') <span class="field__error">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="grid grid--contact">
        <div class="field @error($p.'contact_no') has-error @enderror">
            <label for="{{ $id }}contact">Mobile number <span class="req" aria-hidden="true">*</span></label>
            <div class="phone">
                <span class="phone__prefix" aria-hidden="true">+63</span>
                <input type="text" id="{{ $id }}contact" name="{{ $n }}[contact_no]" value="{{ $digits }}" required
                       inputmode="numeric" maxlength="16" pattern="9[0-9]{2} ?[0-9]{3} ?[0-9]{4}" placeholder="9XX XXX XXXX" data-digits data-contact="mobile"
                       aria-describedby="{{ $id }}contact_hint" @if ($primary) autocomplete="tel-national" @endif>
            </div>
            <span class="sr-only" id="{{ $id }}contact_hint">The 10 digits after +63, starting with 9</span>
            @error($p.'contact_no') <span class="field__error">{{ $message }}</span> @enderror
        </div>
        <div class="field @error($p.'email') has-error @enderror">
            <label for="{{ $id }}email">Email <span class="req" aria-hidden="true">*</span></label>
            <input type="email" id="{{ $id }}email" name="{{ $n }}[email]" value="{{ old($p.'email') }}" maxlength="100" required data-contact="email" @if ($primary) autocomplete="email" @endif>
            @error($p.'email') <span class="field__error">{{ $message }}</span> @enderror
        </div>
    </div>

    <details class="person__ids" @if ($idsOpen) open @endif>
        <summary>Senior citizen or PWD ID <span class="optional">optional · 20% off on paid screenings</span></summary>
        <div class="grid grid--ids">
            <div class="field @error($p.'senior_card_no') has-error @enderror">
                <label for="{{ $id }}senior">Senior citizen ID no.</label>
                <input type="text" id="{{ $id }}senior" name="{{ $n }}[senior_card_no]" value="{{ old($p.'senior_card_no') }}" minlength="4" maxlength="20" pattern="[A-Za-z0-9\- ]{4,20}" title="4–20 letters, numbers, spaces or hyphens">
                @error($p.'senior_card_no') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error($p.'pwd_id_no') has-error @enderror">
                <label for="{{ $id }}pwd">PWD ID no.</label>
                <input type="text" id="{{ $id }}pwd" name="{{ $n }}[pwd_id_no]" value="{{ old($p.'pwd_id_no') }}" inputmode="numeric" maxlength="19" placeholder="RR-PPMM-BBB-NNNNNNN" pattern="[0-9]{2}-[0-9]{4}-[0-9]{3}-[0-9]{7}" title="16-digit PWD ID number" data-pwd-id>
                @error($p.'pwd_id_no') <span class="field__error">{{ $message }}</span> @enderror
            </div>
        </div>
    </details>
</fieldset>
