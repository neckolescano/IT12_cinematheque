<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dashboard revision (2026-10-07): fixed 120 seats, "Other" genre, Admit all, demographic report, catalog list. */
class DashboardRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        $this->staff = User::factory()->create();
    }

    private function screeningForm(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'film', 'film_title' => 'Himala', 'genres' => ['Drama'],
            'event_title' => '', 'event_date' => today()->addDay()->format('Y-m-d'), 'start_time' => '17:00', 'end_time' => '19:00',
            'type' => 'free',
        ], $overrides);
    }

    public function test_the_venue_has_exactly_120_seats_and_no_seat_admin(): void
    {
        $this->assertSame(Seat::CAPACITY, Seat::count());
        $this->assertSame(120, Seat::CAPACITY);

        // 10 rows (A–J) of 12 seats.
        $rows = Seat::pluck('seat_label')->groupBy(fn ($label) => preg_replace('/\d+$/', '', $label))->map->count();
        $this->assertSame(range('A', 'J'), $rows->keys()->sort()->values()->all());
        $this->assertSame([12], $rows->unique()->values()->all());
        $this->assertTrue(Seat::where('seat_label', 'J12')->exists());

        // Capacity is not entered per screening: anything sent is ignored.
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), $this->screeningForm(['total_seats' => 5]))->assertSessionHasNoErrors();
        $this->assertSame(120, Screening::firstOrFail()->total_seats);

        $this->get('/ccdadmin/seats')->assertNotFound();
        $this->get(route('staff.dashboard'))->assertDontSee('Seat layout');
    }

    public function test_other_genre_is_saved_alongside_the_listed_ones(): void
    {
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), $this->screeningForm([
            'genres' => ['Drama'], 'genre_other_on' => '1', 'genre_other' => 'Mumblecore, drama',
        ]))->assertSessionHasNoErrors();

        $movie = Movie::with('genres')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Drama', 'Mumblecore'], $movie->genres->pluck('genre_name')->all());
        $this->assertSame('Mumblecore', $movie->customGenres());

        // Ticking "Other" without typing one is an error; there is still no genre admin page.
        $this->post(route('staff.screenings.store'), $this->screeningForm(['genre_other_on' => '1', 'genre_other' => '']))
            ->assertSessionHasErrors('genre_other');
        $this->get('/ccdadmin/genres')->assertNotFound();
    }

    public function test_admit_all_admits_only_the_people_left_ticked(): void
    {
        $reservation = Reservation::factory()->withSeats(3)->create(); // confirmed
        [$a, $b, $absent] = $reservation->reservationSeats()->orderBy('seat_id')->get()->all();

        $this->actingAs($this->staff)->get(route('staff.screenings.show', $reservation->screening))
            ->assertOk()->assertSee('Party of 3')->assertSee('Admit party')->assertSee('id="admit-'.$reservation->reservation_id.'"', false);

        $this->post(route('staff.reservations.admit', $reservation), ['seats' => [$a->reservation_seat_id, $b->reservation_seat_id]])
            ->assertSessionHas('status');

        $this->assertNotNull($a->fresh()->attendance);
        $this->assertNotNull($b->fresh()->attendance);
        $this->assertNull($absent->fresh()->attendance);

        // Individual admission still works for the one left out.
        $this->post(route('staff.attendances.store', $absent))->assertRedirect();
        $this->assertNotNull($absent->fresh()->attendance);
    }

    public function test_admit_all_respects_booking_state_and_needs_a_selection(): void
    {
        $pending = Reservation::factory()->pending()->withSeats(2)->create();
        $this->actingAs($this->staff)->post(route('staff.reservations.admit', $pending), ['seats' => $pending->reservationSeats->pluck('reservation_seat_id')->all()])
            ->assertSessionHas('warning');
        $this->assertSame(0, $pending->reservationSeats()->whereHas('attendance')->count());

        $confirmed = Reservation::factory()->withSeats(2)->create();
        $this->post(route('staff.reservations.admit', $confirmed), ['seats' => []])->assertSessionHasErrors('seats');

        // Seats of another booking can't be admitted through this one.
        $this->post(route('staff.reservations.admit', $confirmed), ['seats' => $pending->reservationSeats->pluck('reservation_seat_id')->all()])
            ->assertSessionHas('warning');
    }

    public function test_demographic_report_lists_only_people_actually_admitted(): void
    {
        $screening = Screening::factory()->create(['event_date' => today(), 'event_title' => 'Demo Night']);
        $came = Reservation::factory()->for($screening)->withSeats(2)->create();
        $noShow = Reservation::factory()->for($screening)->withSeats(1)->create();
        $cancelled = Reservation::factory()->for($screening)->withSeats(1)->create();

        [$in, $out] = $came->reservationSeats()->with('attendee')->orderBy('seat_id')->get()->all();
        $in->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $this->staff->user_id]);
        $in->attendee->update(['first_name' => 'Admitted', 'last_name' => 'Person', 'company_school' => 'Ateneo de Davao']);
        $out->attendee->update(['first_name' => 'Stayed', 'last_name' => 'Home']);
        $noShow->reservationSeats->first()->attendee->update(['first_name' => 'Never', 'last_name' => 'Came']);
        $c = $cancelled->reservationSeats()->with('attendee')->first();
        $c->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $this->staff->user_id]);
        $c->attendee->update(['first_name' => 'Cancelled', 'last_name' => 'Later']);
        $cancelled->cancel('staff');

        $this->actingAs($this->staff)->get(route('staff.reports.index', ['view' => 'demographics']))
            ->assertOk()->assertSee('Admitted Person')->assertSee('Ateneo de Davao')
            ->assertDontSee('Stayed Home')->assertDontSee('Never Came')->assertDontSee('Cancelled Later');

        $csv = $this->get(route('staff.reports.export', ['view' => 'demographics']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Company/School', $csv);
        $this->assertStringContainsString('Admitted', $csv);
        $this->assertStringNotContainsString('Stayed', $csv);
        $this->assertStringNotContainsString('Cancelled', $csv);
    }

    public function test_dashboard_and_film_catalog_list(): void
    {
        Movie::factory()->create(['title' => 'Himala', 'release_year' => 1982]);

        $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->assertSee('<h1>Dashboard</h1>', false);
        $this->get(route('staff.movies.index'))->assertOk()->assertSee('Himala')->assertSee('class="table films"', false);
    }

    public function test_attendance_page_has_no_create_button_and_scheduling_starts_from_a_film(): void
    {
        $movie = Movie::factory()->create(['title' => 'Himala']);
        Screening::factory()->create(['event_date' => today()->addDay()]);

        $this->actingAs($this->staff)->get(route('staff.screenings.index'))
            ->assertOk()->assertSee('<h1>Attendance</h1>', false)->assertDontSee(route('staff.screenings.create'), false);
        $this->get(route('staff.movies.index'))->assertSee(route('staff.screenings.create', ['movie' => $movie->movie_id]), false);
        $this->get(route('staff.screenings.create', ['movie' => $movie->movie_id]))->assertOk()->assertSee('value="Himala"', false);
    }

    public function test_reports_no_longer_show_check_ins_by_staff(): void
    {
        $this->actingAs($this->staff)->get(route('staff.reports.index'))->assertOk()->assertDontSee('Check-ins by staff');
    }
}
