<?php

namespace App\Policies;

use App\Models\User;

/**
 * Base policy for every staff-managed model.
 *
 * Per the RBAC spec there is exactly one access tier: an active staff account.
 * Guests (moviegoers) never reach these methods — Laravel denies a policy whose
 * $user parameter is not nullable — so `@can` in a public view is false for them.
 * `position` (AVT/PDO) is intentionally never checked.
 *
 * Subclasses only add *state* rules (e.g. "a proof can be reviewed once"),
 * never role rules.
 */
abstract class StaffPolicy
{
    protected function isStaff(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, mixed $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, mixed $model = null): bool
    {
        return $this->isStaff($user);
    }

    public function delete(User $user, mixed $model = null): bool
    {
        return $this->isStaff($user);
    }
}
