<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screening extends Model
{
    use HasFactory;

    public const TYPES = ['free', 'paid'];

    protected $primaryKey = 'screening_id';

    protected $fillable = [
        'event_title', 'movie_id', 'event_date', 'start_time', 'end_time',
        'type', 'price', 'total_seats', 'created_by',
    ];

    protected $attributes = [
        'type' => 'free',
        'total_seats' => 100,
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'price' => 'decimal:2',
            'total_seats' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'movie_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'screening_id', 'screening_id');
    }

    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class, 'screening_id', 'screening_id');
    }

    public function isPaid(): bool
    {
        return $this->type === 'paid';
    }

    /** total_seats minus every seat row locked against this screening. */
    public function availableSeatCount(): int
    {
        return max(0, $this->total_seats - $this->reservationSeats()->count());
    }

    /** @return array<int> seat_ids already held for this screening */
    public function takenSeatIds(): array
    {
        return $this->reservationSeats()->pluck('seat_id')->map(fn ($id) => (int) $id)->all();
    }
}
