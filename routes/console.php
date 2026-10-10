<?php

use App\Models\Program;
use App\Models\User;
use App\Services\ProgramReports;
use App\Services\UnpaidReservationExpiry;
use Database\Seeders\ManilaAcceptanceSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional: every web request already expires overdue bookings (ExpireUnpaidReservations).
// This lets the scheduler do it too, e.g. so staff reports are current without a page visit.
Artisan::command('reservations:expire-unpaid', function (UnpaidReservationExpiry $expiry) {
    $this->info($expiry->run().' unpaid booking(s) expired.');
})->purpose('Cancel paid-screening bookings not paid within the payment window and release their seats');

Schedule::command('reservations:expire-unpaid')->everyMinute();

// Acceptance (revision phase 5): generate the acceptance program's report and compare it, row by row,
// with the lines of Manila's February 2026 Free and Paid Screening Report sheets.
Artisan::command('reports:acceptance', function (ProgramReports $reports) {
    $program = Program::where('name', ManilaAcceptanceSeeder::PROGRAM)->first();
    if (! $program) {
        $this->call('db:seed', ['--class' => ManilaAcceptanceSeeder::class, '--force' => true]);
        $program = Program::where('name', ManilaAcceptanceSeeder::PROGRAM)->firstOrFail();
    }

    $report = $program->report;
    if (! $report || ! $report->isLocked()) {
        $by = User::where('role', 'super_admin')->first() ?? User::where('is_active', true)->firstOrFail();
        $report = $reports->generate($program, $by);
    }
    $rows = $report->rows()->orderBy('screening_date')->orderBy('start_time')->get()->values();

    $fields = ['male' => 'M', 'female' => 'F', 'total_audience' => 'Audience', 'occupancy_rate' => 'Occupancy', 'regular_count' => 'Regular', 'discount_count' => 'Discount', 'total_sales' => 'Sales'];
    $table = [];
    $mismatches = 0;
    $check = function (string $label, array $expected, $actual) use (&$table, &$mismatches, $fields) {
        foreach ($fields as $field => $name) {
            if (! array_key_exists($field, $expected)) {
                continue;
            }
            $got = $actual[$field] ?? null;
            $ok = abs((float) $expected[$field] - (float) $got) < 0.005;
            $mismatches += $ok ? 0 : 1;
            $table[] = [$label, $name, $expected[$field], $got, $ok ? 'match' : 'DIFFERENT'];
        }
    };

    foreach (ManilaAcceptanceSeeder::SHEET_ROWS as $i => [$date, $time, $title, $type, $m, $f, $regular, $discount, $sales, $occupancy]) {
        $row = $rows->get($i);
        $label = date('M j', strtotime($date)).' '.date('g:i A', strtotime($time)).' · '.$title;
        if (! $row || $row->film_title !== $title) {
            $mismatches++;
            $table[] = [$label, 'Row', 'present', 'missing', 'DIFFERENT'];

            continue;
        }
        $check($label, ['male' => $m, 'female' => $f, 'total_audience' => $m + $f, 'occupancy_rate' => $occupancy, 'regular_count' => $regular, 'discount_count' => $discount, 'total_sales' => $sales], $row->toArray());
    }
    $check('Program total', ManilaAcceptanceSeeder::SHEET_TOTAL, $reports->totals($rows));

    $this->table(['Line', 'Column', 'Manila sheet', 'System report', 'Result'], $table);
    if ($mismatches) {
        $this->error("{$mismatches} value(s) differ from the Manila sheets.");

        return 1;
    }
    $this->info('All '.count(ManilaAcceptanceSeeder::SHEET_ROWS).' rows and the program total match the Manila sheets. Report: '.route('staff.program-reports.show', $report));

    return 0;
})->purpose('Compare the program report with the Manila February 2026 sheet examples, row by row');
