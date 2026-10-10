<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff account. Being a row here *is* being staff.
 * `role` gates access: one super_admin (held by FDCP Manila; locks and unlocks reports, manages
 * staff) and admins (daily operations). `position` (AVT/PDO) is a job title only.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const POSITIONS = ['AVT', 'PDO'];

    public const ROLES = ['super_admin', 'admin'];

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    /** The users table has no remember_token column, so "remember me" is disabled. */
    protected $rememberTokenName = '';

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'email', 'password', 'position', 'role', 'is_active',
    ];

    protected $hidden = ['password'];

    protected $attributes = [
        'is_active' => true,
        'role' => 'admin',
    ];

    /** There is exactly one Super Admin account (client decision): a second one is refused. */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->role === 'super_admin' && $user->isDirty('role')
                && static::where('role', 'super_admin')->whereKeyNot($user->getKey())->exists()) {
                throw new \DomainException('There is already a Super Admin account.');
            }
        });
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

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

    public function checkIns(): HasMany
    {
        return $this->hasMany(Attendance::class, 'checked_in_by', 'user_id');
    }
}
