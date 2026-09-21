<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Movie extends Model
{
    protected $fillable = [
        'title', 'synopsis', 'genre', 'language', 'duration_minutes', 'age_rating',
        'rating', 'poster_path', 'backdrop_path', 'status', 'release_date', 'is_featured',
    ];

    protected $casts = [
        'release_date' => 'date',
        'is_featured' => 'boolean',
        'rating' => 'float',
    ];

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public function upcomingShowtimes()
    {
        return $this->hasMany(Showtime::class)->where('starts_at', '>=', now());
    }

    public function scopeNowShowing($query)
    {
        return $query->where('status', 'now_showing');
    }

    public function scopeComingSoon($query)
    {
        return $query->where('status', 'coming_soon');
    }

    public function getDurationLabelAttribute(): string
    {
        return intdiv($this->duration_minutes, 60) . 'h ' . ($this->duration_minutes % 60) . 'min';
    }

    public function getPosterUrlAttribute(): string
    {
        return $this->resolveImage($this->poster_path) ?? asset('images/poster-placeholder.svg');
    }

    public function getBackdropUrlAttribute(): string
    {
        return $this->resolveImage($this->backdrop_path) ?? $this->poster_url;
    }

    /** ["Cinema 1", "Cinema 2"] built from upcoming showtimes (eager load upcomingShowtimes.cinema). */
    public function getCinemaNamesAttribute(): array
    {
        return $this->upcomingShowtimes->pluck('cinema.name')->filter()->unique()->values()->all();
    }

    private function resolveImage(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return Storage::disk('public')->exists($path) ? asset('storage/' . $path) : null;
    }
}
