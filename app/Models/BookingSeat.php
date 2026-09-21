<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingSeat extends Model
{
    public $timestamps = false;

    protected $fillable = ['booking_id', 'showtime_id', 'seat_label'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
