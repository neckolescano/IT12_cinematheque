<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Movie;
use App\Models\Program;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use App\Services\ProgramReports;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 2026-10-11 operations restructure: Operations / Insights / Settings navigation, one screening roster for
 * bookings and door check-in with a time-locked Admit, program tags with one-click exports, and batch
 * scheduling.
 */
class OperationsRestructureTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        $this->staff = User::factory()->create();
    }

    private function screeningAt(string $date, string $start = '18:00', string $end = '20:00'): Screening
    {
        return Screening::factory()->create(['event_date' => $date, 'start_time' => $start, 'end_time' => $end]);
    }

    public function test_navigation_has_operations_insights_and_settings_only(): void
    {
        $html = $this->actingAs($this->staff)->get(route('staff.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Films &amp; schedule', $html);
        $this->assertStringContainsString('Screenings &amp; check-in', $html);
        $this->assertStringContainsString('Program reports', $html);
        $this->assertStringNotContainsString('>Settings<', $html); // nothing for an Admin there
        $this->assertStringNotContainsString(route('staff.reservations.index'), $html);
        $this->assertStringNotContainsString('/ccdadmin/programs', $html);

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->get(route('staff.dashboard'))->assertSee('Staff accounts');
    }

    public function test_admit_unlocks_20_minutes_before_the_start_and_locks_60_minutes_after_the_end(): void
    {
        $screening = $this->screeningAt(today()->addDay()->toDateString());
        $reservation = Reservation::factory()->for($screening)->withSeats(2)->create();
        [$seat, $other] = $reservation->reservationSeats()->orderBy('seat_id')->get()->all();
        $start = $screening->startsAt();

        // 21 minutes before: a reservation roster; Admit is disabled and says when check-in opens.
        $this->travelTo($start->copy()->subMinutes(21));
        $this->actingAs($this->staff)->get(route('staff.screenings.show', $screening))->assertOk()
            ->assertSee('Reservation roster.')->assertSee('Check-in opens at 5:40 PM')
            ->assertSee('disabled title="Check-in opens at 5:40 PM', false)
            ->assertSee('data-reload-in="60"', false);
        $this->post(route('staff.attendances.store', $seat))->assertForbidden();

        // 20 minutes before: open.
        $this->travelTo($start->copy()->subMinutes(20));
        $this->post(route('staff.attendances.store', $seat))->assertRedirect();
        $this->get(route('staff.screenings.show', $screening))->assertSee('Check-in is open');

        // 59 minutes after the end: still open for late arrivals and corrections.
        $this->travelTo($screening->endsAt()->addMinutes(59));
        $this->delete(route('staff.attendances.destroy', Attendance::firstOrFail()))->assertRedirect();
        $this->post(route('staff.attendances.store', $seat))->assertRedirect();

        // 61 minutes after: closed for staff; the Super Admin can still correct the roster.
        $this->travelTo($screening->endsAt()->addMinutes(61));
        $this->post(route('staff.attendances.store', $other))->assertForbidden();
        $this->delete(route('staff.attendances.destroy', Attendance::firstOrFail()))->assertForbidden();
        $this->get(route('staff.screenings.show', $screening))->assertSee('Check-in closed');

        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super)->post(route('staff.attendances.store', $other))->assertRedirect();
        $this->assertSame(2, Attendance::count());
    }

    public function test_the_super_admin_can_admit_before_check_in_opens(): void
    {
        $screening = $this->screeningAt(today()->addDays(3)->toDateString());
        $seat = Reservation::factory()->for($screening)->withSeats(1)->create()->reservationSeats()->first();

        $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.attendances.store', $seat))->assertRedirect();
        $this->assertSame(1, Attendance::count());
    }

    public function test_the_roster_shows_guest_details_and_booking_actions(): void
    {
        $screening = Screening::factory()->paid(150)->create();
        $reservation = Reservation::factory()->for($screening)->withSeats(1)->create();
        $seat = $reservation->reservationSeats()->first();
        $seat->update(['discount_type' => 'pwd', 'amount_due' => 120]);
        $seat->attendee->update(['company_school' => 'Ateneo de Davao', 'pwd_id_no' => '11-2402-000-0001234']);
        $reservation->payment()->create(['amount' => 120, 'status' => 'verified']);

        $this->actingAs($this->staff)->get(route('staff.screenings.show', [$screening, 'open' => $reservation->booking_reference]))->assertOk()
            ->assertSee('id="booking-'.$reservation->booking_reference.'"', false)
            ->assertSee('Ateneo de Davao')->assertSee('PWD 11-2402-000-0001234')->assertSee('20% off · ₱120.00')
            ->assertSee('₱120.00 paid')
            ->assertSee(route('staff.reservations.resend', $reservation), false)
            ->assertSee(route('staff.reservations.cancel', $reservation), false);
    }

    public function test_batch_scheduling_creates_repeats_and_extra_showtimes_and_refuses_clashes(): void
    {
        $form = [
            'program' => 'Cinemalaya 2026', 'film_title' => 'Himala', 'event_date' => '2026-11-02', 'start_time' => '15:00', 'end_time' => '17:00',
            'type' => 'paid', 'price' => 150, 'repeat' => 'weekly', 'repeat_until' => '2026-11-23',
            'more' => [['date' => '2026-11-04', 'start' => '18:30'], ['date' => '', 'start' => '']],
        ];

        $this->actingAs($this->staff)->post(route('staff.screenings.store'), $form)
            ->assertRedirect(route('staff.movies.show', Movie::where('title', 'Himala')->first() ?? 0))
            ->assertSessionHas('status', '5 screenings created and published.');

        $this->assertSame(
            ['2026-11-02 15:00:00', '2026-11-04 18:30:00', '2026-11-09 15:00:00', '2026-11-16 15:00:00', '2026-11-23 15:00:00'],
            Screening::orderBy('event_date')->get()->map(fn ($s) => $s->event_date->toDateString().' '.$s->start_time)->all()
        );
        $this->assertSame(['17:00:00', '20:30:00'], Screening::orderBy('event_date')->take(2)->pluck('end_time')->all()); // same 2-hour length
        $this->assertSame(1, Program::count());

        // The hall is booked then: nothing is created.
        $this->post(route('staff.screenings.store'), [...$form, 'repeat' => 'none', 'more' => [], 'event_date' => '2026-11-09', 'start_time' => '16:00', 'end_time' => '18:00'])
            ->assertSessionHasErrors('start_time');
        $this->post(route('staff.screenings.store'), [...$form, 'event_date' => '2026-12-01', 'repeat_until' => '2026-12-01',
            'more' => [['date' => '2026-12-01', 'start' => '16:00']]])->assertSessionHasErrors('more');
        $this->assertSame(5, Screening::count());
    }

    public function test_program_reports_list_live_totals_and_export_in_one_click(): void
    {
        $program = Program::fromTag('Cinemalaya 2026');
        $screening = Screening::factory()->paid(50)->past()->create(['program_id' => $program->program_id, 'event_title' => 'Himala']);
        $booking = Reservation::factory()->for($screening)->withSeats(2)->create();
        $booking->payment()->create(['amount' => 100, 'status' => 'verified']);
        foreach ($booking->reservationSeats as $i => $seat) {
            $seat->attendee->update(['sex' => $i ? 'F' : 'M']);
            $seat->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $this->staff->user_id]);
        }

        $totals = app(ProgramReports::class)->overview()->get($program->program_id);
        $this->assertSame([1, 1, 1, '100.00'], [(int) $totals->screenings, (int) $totals->male, (int) $totals->female, number_format((float) $totals->revenue, 2, '.', '')]);

        $this->actingAs($this->staff)->get(route('staff.program-reports.index'))->assertOk()
            ->assertSee('Cinemalaya 2026')->assertSee('₱100.00')
            ->assertSee(route('staff.program-reports.export-program', [$program, 'csv']), false);

        // No report yet: live figures.
        $csv = $this->get(route('staff.program-reports.export-program', [$program, 'csv']))->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="program-report-cinemalaya-2026.csv"')->getContent();
        $this->assertStringContainsString('Cinemalaya 2026', $csv);
        $this->assertStringContainsString('PROGRAM TOTAL', $csv);
        $this->assertStringContainsString(',100.00,', $csv);
        $this->get(route('staff.program-reports.export-program', [$program, 'xlsx']))->assertOk()->assertDownload('program-report-cinemalaya-2026.xlsx');

        // Submitted: the locked snapshot, even after the bookings change.
        $this->post(route('staff.program-reports.store'), ['program_id' => $program->program_id]);
        $this->post(route('staff.program-reports.submit', Report::firstOrFail()));
        Attendance::query()->delete();
        $csv = $this->get(route('staff.program-reports.export-program', [$program, 'csv']))->getContent();
        $this->assertStringContainsString('Himala,Paid,1,1,1,', $csv); // still 1 M and 1 F admitted
    }
}
