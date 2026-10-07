<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    /** The venue has exactly this many seats (rows A–J × 1–12). Fixed: there is no seat admin. */
    public const CAPACITY = 120;

    protected $primaryKey = 'seat_id';

    public $timestamps = false;

    protected $fillable = ['seat_label', 'section'];

    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class, 'seat_id', 'seat_id');
    }
}
