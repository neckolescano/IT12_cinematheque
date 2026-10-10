<?php

namespace App\Http\Requests;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Screening;
use App\Models\Seat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The one Add/Edit Screening form: the screening itself plus the film's details (title, runtime,
 * rating, year, genres, director, actors) typed in the same place. The film is saved to the catalog
 * (reused by title). Every screening has a film and a program (revision phase 3): a shorts block or a
 * talk is entered as its own film record, with "No. of films" for the Manila report.
 *
 * intent = draft    save, hidden from customers
 *          review   save, then preview it as customers will see it (a new screening stays a draft)
 *          publish  save and show it on the customer site
 *
 * program  the program tag it is reported under, typed (matched ignoring case, created if new); older
 *          callers may send program_id.
 * Batch (new screenings only): repeat = daily|weekly until repeat_until, and/or more[] = extra
 * {date, start} rows. Every showtime gets the first one's length; none may overlap another screening.
 */
class ScreeningRequest extends FormRequest
{
    public const INTENTS = ['draft', 'review', 'publish'];

    /** At most this many screenings from one form. */
    public const MAX_SHOWTIMES = 30;

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

        $merge = [
            'intent' => $this->input('intent') ?: 'publish',
            'films_count' => $this->input('films_count') ?: 1,
            'repeat' => $this->input('repeat') ?: 'none',
            // Empty "more showtimes" rows are ignored.
            'more' => array_values(array_filter((array) $this->input('more', []), fn ($row) => is_array($row) && (filled($row['date'] ?? null) || filled($row['start'] ?? null)))),
        ];

        if (filled($this->input('film_title'))) {
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
            'film_title.required' => 'Enter the film title. For a shorts block or a talk, enter its title and the number of films.',
            'program.required_without' => 'Enter the program this screening is reported under.',
            'repeat_until.required_unless' => 'Choose the last date to repeat until.',
            'more.*.date.required' => 'Enter a date for each extra showtime.',
            'more.*.start.required' => 'Enter a start time for each extra showtime.',
            'end_time.required' => 'Enter the end time (it fills in automatically when the runtime is known).',
            'genres.*.in' => 'Choose genres from the list.',
            'genre_other.required_if_accepted' => 'Enter the other genre, or untick “Other”.',
        ];
    }

    public function rules(): array
    {
        return [
            'intent' => ['required', Rule::in(self::INTENTS)],
            'program' => ['nullable', 'required_without:program_id', 'string', 'max:100'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'program_id')],
            'film_title' => ['required', 'string', 'max:150'],
            ...Movie::detailRules(),
            'films_count' => ['required', 'integer', 'min:1', 'max:50'],
            'event_title' => ['required', 'string', 'max:150'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(Screening::TYPES)],
            'price' => ['nullable', 'required_if:type,paid', 'numeric', 'min:0', 'max:999999.99'],
            // Manila report columns (optional; they can also be filled in on the report).
            'partner' => ['nullable', 'string', 'max:150'],
            'agency_type' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Batch scheduling (new screenings only)
            'repeat' => ['required', Rule::in(['none', 'daily', 'weekly'])],
            'repeat_until' => ['nullable', 'required_unless:repeat,none', 'date', 'after_or_equal:event_date'],
            'more' => ['array', 'max:'.self::MAX_SHOWTIMES],
            'more.*.date' => ['required', 'date'],
            'more.*.start' => ['required', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $times = $this->showtimes();
                if (count($times) > self::MAX_SHOWTIMES) {
                    $validator->errors()->add('repeat_until', 'That makes '.count($times).' screenings; the most at once is '.self::MAX_SHOWTIMES.'.');

                    return;
                }
                // One hall: no two screenings may overlap, including the new ones with each other.
                $editing = $this->route('screening');
                foreach ($times as $i => [$date, $start, $end]) {
                    foreach (array_slice($times, 0, $i) as [$d2, $s2, $e2]) {
                        if ($d2 === $date && $s2 < $end && $e2 > $start) {
                            $validator->errors()->add('more', 'Two of the new showtimes overlap on '.Carbon::parse($date)->format('M j').'.');

                            return;
                        }
                    }
                    $clash = Screening::whereDate('event_date', $date)
                        ->where('start_time', '<', $end.':00')->where('end_time', '>', $start.':00')
                        ->when($editing, fn ($q) => $q->whereKeyNot($editing->getKey()))
                        ->first();
                    if ($clash) {
                        $validator->errors()->add($i === 0 ? 'start_time' : 'more', 'The hall is booked then: “'.$clash->event_title.'” on '
                            .$clash->event_date->format('M j').', '.Carbon::parse($clash->start_time)->format('g:i A').'–'.Carbon::parse($clash->end_time)->format('g:i A').'.');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * Every showtime this form creates, as [Y-m-d, H:i start, H:i end]: the first one, its daily/weekly
     * repeats, then the extra rows. Each lasts as long as the first. Editing changes just the one screening.
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public function showtimes(): array
    {
        $first = Carbon::parse($this->input('event_date'));
        $start = (string) $this->input('start_time');
        $length = Carbon::createFromFormat('H:i', $start)->diffInMinutes(Carbon::createFromFormat('H:i', (string) $this->input('end_time')));
        $end = fn (string $s) => Carbon::createFromFormat('H:i', $s)->addMinutes($length)->format('H:i');

        $times = [[$first->toDateString(), $start, (string) $this->input('end_time')]];
        if ($this->route('screening')) {
            return $times;
        }

        if ($this->input('repeat') !== 'none' && $this->filled('repeat_until')) {
            $until = Carbon::parse($this->input('repeat_until'));
            $step = $this->input('repeat') === 'weekly' ? 7 : 1;
            for ($d = $first->copy()->addDays($step); $d->lte($until) && count($times) <= self::MAX_SHOWTIMES; $d->addDays($step)) {
                $times[] = [$d->toDateString(), $start, (string) $this->input('end_time')];
            }
        }
        foreach ((array) $this->input('more', []) as $row) {
            $times[] = [Carbon::parse($row['date'])->toDateString(), $row['start'], $end($row['start'])];
        }

        // The same showtime twice is one screening.
        return array_values(array_unique($times, SORT_REGULAR));
    }

    /** The program typed (a tag) or chosen (program_id). Creates the tag if it is new. */
    public function programId(): int
    {
        return filled($this->validated('program'))
            ? Program::fromTag($this->validated('program'))->program_id
            : (int) $this->validated('program_id');
    }

    public function attributes(): array
    {
        return ['program_id' => 'program', 'films_count' => 'number of films', 'agency_type' => 'type of agency', 'repeat_until' => 'repeat until', 'more.*.date' => 'showtime date', 'more.*.start' => 'showtime start'];
    }

    /** The screening's own columns. Free screenings carry no price. */
    public function screeningData(): array
    {
        $data = collect($this->validated())
            ->only(['event_title', 'films_count', 'event_date', 'start_time', 'end_time', 'type', 'price', 'partner', 'agency_type', 'notes'])->all();
        $data['program_id'] = $this->programId();
        $data['total_seats'] = Seat::CAPACITY; // fixed venue capacity, not entered per screening
        if ($data['type'] === 'free') {
            $data['price'] = null;
        }

        return $data;
    }

    /** The film's details as typed. */
    public function filmData(): array
    {
        $data = $this->validated();

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

    public function intent(): string
    {
        return $this->validated('intent');
    }

    public function existingFilm(string $title): ?Movie
    {
        return Movie::whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->first();
    }
}
