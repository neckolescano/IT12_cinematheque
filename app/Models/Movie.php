<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    use HasFactory;

    protected $primaryKey = 'movie_id';

    public $timestamps = false;

    protected $fillable = ['title', 'runtime_minutes', 'rating', 'release_year', 'synopsis'];

    protected function casts(): array
    {
        return [
            'runtime_minutes' => 'integer',
            'release_year' => 'integer',
        ];
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class, 'movie_id', 'movie_id');
    }

    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'movie_actor', 'movie_id', 'actor_id');
    }

    public function directors(): BelongsToMany
    {
        return $this->belongsToMany(Director::class, 'movie_director', 'movie_id', 'director_id');
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre', 'movie_id', 'genre_id');
    }
}
