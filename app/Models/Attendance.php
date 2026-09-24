<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $primaryKey = 'attendance_id';

    public $timestamps = false;

    protected $fillable = ['reservation_seat_id', 'control_number', 'remarks', 'checked_in_at', 'checked_in_by'];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function reservationSeat(): BelongsTo
    {
        return $this->belongsTo(ReservationSeat::class, 'reservation_seat_id', 'reservation_seat_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by', 'user_id');
    }
}
