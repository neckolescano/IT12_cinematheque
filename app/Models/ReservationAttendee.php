<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationAttendee extends Model
{
    public const SEXES = ['M', 'F'];

    protected $primaryKey = 'reservation_attendee_id';

    public $timestamps = false;

    protected $fillable = [
        'reservation_seat_id', 'is_lead_reserver', 'first_name', 'middle_name', 'last_name',
        'age', 'sex', 'company_school', 'contact_no', 'email', 'senior_card_no', 'pwd_id_no', 'pwd_indicator',
    ];

    protected $attributes = [
        'is_lead_reserver' => false,
        'pwd_indicator' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_lead_reserver' => 'boolean',
            'pwd_indicator' => 'boolean',
            'age' => 'integer',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function reservationSeat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class, 'reservation_seat_id', 'reservation_seat_id');
    }
}
