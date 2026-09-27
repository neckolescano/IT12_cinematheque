<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUSES = ['pending', 'verified', 'rejected'];

    protected $primaryKey = 'payment_id';

    /** Only created_at exists on this table. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'reservation_id', 'amount', 'payment_channel', 'status',
        'provider_session_id', 'provider_payment_id', 'paid_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    /** Screenshot proofs from the retired QR flow — kept read-only as payment history. */
    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'payment_id', 'payment_id');
    }

    public function isPaid(): bool
    {
        return $this->status === 'verified';
    }
}
