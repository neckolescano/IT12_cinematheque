<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    /**
     * Approved automatically (revision phase 2): a free booking is confirmed on submit; a paid one is
     * awaiting_payment (seats held) until PayMongo reports it paid, then confirmed.
     */
    public const STATUSES = ['awaiting_payment', 'confirmed', 'cancelled'];

    /** staff = cancelled by Cinematheque staff; payment_expired = paid screening not paid in time. */
    public const CANCELLATION_REASONS = ['staff', 'payment_expired'];

    /** Minutes a paid-screening booking holds its seats while waiting for payment. */
    public const PAYMENT_WINDOW_MINUTES = 15;

    protected $primaryKey = 'reservation_id';

    public $timestamps = false;

    protected $fillable = [
        'screening_id', 'booking_reference', 'status', 'cancellation_reason', 'cancelled_at', 'reservation_datetime',
        'lead_first_name', 'lead_middle_name', 'lead_last_name', 'lead_contact_no', 'lead_email',
    ];

    protected $attributes = [
        'status' => 'confirmed',
    ];

    protected function casts(): array
    {
        return [
            'reservation_datetime' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Cancel the booking and release its seats so they can be booked again. The seat rows,
     * attendees and any attendance stay as history. Returns false if it was already cancelled.
     */
    public function cancel(string $reason): bool
    {
        return DB::transaction(function () use ($reason) {
            $locked = static::whereKey($this->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === 'cancelled') {
                $this->setRawAttributes($locked->getAttributes(), true);

                return false;
            }

            $locked->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'cancelled_at' => now()]);
            $locked->reservationSeats()->held()->update(['released_at' => now()]);
            $this->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /** When an unpaid booking for a paid screening stops holding its seats; null otherwise. */
    public function paymentDeadline(): ?Carbon
    {
        return $this->payment ? $this->reservation_datetime->copy()->addMinutes(self::PAYMENT_WINDOW_MINUTES) : null;
    }

    public function wasExpired(): bool
    {
        return $this->status === 'cancelled' && $this->cancellation_reason === 'payment_expired';
    }

    /**
     * One plain-language state for staff lists, combining reservation and payment:
     * [label, tone] where tone is success | warning | error | neutral.
     *
     * @return array{0: string, 1: string}
     */
    public function staffState(): array
    {
        $payment = $this->payment;

        return match (true) {
            $this->status === 'cancelled' && $payment?->isPaid() => ['Refund due', 'error'],
            $this->wasExpired() => ['Expired · not paid', 'neutral'],
            $this->status === 'cancelled' => ['Cancelled', 'neutral'],
            $this->status === 'confirmed' => [$payment ? 'Confirmed · paid' : 'Confirmed', 'success'],
            default => ['Awaiting payment', 'warning'],
        };
    }

    /** UI wording: an unpaid booking that lapsed shows as "expired". */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === 'awaiting_payment' => 'awaiting payment',
            $this->wasExpired() => 'expired',
            default => $this->status,
        };
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
