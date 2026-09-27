{{-- One attendee's fields (logsheet details), keyed by seat id: attendees[<seat_id>][field]. --}}
@php
    $p = 'attendees.'.$seat->seat_id.'.';
    $n = 'attendees['.$seat->seat_id.']';
    $id = 'a'.$seat->seat_id.'_';
@endphp
<div class="form-grid">
    <div class="field @error($p.'first_name') has-error @enderror">
        <label for="{{ $id }}first">First name <span class="req">*</span></label>
        <input type="text" id="{{ $id }}first" name="{{ $n }}[first_name]" value="{{ old($p.'first_name') }}" maxlength="50" required>
        @error($p.'first_name') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="{{ $id }}middle">Middle name</label>
        <input type="text" id="{{ $id }}middle" name="{{ $n }}[middle_name]" value="{{ old($p.'middle_name') }}" maxlength="50">
    </div>
    <div class="field @error($p.'last_name') has-error @enderror">
        <label for="{{ $id }}last">Last name <span class="req">*</span></label>
        <input type="text" id="{{ $id }}last" name="{{ $n }}[last_name]" value="{{ old($p.'last_name') }}" maxlength="50" required>
        @error($p.'last_name') <span class="field__error">{{ $message }}</span> @enderror
    </div>
</div>
<div class="form-grid">
    <div class="field @error($p.'age') has-error @enderror">
        <label for="{{ $id }}age">Age</label>
        <input type="number" id="{{ $id }}age" name="{{ $n }}[age]" value="{{ old($p.'age') }}" min="0" max="255">
        @error($p.'age') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="{{ $id }}sex">Sex</label>
        <select id="{{ $id }}sex" name="{{ $n }}[sex]">
            <option value="">—</option>
            <option value="M" @selected(old($p.'sex') === 'M')>M</option>
            <option value="F" @selected(old($p.'sex') === 'F')>F</option>
        </select>
    </div>
    <div class="field">
        <label for="{{ $id }}company">Company / School</label>
        <input type="text" id="{{ $id }}company" name="{{ $n }}[company_school]" value="{{ old($p.'company_school') }}" maxlength="150">
    </div>
</div>
<div class="form-grid">
    <div class="field">
        <label for="{{ $id }}contact">Contact no.</label>
        <input type="text" id="{{ $id }}contact" name="{{ $n }}[contact_no]" value="{{ old($p.'contact_no') }}" maxlength="20" inputmode="tel">
    </div>
    <div class="field @error($p.'email') has-error @enderror">
        <label for="{{ $id }}email">Email</label>
        <input type="email" id="{{ $id }}email" name="{{ $n }}[email]" value="{{ old($p.'email') }}" maxlength="100">
        @error($p.'email') <span class="field__error">{{ $message }}</span> @enderror
    </div>
    <div class="field">
        <label for="{{ $id }}senior">Senior citizen card no.</label>
        <input type="text" id="{{ $id }}senior" name="{{ $n }}[senior_card_no]" value="{{ old($p.'senior_card_no') }}" maxlength="30">
    </div>
</div>
<input type="hidden" name="{{ $n }}[pwd_indicator]" value="0">
<label class="check">
    <input type="checkbox" name="{{ $n }}[pwd_indicator]" value="1" @checked(old($p.'pwd_indicator'))>
    Person with disability (PWD)
</label>
