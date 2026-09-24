<?php

namespace App\Policies;

use App\Models\PaymentProof;
use App\Models\User;

class PaymentProofPolicy extends StaffPolicy
{
    /** Proofs are uploaded by moviegoers only; staff never create them. */
    public function create(User $user): bool
    {
        return false;
    }

    /** Each submission is reviewed exactly once, and never for a cancelled reservation. */
    public function review(User $user, PaymentProof $proof): bool
    {
        return $this->isStaff($user)
            && $proof->status === 'pending'
            && $proof->payment->reservation->status !== 'cancelled';
    }
}
