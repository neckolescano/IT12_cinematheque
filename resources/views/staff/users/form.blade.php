@extends('layouts.staff')

@section('title', $staff->exists ? 'Edit staff account' : 'New staff account')

@section('content')
    @php($editing = $staff->exists)

    <div class="page-head">
        <div>
            <a class="back-link" href="{{ route('staff.users.index') }}"><x-arrow dir="left" /> Staff accounts</a>
            <h1>{{ $editing ? 'Edit staff account' : 'New staff account' }}</h1>
        </div>
    </div>

    <form class="panel" style="max-width:760px" method="POST" action="{{ $editing ? route('staff.users.update', $staff) : route('staff.users.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="form-grid">
            <div class="field @error('first_name') has-error @enderror">
                <label for="first_name">First name <span class="req">*</span></label>
                <input type="text" id="first_name" name="first_name" maxlength="50" required value="{{ old('first_name', $staff->first_name) }}">
                @error('first_name') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="middle_name">Middle name</label>
                <input type="text" id="middle_name" name="middle_name" maxlength="50" value="{{ old('middle_name', $staff->middle_name) }}">
            </div>
            <div class="field @error('last_name') has-error @enderror">
                <label for="last_name">Last name <span class="req">*</span></label>
                <input type="text" id="last_name" name="last_name" maxlength="50" required value="{{ old('last_name', $staff->last_name) }}">
                @error('last_name') <span class="field__error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-grid">
            <div class="field @error('email') has-error @enderror">
                <label for="email">Email <span class="req">*</span></label>
                <input type="email" id="email" name="email" maxlength="100" required value="{{ old('email', $staff->email) }}" autocomplete="off">
                @error('email') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field @error('position') has-error @enderror">
                <label for="position">Position</label>
                <select id="position" name="position">
                    <option value="">—</option>
                    @foreach (App\Models\User::POSITIONS as $pos)
                        <option value="{{ $pos }}" @selected(old('position', $staff->position) === $pos)>{{ $pos }}</option>
                    @endforeach
                </select>
                @error('position') <span class="field__error">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="form-grid">
            <div class="field @error('password') has-error @enderror">
                <label for="password">Password @unless ($editing)<span class="req">*</span>@endunless</label>
                <input type="password" id="password" name="password" @required(! $editing) autocomplete="new-password">
                <span class="hint">{{ $editing ? 'Leave blank to keep the current password.' : 'At least 8 characters.' }}</span>
                @error('password') <span class="field__error">{{ $message }}</span> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
            </div>
        </div>

        {{-- Role and active status: the Super Admin's to set, never on their own account. --}}
        @can('manage', $staff)
            <div class="form-grid">
                <div class="field @error('role') has-error @enderror">
                    <label for="role">Role <span class="req">*</span></label>
                    <select id="role" name="role">
                        <option value="admin" @selected(old('role', $staff->role) === 'admin')>Admin</option>
                        <option value="super_admin" @selected(old('role', $staff->role) === 'super_admin')>Super Admin</option>
                    </select>
                    <span class="hint">Admins run screenings, bookings and reports. The one Super Admin also unlocks reports and manages accounts.</span>
                    @error('role') <span class="field__error">{{ $message }}</span> @enderror
                </div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <label class="check">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
                Active (can sign in)
            </label>
            @error('is_active') <div class="field__error">{{ $message }}</div> @enderror
        @else
            @if ($editing)
                <p class="hint">Role: <strong>{{ $staff->isSuperAdmin() ? 'Super Admin' : 'Admin' }}</strong>. Only the Super Admin changes roles and account status.</p>
            @endif
        @endcan

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">{{ $editing ? 'Save changes' : 'Create account' }}</button>
            <a class="btn btn--secondary" href="{{ auth()->user()->isSuperAdmin() ? route('staff.users.index') : route('staff.dashboard') }}">Cancel</a>
        </div>
    </form>
@endsection
