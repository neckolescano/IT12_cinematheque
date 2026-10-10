<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision phase 3 (2026-10-11): programs, the poster-tile catalog and film panel, and
 * Save as draft / Review / Publish for films and screenings. Drafts never show to customers.
 */
class RevisionPhase3Test extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        $this->staff = User::factory()->create();
    }

    private function screeningForm(Program $program, array $overrides = []): array
    {
        return array_merge([
            'program_id' => $program->program_id, 'film_title' => 'Himala', 'runtime_minutes' => 124,
            'event_date' => today()->addDay()->format('Y-m-d'), 'start_time' => '17:00', 'type' => 'free',
        ], $overrides);
    }

    public function test_programs_are_free_text_tags_matched_ignoring_case_and_spacing(): void
    {
        $this->actingAs($this->staff)->post(route('staff.movies.store'), ['title' => 'Himala', 'programs' => ['Cinemalaya 2026', '  cinemalaya   2026 ', 'Filipino Classics', '']])
            ->assertSessionHasNoErrors();
        $this->post(route('staff.movies.store'), ['title' => 'Oro, Plata, Mata', 'programs' => ['CINEMALAYA 2026']])->assertSessionHasNoErrors();

        $this->assertSame(['Cinemalaya 2026', 'Filipino Classics'], Program::orderBy('name')->pluck('name')->all());
        $this->assertSame(2, Program::where('name', 'Cinemalaya 2026')->firstOrFail()->movies()->count());

        // No Programs page any more; the old address lands on the catalog. Unused tags disappear.
        $this->get('/ccdadmin/programs')->assertRedirect('/ccdadmin/movies');
        $himala = Movie::where('title', 'Himala')->firstOrFail();
        $this->put(route('staff.movies.update', $himala), ['title' => 'Himala', 'programs' => ['Cinemalaya 2026']]);
        $this->assertFalse(Program::where('name', 'Filipino Classics')->exists());
    }

    public function test_the_screening_form_takes_a_typed_program_and_suggests_the_films_tags(): void
    {
        $movie = Movie::factory()->create(['title' => 'Himala']);
        $movie->programs()->attach(Program::fromTag('Filipino Classics'));

        $this->actingAs($this->staff)->get(route('staff.screenings.create', ['movie' => $movie->movie_id]))
            ->assertOk()->assertSee('value="Filipino Classics"', false)->assertSee('No. of films');

        $this->post(route('staff.screenings.store'), [
            'program' => 'cinemalaya 2026', 'film_title' => 'Himala', 'event_date' => today()->addDays(3)->format('Y-m-d'),
            'start_time' => '17:00', 'end_time' => '19:00', 'type' => 'free',
        ])->assertSessionHasNoErrors();

        $this->assertSame('cinemalaya 2026', Screening::firstOrFail()->program->name);
        $this->assertEqualsCanonicalizing(['Filipino Classics', 'cinemalaya 2026'], $movie->programs()->pluck('name')->all());
    }

    public function test_save_as_draft_keeps_the_screening_and_its_new_film_from_customers(): void
    {
        $program = Program::factory()->create();

        $this->actingAs($this->staff)->post(route('staff.screenings.store'), $this->screeningForm($program, ['intent' => 'draft']))
            ->assertSessionHasNoErrors();

        $screening = Screening::with('movie')->firstOrFail();
        $this->assertSame('draft', $screening->status);
        $this->assertSame('draft', $screening->movie->status);
        $this->assertTrue($screening->movie->programs->contains($program));

        auth()->logout();
        $this->get(route('home'))->assertOk()->assertDontSee('Himala');
        $this->get(route('screenings.show', $screening))->assertNotFound();
        $this->get(route('bookings.create', $screening))->assertNotFound();
    }

    public function test_review_previews_the_customer_page_and_publish_makes_it_live(): void
    {
        $program = Program::factory()->create();
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), $this->screeningForm($program, ['intent' => 'review']))
            ->assertRedirect(route('staff.screenings.preview', $screening = Screening::firstOrFail()));

        $this->get(route('staff.screenings.preview', $screening))->assertOk()
            ->assertSee('class="preview-bar"', false)->assertSee('Home page card')->assertSee('Himala')
            ->assertSee(route('staff.screenings.publish', $screening), false)
            ->assertSee(route('staff.screenings.edit', $screening), false);

        $this->post(route('staff.screenings.publish', $screening))->assertRedirect(route('staff.screenings.show', $screening));
        $this->assertSame('published', $screening->fresh()->status);
        $this->assertSame('published', $screening->movie->fresh()->status);

        auth()->logout();
        $this->get(route('home'))->assertSee('Himala');
        $this->get(route('screenings.show', $screening))->assertOk()->assertDontSee('class="preview-bar"', false);
        $this->get(route('staff.screenings.preview', $screening))->assertRedirect(route('login'));
    }

    public function test_a_draft_film_hides_its_published_screenings(): void
    {
        $screening = Screening::factory()->create(['event_title' => 'Hidden Film']);
        $screening->movie->update(['status' => 'draft']);

        $this->get(route('home'))->assertDontSee('Hidden Film');
        $this->get(route('screenings.show', $screening))->assertNotFound();

        $this->actingAs($this->staff)->post(route('staff.movies.publish', $screening->movie))->assertRedirect();
        auth()->logout();
        $this->get(route('home'))->assertSee('Hidden Film');
    }

    public function test_film_form_saves_programs_and_intent(): void
    {
        [$a, $b] = Program::factory()->count(2)->create();

        $this->actingAs($this->staff)->post(route('staff.movies.store'), ['title' => 'Oro, Plata, Mata', 'programs' => [$a->name, $b->name], 'intent' => 'draft'])
            ->assertRedirect(route('staff.movies.show', $movie = Movie::firstOrFail()));
        $this->assertSame('draft', $movie->status);
        $this->assertEqualsCanonicalizing([$a->program_id, $b->program_id], $movie->programs->pluck('program_id')->all());

        // No screening yet: Review explains that customers see a film through its screenings.
        $this->put(route('staff.movies.update', $movie), ['title' => 'Oro, Plata, Mata', 'programs' => [$a->name], 'intent' => 'review'])
            ->assertRedirect(route('staff.movies.preview', $movie));
        $this->get(route('staff.movies.preview', $movie))->assertRedirect(route('staff.movies.show', $movie))->assertSessionHas('warning');

        $this->put(route('staff.movies.update', $movie), ['title' => 'Oro, Plata, Mata', 'programs' => [$a->name], 'intent' => 'publish']);
        $this->assertSame('published', $movie->fresh()->status);
        $this->assertSame([$a->program_id], $movie->fresh()->programs->pluck('program_id')->all());
    }

    public function test_catalog_shows_poster_tiles_by_program_with_draft_badges(): void
    {
        [$classics, $world] = Program::factory()->count(2)->create();
        $himala = Movie::factory()->create(['title' => 'Himala']);
        $himala->programs()->attach($classics);
        $draft = Movie::factory()->create(['title' => 'Unfinished Cut', 'status' => 'draft']);
        $draft->programs()->attach($world);

        $this->actingAs($this->staff)->get(route('staff.movies.index'))->assertOk()
            ->assertSeeInOrder([$classics->name, 'Himala'])->assertSee('Unfinished Cut')->assertSee('poster-tile__badge', false);

        $this->get(route('staff.movies.index', ['program' => $classics->program_id]))
            ->assertSee('Himala')->assertDontSee('Unfinished Cut');
    }

    public function test_film_panel_lists_every_screening_with_its_numbers(): void
    {
        $movie = Movie::factory()->create(['title' => 'Himala']);
        $screening = Screening::factory()->create(['movie_id' => $movie->movie_id, 'event_date' => today()->addDays(2)]);
        Reservation::factory()->for($screening)->withSeats(3)->create();
        Screening::factory()->draft()->create(['movie_id' => $movie->movie_id]);

        $this->actingAs($this->staff)->get(route('staff.movies.show', $movie))->assertOk()
            ->assertSee('Edit movie')->assertSee('Add screening schedule')
            ->assertSee('3 / 120')->assertSee('Draft')
            ->assertSee(route('staff.screenings.show', $screening), false);
    }

    public function test_a_film_with_screenings_cannot_be_deleted(): void
    {
        $screening = Screening::factory()->create();

        $this->actingAs($this->staff)->delete(route('staff.movies.destroy', $screening->movie))->assertSessionHasErrors('movie');
        $this->assertModelExists($screening->movie);
    }
}
