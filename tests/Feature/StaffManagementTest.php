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
            'event_title' => 'Opening Night', 'event_date' => today()->addDay()->toDateString(),
            'start_time' => '18:00', 'end_time' => '20:00', 'type' => 'paid', 'price' => '200', 'total_seats' => 80,
        ])->assertRedirect();

        $screening = Screening::firstOrFail();
        $this->assertSame($this->staff->user_id, $screening->created_by);
        $this->assertNull($screening->movie_id);
    }

    public function test_screening_validation(): void
    {
        $this->actingAs($this->staff)->post(route('staff.screenings.store'), [
            'event_title' => '', 'event_date' => 'x', 'start_time' => '20:00', 'end_time' => '18:00', 'type' => 'paid', 'total_seats' => 0,
        ])->assertSessionHasErrors(['event_title', 'event_date', 'end_time', 'price', 'total_seats']);
    }

    public function test_screening_with_reservations_cannot_be_deleted(): void
    {
        $screening = Screening::factory()->create();
        Reservation::factory()->for($screening)->withSeats(1)->create();

        $this->actingAs($this->staff)->delete(route('staff.screenings.destroy', $screening))->assertForbidden();
        $this->assertModelExists($screening);
    }

    public function test_movie_catalog_syncs_pivots(): void
    {
        $actors = Actor::factory()->count(2)->create();
        $genre = Genre::factory()->create();

        $this->actingAs($this->staff)->post(route('staff.movies.store'), [
            'title' => 'Himala', 'release_year' => 1982,
            'actor_ids' => $actors->pluck('actor_id')->all(), 'genre_ids' => [$genre->genre_id],
        ])->assertRedirect();

        $movie = Movie::with('actors', 'genres')->firstOrFail();
        $this->assertCount(2, $movie->actors);
        $this->assertCount(1, $movie->genres);
    }

    public function test_admission_is_recorded_once_without_a_control_number(): void
    {
        $reservation = Reservation::factory()->withSeats(2)->create(); // factory default: confirmed
        $seat = $reservation->reservationSeats()->first();

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

        $this->actingAs($this->staff)->postJson(route('staff.attendances.store', $seat))
            ->assertOk()->assertJsonPath('admitted', 1)
            ->assertJsonPath('message', 'Seat '.$seat->seat->seat_label.' admitted.')
            ->assertSee('Undo', false);

        $attendance = Attendance::firstOrFail();
        $this->actingAs($this->staff)->deleteJson(route('staff.attendances.destroy', $attendance))
            ->assertOk()->assertJsonPath('admitted', 0);
        $this->assertSame(0, Attendance::count());
    }

    public function test_pending_reservations_cannot_be_admitted(): void
    {
        $reservation = Reservation::factory()->pending()->withSeats(1)->create();

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

    public function test_approve_all_pending_free_reservations(): void
    {
        $screening = Screening::factory()->create();
        Reservation::factory()->count(2)->for($screening)->pending()->withSeats(1)->create();

        $this->actingAs($this->staff)->post(route('staff.screenings.approve-pending', $screening))->assertRedirect();

        $this->assertSame(2, $screening->reservations()->where('status', 'confirmed')->count());
    }

    public function test_paid_reservations_cannot_be_approved_by_staff(): void
    {
        $screening = Screening::factory()->paid()->create();
        $reservation = Reservation::factory()->for($screening)->pending()->withSeats(1)->create();
        $reservation->payment()->create(['amount' => 150, 'status' => 'pending']);

        $this->actingAs($this->staff)->patch(route('staff.reservations.confirm', $reservation))->assertForbidden();
        $this->actingAs($this->staff)->post(route('staff.screenings.approve-pending', $screening))->assertStatus(422);
        $this->assertSame('pending', $reservation->fresh()->status);
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
        $this->seed(\Database\Seeders\StaffUserSeeder::class);
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $this->seed(\Database\Seeders\DemoScreeningSeeder::class);

        $reservation = Reservation::firstOrFail();
        $screening = Screening::firstOrFail();
        $movie = Movie::firstOrFail();

        foreach ([
            route('staff.dashboard'), route('staff.screenings.index'), route('staff.screenings.create'),
            route('staff.screenings.show', $screening), route('staff.screenings.edit', $screening),
            route('staff.reservations.index'), route('staff.reservations.show', $reservation),
            route('staff.movies.index'), route('staff.movies.create'), route('staff.movies.edit', $movie),
            route('staff.actors.index'), route('staff.directors.index'), route('staff.genres.index'),
            route('staff.seats.index'), route('staff.users.index'), route('staff.users.create'),
            route('staff.reports.index'), route('bookings.show', $reservation), route('home'),
        ] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }
}
