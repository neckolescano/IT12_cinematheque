<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\Director;
use App\Models\Movie;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** One Add Screening workflow: the film's genre, director and actors are entered on the screening form. */
class ScreeningFilmDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'film', 'film_title' => 'Himala', 'runtime_minutes' => 124, 'rating' => 'R-13', 'release_year' => 1982,
            'genres' => ['Drama'], 'directors' => 'Ishmael Bernal', 'actors' => 'Nora Aunor, Veronica Palileo',
            'event_title' => '', 'event_date' => today()->addDay()->format('Y-m-d'), 'start_time' => '17:00', 'end_time' => '',
            'type' => 'free',
        ], $overrides);
    }

    public function test_adding_a_screening_saves_the_film_and_its_credits_in_one_step(): void
    {
        $this->actingAs(User::factory()->create())->post(route('staff.screenings.store'), $this->form())->assertSessionHasNoErrors();

        $screening = Screening::with('movie.directors', 'movie.actors', 'movie.genres')->firstOrFail();
        $this->assertSame('Himala', $screening->event_title);
        $this->assertSame('19:04', substr($screening->end_time, 0, 5));
        $this->assertSame('Himala', $screening->movie->title);
        $this->assertSame('R-13', $screening->movie->rating);
        $this->assertSame('Ishmael Bernal', $screening->movie->directorNames());
        $this->assertSame('Nora Aunor, Veronica Palileo', $screening->movie->castNames());
        $this->assertSame(['Drama'], $screening->movie->genres->pluck('genre_name')->all());
    }

    public function test_a_known_title_reuses_the_film_and_blank_fields_keep_its_details(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('staff.screenings.store'), $this->form());

        // Second screening of the same film: only the title, typed in a different case.
        $this->actingAs($staff)->post(route('staff.screenings.store'), $this->form([
            'film_title' => 'himala', 'runtime_minutes' => '', 'rating' => '', 'release_year' => '',
            'genres' => [], 'directors' => '', 'actors' => '', 'start_time' => '13:00',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Movie::count());
        $movie = Movie::with('actors')->firstOrFail();
        $this->assertSame(124, $movie->runtime_minutes);
        $this->assertCount(2, $movie->actors);
        $this->assertSame('15:04', substr(Screening::orderByDesc('screening_id')->first()->end_time, 0, 5)); // runtime from the catalog
    }

    public function test_a_special_programme_has_no_film_and_needs_its_own_title(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('staff.screenings.store'), $this->form(['kind' => 'programme', 'event_title' => '', 'end_time' => '19:00']))
            ->assertSessionHasErrors('event_title');

        $this->actingAs($staff)->post(route('staff.screenings.store'), $this->form(['kind' => 'programme', 'event_title' => 'Shorts Night', 'end_time' => '19:00']))
            ->assertSessionHasNoErrors();
        $this->assertNull(Screening::firstOrFail()->movie_id);
        $this->assertSame(0, Movie::count());
    }

    public function test_people_no_longer_credited_are_cleaned_up_and_there_are_no_separate_admin_pages(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('staff.movies.store'), ['title' => 'A', 'directors' => 'Lav Diaz', 'actors' => 'John Lloyd Cruz']);
        $movie = Movie::firstOrFail();

        $this->actingAs($staff)->put(route('staff.movies.update', $movie), ['title' => 'A', 'directors' => 'Brillante Mendoza', 'actors' => '']);

        $this->assertSame(['Brillante Mendoza'], Director::all()->map->full_name->all());
        $this->assertSame(0, Actor::count());
        $this->actingAs($staff)->get('/ccdadmin/actors')->assertNotFound();
        $this->actingAs($staff)->get('/ccdadmin/genres')->assertNotFound();
    }
}
