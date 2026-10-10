<?php

namespace Tests\Feature;

use App\Models\Actor;
use App\Models\Attendance;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        $this->staff = User::factory()->create();
    }

    public function test_staff_can_create_screening_and_is_recorded_as_creator(): void
    {
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), [
            'program_id' => \App\Models\Program::factory()->create()->program_id,
            'film_title' => 'Opening Night', 'event_title' => '', 'event_date' => today()->addDay()->toDateString(),
            'start_time' => '18:00', 'end_time' => '20:00', 'type' => 'paid', 'price' => '200',
        ])->assertRedirect();

        $screening = Screening::firstOrFail();
        $this->assertSame($this->staff->user_id, $screening->created_by);
        $this->assertSame('Opening Night', $screening->movie->title);
    }

    public function test_screening_validation(): void
    {
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), [
            'event_title' => '', 'event_date' => 'x', 'start_time' => '20:00', 'end_time' => '18:00', 'type' => 'paid',
        ])->assertSessionHasErrors(['event_title', 'event_date', 'end_time', 'price']);
    }

    public function test_screening_with_reservations_cannot_be_deleted(): void
    {
        $screening = Screening::factory()->create();
        Reservation::factory()->for($screening)->withSeats(1)->create();

        $this->actingAs($this->staff)->delete(route('staff.screenings.destroy', $screening))->assertForbidden();
        $this->assertModelExists($screening);
    }

    public function test_movie_form_takes_typed_credits_and_fixed_genres(): void
    {
        $this->actingAs($this->staff)->post(route('staff.movies.store'), [
            'title' => 'Himala', 'release_year' => 1982,
            'directors' => 'Ishmael Bernal', 'actors' => 'Nora Aunor, Veronica Palileo',
            'genres' => ['Drama'],
        ])->assertRedirect();

        $movie = Movie::with('actors', 'genres', 'directors')->firstOrFail();
        $this->assertSame('Ishmael Bernal', $movie->directorNames());
        $this->assertSame('Nora Aunor, Veronica Palileo', $movie->castNames());
        $this->assertSame(['Drama'], $movie->genres->pluck('genre_name')->all());

        $this->actingAs($this->staff)->post(route('staff.movies.store'), ['title' => 'X', 'genres' => ['Not A Genre']])
            ->assertSessionHasErrors('genres.0');
    }

    public function test_admission_is_recorded_once_without_a_control_number(): void
    {
        $reservation = Reservation::factory()->withSeats(2)->create(); // factory default: confirmed
        $seat = $reservation->reservationSeats()->first();
        $this->travelTo($reservation->screening->startsAt()); // check-in is open

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $seat), ['remarks' => 'On time'])
            ->assertRedirect();

        $attendance = Attendance::firstOrFail();
        $this->assertSame($this->staff->user_id, $attendance->checked_in_by);
        $this->assertSame('On time', $attendance->remarks);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('attendances', 'control_number'));

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $seat))->assertForbidden();
        $this->assertSame(1, Attendance::count());
    }

    public function test_admit_and_undo_via_the_checklist_return_the_updated_row(): void
    {
        $reservation = Reservation::factory()->withSeats(1)->create();
        $seat = $reservation->reservationSeats()->first();
        $this->travelTo($reservation->screening->startsAt()); // check-in is open

        $response = $this->actingAs($this->staff)->postJson(route('staff.attendances.store', $seat))
            ->assertOk()->assertJsonPath('admitted', 1)
            ->assertJsonPath('message', 'Seat '.$seat->seat->seat_label.' admitted.');
        // The reservation's row comes back re-rendered as admitted, with Undo.
        $this->assertStringContainsString('data-state="admitted"', $response->json('party'));
        $this->assertStringContainsString('Undo', $response->json('party'));

        $attendance = Attendance::firstOrFail();
        $this->actingAs($this->staff)->deleteJson(route('staff.attendances.destroy', $attendance))
            ->assertOk()->assertJsonPath('admitted', 0);
        $this->assertSame(0, Attendance::count());
    }

    public function test_reservations_awaiting_payment_cannot_be_admitted(): void
    {
        $reservation = Reservation::factory()->awaitingPayment()->withSeats(1)->create();

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $reservation->reservationSeats()->first()))
            ->assertForbidden();
        $this->assertSame(0, Attendance::count());
    }

    public function test_checklist_lists_every_reserved_seat_and_keeps_no_shows_distinct(): void
    {
        $screening = Screening::factory()->past()->create();
        $came = Reservation::factory()->for($screening)->withSeats(1)->create();
        $noShow = Reservation::factory()->for($screening)->withSeats(1)->create();
        $came->reservationSeats()->first()->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $this->staff->user_id]);

        $html = $this->actingAs($this->staff)->get(route('staff.screenings.show', $screening))->assertOk()->getContent();

        $this->assertStringContainsString($came->booking_reference, $html);
        $this->assertStringContainsString($noShow->booking_reference, $html);
        $this->assertSame(1, substr_count($html, 'data-state="admitted"'));
        $this->assertSame(1, substr_count($html, 'data-state="no-show"'));
    }

    public function test_cancelled_reservation_cannot_be_checked_in(): void
    {
        $reservation = Reservation::factory()->withSeats(1)->create();
        $this->actingAs($this->staff)->patch(route('staff.reservations.cancel', $reservation))->assertRedirect();

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $reservation->reservationSeats()->first()))
            ->assertForbidden();
    }

    public function test_report_exports_as_csv(): void
    {
        $screening = Screening::factory()->create(['event_title' => 'Export Night']);
        Reservation::factory()->for($screening)->withSeats(2)->create();

        $response = $this->actingAs($this->staff)->get(route('staff.reports.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Seats reserved', $csv);
        $this->assertStringContainsString('Export Night', $csv);

        $this->get(route('staff.reports.export'))->assertOk(); // still logged in
        auth()->logout();
        $this->get(route('staff.reports.export'))->assertRedirect(route('login'));
    }

    public function test_staff_pages_render(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public'); // CatalogSeeder copies poster files
        $this->seed(\Database\Seeders\StaffUserSeeder::class);
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $this->seed(\Database\Seeders\DemoScreeningSeeder::class);

        $reservation = Reservation::firstOrFail();
        $screening = Screening::firstOrFail();
        $movie = Movie::firstOrFail();

        foreach ([
            route('staff.dashboard'), route('staff.screenings.index'), route('staff.screenings.create'),
            route('staff.screenings.show', $screening), route('staff.screenings.edit', $screening),
            route('staff.screenings.show', $reservation->screening_id),
            route('staff.movies.index'), route('staff.movies.create'), route('staff.movies.edit', $movie), route('staff.movies.show', $movie),
            route('staff.program-reports.index'),
            route('staff.reports.index'), route('bookings.show', $reservation), route('home'),
        ] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }

        $superAdmin = \App\Models\User::where('role', 'super_admin')->firstOrFail();
        foreach ([route('staff.users.index'), route('staff.users.create'), route('staff.program-reports.index')] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
        }
    }
}
