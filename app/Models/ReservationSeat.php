<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A full model, not a plain pivot: it owns a ReservationAttendee and an Attendance.
 *
 * released_at is set when the reservation is cancelled. The row stays as history, but the
 * seat no longer counts as taken (held_seat_id, a generated column, becomes NULL).
 */
class ReservationSeat extends Model
{
    protected $primaryKey = 'reservation_seat_id';

    public $timestamps = false;

    protected $fillable = ['reservation_id', 'screening_id', 'seat_id'];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    /** Seats still held by their reservation (not released by a cancellation). */
    public function scopeHeld(Builder $query): void
    {
        $query->whereNull('released_at');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class, 'screening_id', 'screening_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class, 'seat_id', 'seat_id');
    }

    public function attendee(): HasOne
    {
        return $this->hasOne(ReservationAttendee::class, 'reservation_seat_id', 'reservation_seat_id');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class, 'reservation_seat_id', 'reservation_seat_id');
    }
}
