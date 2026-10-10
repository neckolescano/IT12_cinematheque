<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Program reports: admins move a report forward (generate, edit, submit); a submitted report is read-only
 * for everyone until the Super Admin unlocks it. Admins can only ask for an unlock, with a reason.
 */
class ReportPolicy extends StaffPolicy
{
    /** Edit the Partner / Agency / Notes columns, regenerate or submit. */
    public function update(User $user, mixed $report = null): bool
    {
        return $this->isStaff($user) && ! ($report instanceof Report && $report->isLocked());
    }

    public function requestUnlock(User $user, Report $report): bool
    {
        return $this->isStaff($user) && $report->isLocked() && ! $user->isSuperAdmin() && ! $report->pendingUnlockRequest();
    }

    public function unlock(User $user, Report $report): bool
    {
        return $this->isStaff($user) && $user->isSuperAdmin() && $report->isLocked();
    }

    /** Reports are kept: they are the record sent to Manila. */
    public function delete(User $user, mixed $report = null): bool
    {
        return false;
    }
}
