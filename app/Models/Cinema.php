<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cinema extends Model
{
    protected $fillable = [
        'name', 'seat_rows', 'seats_per_row', 'ticket_price',
        'requires_payment', 'payment_qr_path',
    ];

    protected $casts = [
        'requires_payment' => 'boolean',
        'ticket_price' => 'float',
    ];

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public function getTotalSeatsAttribute(): int
    {
        return $this->seat_rows * $this->seats_per_row;
    }

    /** ['A', 'B', ... 'J'] */
    public function rowLetters(): array
    {
        return array_map('chr', range(65, 64 + $this->seat_rows));
    }

    public function isValidSeat(string $label): bool
    {
        if (!preg_match('/^([A-Z])(\d{1,2})$/', $label, $m)) {
            return false;
        }
        $row = ord($m[1]) - 64;
        $number = (int) $m[2];

        return $row >= 1 && $row <= $this->seat_rows
            && $number >= 1 && $number <= $this->seats_per_row;
    }

    public function getPaymentQrUrlAttribute(): ?string
    {
        return $this->payment_qr_path ? asset('storage/' . $this->payment_qr_path) : null;
    }

    /** Only cinemas flagged as paid AND with a price show the QR step. */
    public function chargesCustomers(): bool
    {
        return $this->requires_payment && $this->ticket_price > 0;
    }
}
