<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'confirmed', 'cancelled'];

    protected $primaryKey = 'reservation_id';

    public $timestamps = false;

    protected $fillable = [
        'screening_id', 'booking_reference', 'status', 'reservation_datetime',
        'lead_first_name', 'lead_middle_name', 'lead_last_name', 'lead_contact_no', 'lead_email',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reservation_datetime' => 'datetime',
        ];
    }

    public static function generateBookingReference(): string
    {
        do {
            $reference = 'CCD-'.Str::upper(Str::random(8));
        } while (static::where('booking_reference', $reference)->exists());

        return $reference;
    }

    public function getLeadFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->lead_first_name, $this->lead_middle_name, $this->lead_last_name])));
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class, 'screening_id', 'screening_id');
    }

    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class, 'reservation_id', 'reservation_id');
    }

    public function attendees(): HasManyThrough
    {
        return $this->hasManyThrough(
            ReservationAttendee::class, ReservationSeat::class,
            'reservation_id', 'reservation_seat_id', 'reservation_id', 'reservation_seat_id'
        );
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'reservation_id', 'reservation_id');
    }
}
