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
 *
 * One seat = one ticket: unit_price is the screening price when booked, discount_type is pwd or
 * senior when that gives a discount, amount_due is what the ticket costs. A new seat takes its
 * screening's price unless the booking sets one.
 */
class ReservationSeat extends Model
{
    protected $primaryKey = 'reservation_seat_id';

    public $timestamps = false;

    public const DISCOUNT_TYPES = ['none', 'pwd', 'senior'];

    /** PWD and Senior Citizen tickets for paid screenings are 20% off (one discount per ticket). */
    public const DISCOUNT_RATE = 0.20;

    /**
     * The price of one ticket for this attendee: the screening price, 20% off with a PWD ID or a
     * Senior Citizen ID (PWD counted first when both are given). Free screenings cost nothing.
     *
     * @param  array<string, mixed>  $attendee  the attendee's form fields (pwd_id_no, senior_card_no)
     * @return array{unit_price: float, discount_type: string, amount_due: float}
     */
    public static function priceFor(Screening $screening, array $attendee): array
    {
        $type = match (true) {
            filled($attendee['pwd_id_no'] ?? null) => 'pwd',
            filled($attendee['senior_card_no'] ?? null) => 'senior',
            default => 'none',
        };
        $unit = $screening->isPaid() ? (float) $screening->price : 0.0;

        return [
            'unit_price' => $unit,
            'discount_type' => $type,
            'amount_due' => round($type === 'none' ? $unit : $unit * (1 - self::DISCOUNT_RATE), 2),
        ];
    }

    protected $fillable = ['reservation_id', 'screening_id', 'seat_id', 'unit_price', 'discount_type', 'amount_due'];

    protected $attributes = [
        'discount_type' => 'none',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReservationSeat $seat) {
            if ($seat->unit_price === null) {
                $screening = Screening::find($seat->screening_id);
                $seat->unit_price = $screening?->isPaid() ? (float) $screening->price : 0;
            }
            $seat->amount_due ??= $seat->unit_price;
        });
    }

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'unit_price' => 'decimal:2',
            'amount_due' => 'decimal:2',
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
