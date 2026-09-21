<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Showtime extends Model
{
    protected $fillable = ['movie_id', 'cinema_id', 'starts_at'];

    protected $casts = ['starts_at' => 'datetime'];

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function bookingSeats()
    {
        return $this->hasMany(BookingSeat::class);
    }

    /**
     * Use ->withCount('bookingSeats') on the query to avoid one query per showtime.
     * Call Booking::releaseExpired() first so expired holds are not counted.
     */
    public function getSeatsLeftAttribute(): int
    {
        $sold = $this->booking_seats_count ?? $this->bookingSeats()->count();

        return max(0, $this->cinema->total_seats - $sold);
    }
}
