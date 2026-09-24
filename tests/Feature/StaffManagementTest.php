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

    public function test_check_in_records_attendance_once(): void
    {
        $reservation = Reservation::factory()->withSeats(2)->create();
        $seat = $reservation->reservationSeats()->first();

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $seat), ['control_number' => '000123'])
            ->assertRedirect();

        $attendance = Attendance::firstOrFail();
        $this->assertSame('000123', $attendance->control_number);
        $this->assertSame($this->staff->user_id, $attendance->checked_in_by);

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $seat))->assertForbidden();
        $this->assertSame(1, Attendance::count());
    }

    public function test_control_number_must_be_unique_and_can_be_recorded_later(): void
    {
        $reservation = Reservation::factory()->withSeats(2)->create();
        [$s1, $s2] = $reservation->reservationSeats->all();

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $s1), ['control_number' => 'A-1']);
        $this->actingAs($this->staff)->post(route('staff.attendances.store', $s2), ['control_number' => 'A-1'])
            ->assertSessionHasErrors('control_number');

        $this->actingAs($this->staff)->post(route('staff.attendances.store', $s2))->assertRedirect();
        $second = $s2->attendance()->firstOrFail();
        $this->actingAs($this->staff)->patch(route('staff.attendances.update', $second), ['control_number' => 'A-2'])
            ->assertRedirect();
        $this->assertSame('A-2', $second->fresh()->control_number);
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
            route('staff.payment-proofs.index'), route('staff.qr-codes.index'),
            route('staff.movies.index'), route('staff.movies.create'), route('staff.movies.edit', $movie),
            route('staff.actors.index'), route('staff.directors.index'), route('staff.genres.index'),
            route('staff.seats.index'), route('staff.users.index'), route('staff.users.create'),
            route('staff.reports.index'), route('bookings.show', $reservation), '/',
        ] as $url) {
            $this->actingAs($this->staff)->get($url)->assertOk();
        }
    }
}
