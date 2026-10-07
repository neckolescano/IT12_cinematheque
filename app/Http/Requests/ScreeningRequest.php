<?php

namespace App\Http\Requests;

use App\Models\Movie;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The one Add/Edit Screening form: the screening itself plus, for a film, the film's details
 * (title, runtime, rating, year, genres, director, actors) typed in the same place.
 *
 *   kind = film        film_title + details → saved to the film catalog (reused by title)
 *   kind = programme   a festival block, talk or shorts selection with no single film
 */
class ScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $screening = $this->route('screening');

        return $screening
            ? $this->user()->can('update', $screening)
            : $this->user()->can('create', Screening::class);
    }

    /**
     * Fill what the film already tells us: the event title defaults to the film title, and the
     * end time to start + runtime (typed now, or already in the catalog).
     */
    protected function prepareForValidation(): void
    {
        // Older callers send a catalog movie_id instead of a typed title.
        if (blank($this->input('film_title')) && $this->filled('movie_id') && ($m = Movie::find($this->input('movie_id')))) {
            $this->merge(['film_title' => $m->title]);
        }

        $kind = $this->input('kind') ?: (filled($this->input('film_title')) ? 'film' : 'programme');
        $merge = ['kind' => $kind];

        if ($kind === 'film' && filled($this->input('film_title'))) {
            $title = trim(preg_replace('/\s+/', ' ', $this->input('film_title')));
            $merge['film_title'] = $title;

            if (blank($this->input('event_title'))) {
                $merge['event_title'] = $title;
            }

            $runtime = (int) $this->input('runtime_minutes') ?: (int) $this->existingFilm($title)?->runtime_minutes;
            $start = (string) $this->input('start_time');
            if (blank($this->input('end_time')) && $runtime > 0 && preg_match('/^\d{2}:\d{2}$/', $start)) {
                $end = Carbon::createFromFormat('H:i', $start)->addMinutes($runtime);
                // A film running past midnight can't be stored as one day's times; leave it for staff.
                if ($end->isSameDay(Carbon::createFromFormat('H:i', $start))) {
                    $merge['end_time'] = $end->format('H:i');
                }
            }
        }

        $this->merge($merge);
    }

    public function messages(): array
    {
        return [
            'film_title.required_if' => 'Enter the film title, or choose “Special programme”.',
            'event_title.required' => 'Enter a title for this programme.',
            'end_time.required' => 'Enter the end time (it fills in automatically when the runtime is known).',
            'genres.*.in' => 'Choose genres from the list.',
            'genre_other.required_if_accepted' => 'Enter the other genre, or untick “Other”.',
        ];
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['film', 'programme'])],
            'film_title' => ['nullable', 'required_if:kind,film', 'string', 'max:150'],
            ...Movie::detailRules(),
            'event_title' => ['required', 'string', 'max:150'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(Screening::TYPES)],
            'price' => ['nullable', 'required_if:type,paid', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }


    /** The screening's own columns. Free screenings carry no price. */
    public function screeningData(): array
    {
        $data = collect($this->validated())
            ->only(['event_title', 'event_date', 'start_time', 'end_time', 'type', 'price'])->all();
        $data['total_seats'] = Seat::CAPACITY; // fixed venue capacity, not entered per screening
        if ($data['type'] === 'free') {
            $data['price'] = null;
        }

        return $data;
    }

    /** The film's details as typed, or null for a special programme. */
    public function filmData(): ?array
    {
        $data = $this->validated();
        if ($data['kind'] !== 'film') {
            return null;
        }

        return [
            'title' => $data['film_title'],
            'runtime_minutes' => $data['runtime_minutes'] ?? null,
            'rating' => $data['rating'] ?? null,
            'release_year' => $data['release_year'] ?? null,
            'synopsis' => $data['synopsis'] ?? null,
            'genres' => Movie::genreList($data),
            'directors' => $data['directors'] ?? null,
            'actors' => $data['actors'] ?? null,
        ];
    }

    public function existingFilm(string $title): ?Movie
    {
        return Movie::whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->first();
    }
}
