<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Staff account management. Accounts are deactivated, never deleted. Only the Super Admin creates
 * accounts, sets roles and deactivates; everyone can edit their own name, email and password.
 */
class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('staff.users.index', [
            'users' => User::orderByRaw("role = 'super_admin' desc")->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('staff.users.form', ['staff' => new User(['is_active' => true, 'role' => 'admin'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        User::create($this->validated($request, null, true));

        return redirect()->route('staff.users.index')->with('status', 'Staff account created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('staff.users.form', ['staff' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $data = $this->validated($request, $user, Gate::allows('manage', $user));

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route($request->user()->isSuperAdmin() ? 'staff.users.index' : 'staff.dashboard')
            ->with('status', $user->is($request->user()) ? 'Your account is updated.' : 'Staff account updated.');
    }

    /** $manage: the Super Admin may also set the role and active status (never on their own account). */
    private function validated(Request $request, ?User $user, bool $manage): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => [
                'required', 'email', 'max:100',
                Rule::unique('users', 'email')->ignore($user?->user_id, 'user_id'),
            ],
            'position' => ['nullable', Rule::in(User::POSITIONS)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ];
        if ($manage) {
            $rules['role'] = ['nullable', Rule::in(User::ROLES)];
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        $validator = validator($request->all(), $rules);
        // There is exactly one Super Admin account.
        $validator->after(function (Validator $v) use ($request, $user, $manage) {
            if ($manage && $request->input('role') === 'super_admin'
                && User::where('role', 'super_admin')->when($user, fn ($q) => $q->whereKeyNot($user->user_id))->exists()) {
                $v->errors()->add('role', 'There is already a Super Admin account. There can only be one.');
            }
        });
        $data = $validator->validate();

        if ($manage) {
            $data['role'] = $data['role'] ?? 'admin';
            $data['is_active'] = $request->boolean('is_active');
        }

        return $data;
    }
}
