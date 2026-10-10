<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A program (Cinemalaya, Filipino Classics…): a free-text tag typed on the film form, and the unit of the
 * Manila report. There is no Programs page; this table is the tag registry behind the autocomplete, so
 * tags that differ only in case or spacing are one program (name_key). A film can carry several tags;
 * each screening is reported under one of them.
 */
class Program extends Model
{
    use HasFactory;

    protected $primaryKey = 'program_id';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Program $program) {
            $program->name = self::clean($program->name);
            $program->name_key = mb_strtolower($program->name);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** "  Cinemalaya   2026 " → "Cinemalaya 2026" */
    public static function clean(?string $name): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', (string) $name)), 0, 100);
    }

    /** The program for a typed tag: an existing one matched ignoring case and spacing, or a new one. */
    public static function fromTag(string $name): self
    {
        $name = self::clean($name);

        return self::firstOrCreate(['name_key' => mb_strtolower($name)], ['name' => $name]);
    }

    /** Tags no film, screening or report uses any more. */
    public static function pruneUnused(): void
    {
        self::doesntHave('movies')->doesntHave('screenings')->doesntHave('report')->delete();
    }

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_program', 'program_id', 'movie_id');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class, 'program_id', 'program_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(Report::class, 'program_id', 'program_id');
    }
}
