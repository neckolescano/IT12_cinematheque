<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentQrCode extends Model
{
    protected $primaryKey = 'qr_code_id';

    public $timestamps = false;

    protected $fillable = ['qr_image', 'is_active', 'uploaded_by', 'uploaded_at'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'uploaded_at' => 'datetime',
        ];
    }

    /** The QR currently shown on the Payment Screen. */
    public static function current(): ?self
    {
        return static::where('is_active', true)->latest('uploaded_at')->first();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
