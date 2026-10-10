<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\ManilaAcceptanceSeeder;
use Database\Seeders\SeatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revision phase 5, acceptance: the program report reproduces the lines of Manila's February 2026
 * Free and Paid Screening Report sheets (26 viewers = 21.67%; 4 regular + 1 discount at ₱50 = ₱240).
 */
class RevisionAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_report_matches_the_manila_sheets_row_by_row(): void
    {
        $this->seed(SeatSeeder::class);
        User::factory()->superAdmin()->create();

        $this->artisan('reports:acceptance')
            ->expectsOutputToContain('All 4 rows and the program total match the Manila sheets.')
            ->assertSuccessful();

        $report = Report::firstOrFail();
        $this->assertSame(ManilaAcceptanceSeeder::PROGRAM, $report->program->name);
        $this->assertSame(
            [['21.67', 26], ['32.50', 39], ['1.67', 2], ['4.17', 5]],
            $report->rows()->orderBy('screening_date')->orderBy('start_time')->get()->map(fn ($r) => [$r->occupancy_rate, $r->total_audience])->all()
        );

        // Seeding again adds nothing; the comparison still passes on the existing report.
        $this->seed(ManilaAcceptanceSeeder::class);
        $this->assertSame(1, Program::where('name', ManilaAcceptanceSeeder::PROGRAM)->count());
        $this->artisan('reports:acceptance')->assertSuccessful();
    }

    public function test_a_difference_from_the_sheets_is_reported(): void
    {
        $this->seed(SeatSeeder::class);
        User::factory()->superAdmin()->create();
        $this->seed(ManilaAcceptanceSeeder::class);

        // One admission undone: the first free row now has 25 viewers instead of 26.
        \App\Models\Attendance::orderBy('attendance_id')->first()->delete();

        $this->artisan('reports:acceptance')
            ->expectsOutputToContain('differ from the Manila sheets')
            ->assertFailed();
    }
}
