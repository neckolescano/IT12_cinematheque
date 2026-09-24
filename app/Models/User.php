<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff account (AVT or PDO). Being a row here *is* being staff.
 * `position` is descriptive only and never gates access.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const POSITIONS = ['AVT', 'PDO'];

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    /** The users table has no remember_token column, so "remember me" is disabled. */
    protected $rememberTokenName = '';

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'email', 'password', 'position', 'is_active',
    ];

    protected $hidden = ['password'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class, 'created_by', 'user_id');
    }

    public function reviewedPaymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class, 'reviewed_by', 'user_id');
    }

    public function uploadedQrCodes(): HasMany
    {
        return $this->hasMany(PaymentQrCode::class, 'uploaded_by', 'user_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(Attendance::class, 'checked_in_by', 'user_id');
    }
}
