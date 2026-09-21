<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'reference', 'showtime_id', 'customer_name', 'customer_email', 'customer_phone',
        'status', 'payment_status', 'total_amount', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'total_amount' => 'float',
    ];

    /** URLs use the reference (e.g. CZDF5HHWIW) instead of the numeric id. */
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seats()
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function seatLabels(): array
    {
        $labels = $this->seats->pluck('seat_label')->all();
        usort($labels, 'strnatcmp');

        return $labels;
    }

    public static function newReference(): string
    {
        do {
            $reference = Str::upper(Str::random(10));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /** Free the seats of every hold whose timer has run out. */
    public static function releaseExpired(): void
    {
        $ids = static::where('status', 'held')->where('expires_at', '<', now())->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        BookingSeat::whereIn('booking_id', $ids)->delete();
        static::whereIn('id', $ids)->update(['status' => 'expired']);
    }
}
