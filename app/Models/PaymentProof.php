<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    public const STATUSES = ['pending', 'accepted', 'rejected'];

    protected $primaryKey = 'proof_id';

    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'proof_image', 'submitted_at', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }
}
