<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Staff account management. Accounts are deactivated, never deleted. */
class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('staff.users.index', [
            'users' => User::orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('staff.users.form', ['staff' => new User(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        User::create($this->validated($request));

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

        $data = $this->validated($request, $user);

        if (! $data['is_active'] && Gate::denies('deactivate', $user)) {
            return back()->withInput()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('staff.users.index')->with('status', 'Staff account updated.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => [
                'required', 'email', 'max:100',
                Rule::unique('users', 'email')->ignore($user?->user_id, 'user_id'),
            ],
            'position' => ['nullable', Rule::in(User::POSITIONS)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
