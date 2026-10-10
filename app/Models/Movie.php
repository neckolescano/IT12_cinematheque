<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class Movie extends Model
{
    use HasFactory;

    protected $primaryKey = 'movie_id';

    public $timestamps = false;

    protected $fillable = ['title', 'runtime_minutes', 'rating', 'release_year', 'synopsis', 'logline', 'curator_note', 'trailer_url', 'poster_path', 'status'];

    /** draft = only staff see it; published = on the customer site. */
    public const STATUSES = ['draft', 'published'];

    protected $attributes = [
        'status' => 'published',
    ];

    /** MTRCB content ratings offered in the movie form. */
    public const RATINGS = ['G', 'PG', 'PG-13', 'R-13', 'R-16', 'R-18'];

    /** Folder on the "public" disk that holds uploaded posters. */
    public const POSTER_DIR = 'posters';

    /**
     * Fixed genre options shown as chips on the screening and movie forms, plus "Other" with
     * a typed genre. There is no separate genre admin. (Stored in the genres table + movie_genre.)
     */
    public const GENRES = [
        'Drama', 'Comedy', 'Romance', 'Action', 'Thriller', 'Horror',
        'Documentary', 'Animation', 'Musical', 'Historical', 'Experimental', 'Short Film',
    ];

    /** Validation for the film details shared by the screening form and the movie form. */
    public static function detailRules(): array
    {
        return [
            'runtime_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'rating' => ['nullable', Rule::in(self::RATINGS)],
            'release_year' => ['nullable', 'integer', 'min:1888', 'max:'.(now()->year + 5)],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['string', Rule::in(self::GENRES)],
            'genre_other_on' => ['nullable', 'boolean'],
            'genre_other' => ['nullable', 'required_if_accepted:genre_other_on', 'string', 'max:100'],
            'directors' => ['nullable', 'string', 'max:255'],
            'actors' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The chosen chips plus any typed "Other" genres (comma-separated). A typed genre that is
     * already on the list (any case) is stored under the listed name.
     */
    public static function genreList(array $data): array
    {
        $listed = collect(self::GENRES)->keyBy(fn ($g) => mb_strtolower($g));

        return collect($data['genres'] ?? [])
            ->merge(collect(self::parseNames($data['genre_other'] ?? null))->map(fn ($g) => $listed[mb_strtolower($g)] ?? mb_substr($g, 0, 50)))
            ->unique(fn ($g) => mb_strtolower($g))->values()->all();
    }

    /** Genres that aren't on the fixed list, as the "Other" text box shows them. */
    public function customGenres(): string
    {
        $listed = array_map('mb_strtolower', self::GENRES);

        return $this->genres->pluck('genre_name')->reject(fn ($g) => in_array(mb_strtolower($g), $listed, true))->join(', ');
    }

    /** "Lav Diaz, Brillante Mendoza" → ['Lav Diaz', 'Brillante Mendoza'] (commas, semicolons or new lines). */
    public static function parseNames(?string $text): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $text))
            ->map(fn ($name) => trim(preg_replace('/\s+/', ' ', $name)))
            ->filter()->unique(fn ($n) => mb_strtolower($n))->values()->all();
    }

    /**
     * Save the typed credits and chosen genres. Names typed on the form are matched to
     * existing director/actor records or created behind the scenes, so staff never manage
     * people separately. A person no longer credited on any film is removed.
     */
    public function syncDetails(array $genres, ?string $directors, ?string $actors): void
    {
        $this->genres()->sync(collect($genres)->map(fn ($g) => Genre::firstOrCreate(['genre_name' => $g])->genre_id));
        $this->directors()->sync(collect(self::parseNames($directors))->map(fn ($n) => self::person(Director::class, $n)->director_id));
        $this->actors()->sync(collect(self::parseNames($actors))->map(fn ($n) => self::person(Actor::class, $n)->actor_id));

        Director::doesntHave('movies')->delete();
        Actor::doesntHave('movies')->delete();
    }

    /** "Lamberto V. Avellana" → first "Lamberto V.", last "Avellana"; a single word is the last name. */
    private static function person(string $model, string $name): Model
    {
        $parts = explode(' ', $name);
        $last = array_pop($parts);

        // Columns are 50 characters each; first_name is required, so a one-word name keeps it empty.
        return $model::firstOrCreate(['first_name' => mb_substr(implode(' ', $parts), 0, 50), 'last_name' => mb_substr($last, 0, 50)]);
    }

    /** Credits as the comma-separated text the forms show. */
    public function directorNames(): string
    {
        return $this->directors->map(fn ($d) => $d->full_name)->join(', ');
    }

    public function castNames(): string
    {
        return $this->actors->map(fn ($a) => $a->full_name)->join(', ');
    }

    protected function casts(): array
    {
        return [
            'runtime_minutes' => 'integer',
            'release_year' => 'integer',
        ];
    }

    /** Public URL of the poster image, or null (the UI then shows the generated tile). */
    public function posterUrl(): ?string
    {
        // asset() follows the host the page was opened on, unlike Storage::url() (APP_URL).
        return $this->poster_path ? asset('storage/'.$this->poster_path) : null;
    }

    /** Player URL for the trailer modal (YouTube or Vimeo), or null: other links just open in a new tab. */
    public function trailerEmbedUrl(): ?string
    {
        $url = (string) $this->trailer_url;

        return match (true) {
            (bool) preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $url, $m) => 'https://www.youtube-nocookie.com/embed/'.$m[1],
            (bool) preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m) => 'https://player.vimeo.com/video/'.$m[1],
            default => null,
        };
    }

    /**
     * The banner's one line: the logline, or the synopsis up to its first full stop, cut at 120 characters.
     */
    public function loglineText(): ?string
    {
        if (filled($this->logline)) {
            return $this->logline;
        }
        $synopsis = trim((string) $this->synopsis);
        if ($synopsis === '') {
            return null;
        }
        $first = preg_match('/^.+?[.!?](?=\s|$)/su', $synopsis, $m) ? $m[0] : $synopsis;

        return Str::limit(preg_replace('/\s+/', ' ', $first), 120);
    }

    /** Films customers may see (drafts are staff-only). */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class, 'movie_id', 'movie_id');
    }

    /** A film can be in more than one program (a festival may screen it again). */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'movie_program', 'movie_id', 'program_id');
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
