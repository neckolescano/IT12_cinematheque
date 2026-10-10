<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Program;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\ReservationAttendee;
use App\Models\Screening;
use App\Models\Seat;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\DemoScreeningSeeder;
use Database\Seeders\SeatSeeder;
use Database\Seeders\StaffUserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** System revision, phase 1: the data foundation (programs, roles, per-ticket prices, reports). */
class RevisionPhase1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
    }

    public function test_a_film_can_be_in_more_than_one_program_and_each_screening_is_in_one(): void
    {
        $classics = Program::factory()->create(['name' => 'Filipino Classics']);
        $cinemalaya = Program::factory()->create(['name' => 'Cinemalaya']);
        $himala = Movie::factory()->create(['title' => 'Himala']);
        $himala->programs()->attach([$classics->program_id, $cinemalaya->program_id]);

        $screening = Screening::factory()->create(['movie_id' => $himala->movie_id, 'program_id' => $cinemalaya->program_id]);

        $this->assertEqualsCanonicalizing(['Filipino Classics', 'Cinemalaya'], $himala->programs->pluck('name')->all());
        $this->assertTrue($classics->movies->contains($himala));
        $this->assertTrue($screening->program->is($cinemalaya));
        $this->assertTrue($cinemalaya->screenings->contains($screening));
        $this->assertCount(0, $classics->screenings);
    }

    public function test_each_ticket_takes_its_screenings_price(): void
    {
        $paid = Screening::factory()->paid(150)->create();
        $free = Screening::factory()->create();

        $paidSeat = Reservation::factory()->for($paid)->withSeats(1)->create()->reservationSeats->first();
        $freeSeat = Reservation::factory()->for($free)->withSeats(1)->create()->reservationSeats->first();

        $this->assertSame('150.00', $paidSeat->unit_price);
        $this->assertSame('150.00', $paidSeat->amount_due);
        $this->assertSame('none', $paidSeat->discount_type);
        $this->assertSame('0.00', $freeSeat->amount_due);
    }

    public function test_school_or_company_is_required_in_the_database(): void
    {
        $seat = Reservation::factory()->for(Screening::factory()->create())->withSeats(1)->create()->reservationSeats->first();

        $this->expectException(QueryException::class);
        ReservationAttendee::whereKey($seat->attendee->getKey())->update(['company_school' => null]);
    }

    public function test_staff_are_admins_and_there_is_only_one_super_admin(): void
    {
        $this->seed(StaffUserSeeder::class);

        $this->assertSame('admin', User::factory()->create()->role);
        $this->assertSame(1, User::where('role', 'super_admin')->count());
        $this->assertTrue(User::where('email', 'manila@cinematheque.test')->firstOrFail()->isSuperAdmin());

        $this->expectException(\DomainException::class);
        User::factory()->create(['role' => 'super_admin']);
    }

    public function test_paid_bookings_can_await_payment(): void
    {
        $reservation = Reservation::factory()->for(Screening::factory()->paid()->create())->create(['status' => 'awaiting_payment']);

        $this->assertSame('awaiting_payment', $reservation->fresh()->status);
        $this->assertContains('awaiting_payment', Reservation::STATUSES);
    }

    public function test_a_program_has_one_report_with_frozen_rows_and_an_audit_trail(): void
    {
        $program = Program::factory()->create();
        $staff = User::factory()->create();
        $screening = Screening::factory()->create(['program_id' => $program->program_id, 'partner' => 'OWWA Region XI', 'agency_type' => 'Government']);

        $report = Report::create(['program_id' => $program->program_id, 'generated_by' => $staff->user_id]);
        $report->rows()->create([
            'screening_id' => $screening->screening_id, 'type' => 'free', 'screening_date' => $screening->event_date,
            'start_time' => '15:00', 'film_title' => 'Ang Huling ChaCha Ni Anita',
            'male' => 6, 'female' => 20, 'total_audience' => 26, 'occupancy_rate' => 21.67,
            'partner' => $screening->partner, 'agency_type' => $screening->agency_type,
        ]);
        $report->events()->create(['user_id' => $staff->user_id, 'action' => 'generated']);

        $this->assertSame('draft', $report->fresh()->status);
        $this->assertFalse($report->isLocked());
        $this->assertSame('21.67', $report->rows->first()->occupancy_rate);
        $this->assertTrue($program->report->is($report));
        $this->assertSame('generated', $report->events->first()->action);

        // One report per program.
        $this->expectException(QueryException::class);
        Report::create(['program_id' => $program->program_id, 'generated_by' => $staff->user_id]);
    }

    public function test_demo_data_files_every_film_and_screening_under_a_program(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public'); // CatalogSeeder copies poster files
        $this->seed([StaffUserSeeder::class, CatalogSeeder::class, DemoScreeningSeeder::class]);

        $this->assertSame(0, Movie::doesntHave('programs')->count());
        $this->assertSame(0, Screening::whereNull('program_id')->count());
        $this->assertSame('Community Screenings', Screening::where('event_title', 'Shorts Night: Mindanao Filmmakers')->firstOrFail()->program->name);
        $this->assertSame(Seat::CAPACITY, Seat::count());
    }
}
