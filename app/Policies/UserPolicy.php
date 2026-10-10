<?php

namespace App\Policies;

use App\Models\User;

/**
 * Staff accounts (revision phase 4): only the Super Admin creates accounts, sets roles and deactivates
 * users. Every staff member may still edit their own account (name, email, password).
 */
class UserPolicy extends StaffPolicy
{
    /** The accounts list is the Super Admin's; admins reach their own account from the account menu. */
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user) && $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user) && $user->isSuperAdmin();
    }

    public function update(User $user, mixed $target = null): bool
    {
        return $this->isStaff($user) && ($user->isSuperAdmin() || ($target instanceof User && $target->is($user)));
    }

    /** Role and active status are the Super Admin's to set, never on their own account. */
    public function manage(User $user, User $target): bool
    {
        return $this->isStaff($user) && $user->isSuperAdmin() && ! $target->is($user);
    }

    /** Staff accounts are deactivated, never deleted (they are referenced by audit FKs). */
    public function delete(User $user, mixed $target = null): bool
    {
        return false;
    }

    /** Only the Super Admin deactivates, and nobody can deactivate their own account and lock themselves out. */
    public function deactivate(User $user, User $target): bool
    {
        return $this->manage($user, $target);
    }
}
