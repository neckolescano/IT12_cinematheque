<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends StaffPolicy
{
    /** Staff accounts are deactivated, never deleted (they are referenced by audit FKs). */
    public function delete(User $user, mixed $target = null): bool
    {
        return false;
    }

    /** Nobody can deactivate their own account and lock themselves out. */
    public function deactivate(User $user, User $target): bool
    {
        return $this->isStaff($user) && $user->user_id !== $target->user_id;
    }
}
