<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Screening extends Model
{
    use HasFactory;

    public const TYPES = ['free', 'paid'];

    /** draft = only staff see it; published = on the customer site. */
    public const STATUSES = ['draft', 'published'];

    protected $primaryKey = 'screening_id';

    protected $fillable = [
        'event_title', 'movie_id', 'films_count', 'program_id', 'event_date', 'start_time', 'end_time',
        'type', 'price', 'total_seats', 'status', 'partner', 'agency_type', 'notes', 'created_by',
    ];

    protected $attributes = [
        'type' => 'free',
        'total_seats' => Seat::CAPACITY,
        'status' => 'published',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'films_count' => 'integer',
            'price' => 'decimal:2',
            'total_seats' => 'integer',
        ];
    }

    /** Screenings customers may see and book: published, and so is their film. */
    public function scopePublished(Builder $query): void
    {
        $query->where('screenings.status', 'published')->whereHas('movie', fn ($m) => $m->published());
    }

    /** Hidden from customers: the screening or its film is still a draft. */
    public function isDraft(): bool
    {
        return $this->status === 'draft' || $this->movie?->isDraft();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'movie_id', 'movie_id');
    }

    /** The program this screening is reported under (a film may be in several programs). */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'screening_id', 'screening_id');
    }

    public function reservationSeats(): HasMany
    {
        return $this->hasMany(ReservationSeat::class, 'screening_id', 'screening_id');
    }

    /** Seat rows still held (cancelled reservations release theirs). */
    public function heldSeats(): HasMany
    {
        return $this->reservationSeats()->held();
    }

    /** Door check-in opens this many minutes before the start… */
    public const CHECKIN_OPENS_MINUTES = 20;

    /** …and closes this many minutes after the end (late arrivals, corrections right after the show). */
    public const CHECKIN_CLOSES_MINUTES = 60;

    public function startsAt(): Carbon
    {
        return $this->event_date->copy()->setTimeFromTimeString((string) $this->start_time);
    }

    public function endsAt(): Carbon
    {
        return $this->event_date->copy()->setTimeFromTimeString((string) $this->end_time);
    }

    public function checkInOpensAt(): Carbon
    {
        return $this->startsAt()->subMinutes(self::CHECKIN_OPENS_MINUTES);
    }

    public function checkInClosesAt(): Carbon
    {
        return $this->endsAt()->addMinutes(self::CHECKIN_CLOSES_MINUTES);
    }

    /** 'not_yet', 'open' or 'closed', for the door (Manila time). */
    public function checkInState(): string
    {
        return match (true) {
            now()->lt($this->checkInOpensAt()) => 'not_yet',
            now()->gt($this->checkInClosesAt()) => 'closed',
            default => 'open',
        };
    }

    /** Staff admit (or undo, or note) only while check-in is open; the Super Admin can always correct a roster. */
    public function allowsCheckInBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->checkInState() === 'open';
    }

    public function isPaid(): bool
    {
        return $this->type === 'paid';
    }

    /** total_seats minus every seat still held for this screening. */
    public function availableSeatCount(): int
    {
        return max(0, $this->total_seats - $this->heldSeats()->count());
    }

    /** @return array<int> seat_ids currently held for this screening */
    public function takenSeatIds(): array
    {
        return $this->heldSeats()->pluck('seat_id')->map(fn ($id) => (int) $id)->all();
    }
}
