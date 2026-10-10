<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Revision phase 4 (2026-10-11): the program report in the Manila format, its lock (submit → request
 * unlock → Super Admin unlock), the .xlsx export, and Super-Admin-only staff accounts.
 */
class RevisionPhase4Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SeatSeeder::class);
        $this->admin = User::factory()->create();
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->program = Program::factory()->create(['name' => 'Pamanang Pelikula']);
    }

    /** Admit every seat of a reservation at the door; $sexes sets each attendee's sex in seat order. */
    private function admit(Reservation $reservation, array $sexes = []): void
    {
        foreach ($reservation->reservationSeats()->with('attendee')->orderBy('seat_id')->get() as $i => $seat) {
            if (isset($sexes[$i])) {
                $seat->attendee->update(['sex' => $sexes[$i]]);
            }
            $seat->attendance()->create(['checked_in_at' => now(), 'checked_in_by' => $this->admin->user_id]);
        }
    }

    /** The Manila sheet examples: 26 viewers = 21.67% occupancy; 4 regular + 1 discount at ₱50 = ₱240. */
    private function sheetExamples(): array
    {
        $free = Screening::factory()->past()->create(['program_id' => $this->program->program_id, 'event_title' => 'Biyaya ng Lupa']);
        $party = Reservation::factory()->for($free)->withSeats(26)->create();
        $this->admit($party, [...array_fill(0, 10, 'M'), ...array_fill(0, 16, 'F')]);
        $party->reservationSeats()->orderBy('seat_id')->first()->attendee->update(['senior_card_no' => 'SC-1']);

        $paid = Screening::factory()->paid(50)->past()->create(['program_id' => $this->program->program_id, 'event_title' => 'Himala', 'films_count' => 1]);
        $booking = Reservation::factory()->for($paid)->withSeats(5)->create();
        $pwdSeat = $booking->reservationSeats()->orderBy('seat_id')->get()->last();
        $pwdSeat->update(['discount_type' => 'pwd', 'amount_due' => 40]);
        $pwdSeat->attendee->update(['pwd_id_no' => 'PWD-9']);
        $booking->payment()->create(['amount' => 240, 'status' => 'verified']);
        $this->admit($booking, ['M', 'M', 'F', 'F', 'F']);

        // Never in the report: a draft screening, and an unpaid booking's sales.
        Screening::factory()->draft()->create(['program_id' => $this->program->program_id, 'event_title' => 'Not Yet Announced']);
        $unpaid = Reservation::factory()->for($paid)->awaitingPayment()->withSeats(2)->create();
        $unpaid->payment()->create(['amount' => 100, 'status' => 'pending']);

        return [$free, $paid];
    }

    public function test_generated_rows_and_totals_match_the_manila_sheets(): void
    {
        [$free, $paid] = $this->sheetExamples();

        $this->actingAs($this->admin)->post(route('staff.program-reports.store'), ['program_id' => $this->program->program_id])
            ->assertRedirect(route('staff.program-reports.show', $report = Report::firstOrFail()));

        $rows = $report->rows()->get()->keyBy('screening_id');
        $this->assertCount(2, $rows); // the draft screening is left out

        $f = $rows[$free->screening_id];
        $this->assertSame(['free', 10, 16, 0, 1, 26, '21.67'], [$f->type, $f->male, $f->female, $f->pwd, $f->senior, $f->total_audience, $f->occupancy_rate]);

        $p = $rows[$paid->screening_id];
        $this->assertSame(['paid', 4, 1, '240.00', 1, 5], [$p->type, $p->regular_count, $p->discount_count, $p->total_sales, $p->pwd, $p->total_audience]);

        $this->get(route('staff.program-reports.show', $report))->assertOk()
            ->assertSee('Program total')->assertSee('21.67%')->assertSee('₱240.00')
            ->assertSeeInOrder(['Biyaya ng Lupa', 'Himala'])->assertDontSee('Not Yet Announced');
    }

    public function test_a_submitted_report_is_locked_for_admins_until_the_super_admin_unlocks_it(): void
    {
        Screening::factory()->create(['program_id' => $this->program->program_id]);
        $this->actingAs($this->admin)->post(route('staff.program-reports.store'), ['program_id' => $this->program->program_id]);
        $report = Report::firstOrFail();
        $row = $report->rows()->firstOrFail();

        $this->put(route('staff.program-reports.update', $report), ['rows' => [$row->report_row_id => ['partner' => 'OWWA Region XI', 'agency_type' => 'Government']]])
            ->assertSessionHasNoErrors();
        $this->post(route('staff.program-reports.submit', $report))->assertRedirect();
        $this->assertSame('submitted', $report->fresh()->status);

        // Locked: no edits, no regenerating, no submitting again, and an admin can't unlock.
        $this->put(route('staff.program-reports.update', $report), ['rows' => [$row->report_row_id => ['partner' => 'Changed']]])->assertForbidden();
        $this->post(route('staff.program-reports.submit', $report))->assertForbidden();
        $this->post(route('staff.program-reports.unlock', $report), ['reason' => 'I want to fix it'])->assertForbidden();
        $this->post(route('staff.program-reports.store'), ['program_id' => $this->program->program_id])->assertSessionHasErrors('report');
        $this->assertSame('OWWA Region XI', $row->fresh()->partner);

        // An admin asks, with a reason; the Super Admin sees it in the inbox and unlocks, with a reason.
        $this->post(route('staff.program-reports.request-unlock', $report), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('staff.program-reports.request-unlock', $report), ['reason' => 'Two admissions were missed at the door.'])->assertSessionHasNoErrors();
        $this->post(route('staff.program-reports.request-unlock', $report), ['reason' => 'Again'])->assertForbidden(); // one open request

        $this->actingAs($this->superAdmin)->get(route('staff.program-reports.index'))
            ->assertSee('Unlock requested')->assertSee('Two admissions were missed at the door.');
        $this->post(route('staff.program-reports.unlock', $report), ['reason' => 'Approved: recount admissions.'])->assertSessionHasNoErrors();
        $this->assertSame('unlocked', $report->fresh()->status);

        // Editable again; regenerating keeps the typed partner; then it is resubmitted.
        $this->actingAs($this->admin)->post(route('staff.program-reports.store'), ['program_id' => $this->program->program_id])->assertSessionHasNoErrors();
        $this->assertSame('OWWA Region XI', $report->rows()->firstOrFail()->partner);
        $this->post(route('staff.program-reports.submit', $report));

        $this->assertSame(
            ['generated', 'edited', 'submitted', 'unlock_requested', 'unlocked', 'generated', 'resubmitted'],
            $report->events()->orderBy('event_id')->pluck('action')->all()
        );
        $this->assertSame('Approved: recount admissions.', $report->events()->where('action', 'unlocked')->value('reason'));
    }

    public function test_the_xlsx_export_has_the_two_row_header_merged_cells_and_the_total(): void
    {
        $this->sheetExamples();
        $this->actingAs($this->admin)->post(route('staff.program-reports.store'), ['program_id' => $this->program->program_id]);

        $response = $this->get(route('staff.program-reports.export', Report::firstOrFail()))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('program-report-pamanang-pelikula.xlsx');

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertNotFalse($zip->getFromName('xl/styles.xml'));
        $zip->close();

        foreach (['PROGRAM', 'NO. OF VIEWERS', 'TICKETS SOLD', 'REGULAR', 'DISCOUNT', 'OCCUPANCY RATE', 'PROGRAM TOTAL', 'Himala'] as $text) {
            $this->assertStringContainsString($text, $sheet);
        }
        $this->assertStringContainsString('<mergeCell ref="H4:I4"/>', $sheet); // NO. OF VIEWERS over M / F
        $this->assertStringContainsString('<mergeCell ref="N4:O4"/>', $sheet); // TICKETS SOLD over REGULAR / DISCOUNT
        $this->assertStringContainsString('<mergeCell ref="A6:A7"/>', $sheet); // the program down its two rows
        $this->assertStringContainsString('<v>240</v>', $sheet);
        $this->assertStringContainsString('<v>21.67</v>', $sheet);
    }

    public function test_only_the_super_admin_manages_staff_accounts(): void
    {
        $new = ['first_name' => 'Ana', 'last_name' => 'Cruz', 'email' => 'ana@cinematheque.test', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass', 'is_active' => '1'];

        $this->actingAs($this->admin)->get(route('staff.users.index'))->assertForbidden();
        $this->post(route('staff.users.store'), $new)->assertForbidden();
        $other = User::factory()->create();
        $this->put(route('staff.users.update', $other), ['first_name' => 'X', 'last_name' => 'Y', 'email' => $other->email, 'is_active' => '0'])->assertForbidden();

        // An admin still edits their own account, but not their role or status.
        $this->put(route('staff.users.update', $this->admin), ['first_name' => 'Renamed', 'last_name' => $this->admin->last_name, 'email' => $this->admin->email, 'role' => 'super_admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Renamed', 'admin'], [$this->admin->fresh()->first_name, $this->admin->fresh()->role]);

        $this->actingAs($this->superAdmin)->post(route('staff.users.store'), $new)->assertRedirect(route('staff.users.index'));
        $this->assertSame('admin', User::where('email', 'ana@cinematheque.test')->value('role'));
        $this->put(route('staff.users.update', $other), ['first_name' => $other->first_name, 'last_name' => $other->last_name, 'email' => $other->email, 'role' => 'admin', 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        // Exactly one Super Admin.
        $this->put(route('staff.users.update', $other), ['first_name' => $other->first_name, 'last_name' => $other->last_name, 'email' => $other->email, 'role' => 'super_admin', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertSame(1, User::where('role', 'super_admin')->count());
    }
}
