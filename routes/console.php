<?php

use App\Models\Program;
use App\Models\User;
use App\Services\ProgramReports;
use App\Services\UnpaidReservationExpiry;
use Database\Seeders\ManilaAcceptanceSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional: every web request already expires overdue bookings (ExpireUnpaidReservations).
// This lets the scheduler do it too, e.g. so staff reports are current without a page visit.
Artisan::command('reservations:expire-unpaid', function (UnpaidReservationExpiry $expiry) {
    $this->info($expiry->run().' unpaid booking(s) expired.');
})->purpose('Cancel paid-screening bookings not paid within the payment window and release their seats');

Schedule::command('reservations:expire-unpaid')->everyMinute();

// First-run setup on a clean (production) database: the one account that can create every other one.
// Interactive by default; with --no-interaction (deployment scripts) the details come from the options and
// the password from INITIAL_ADMIN_PASSWORD. --if-none makes it safe to run on every deploy.
Artisan::command('staff:create-admin
        {--email= : Login email}
        {--first-name= : First name}
        {--last-name= : Last name}
        {--role=super_admin : super_admin or admin}
        {--position= : AVT or PDO (admins)}
        {--if-none : Do nothing when a Super Admin already exists}', function () {
    if ($this->option('if-none') && User::where('role', 'super_admin')->exists()) {
        $this->info('A Super Admin already exists; nothing to do.');

        return 0;
    }

    $interactive = $this->input->isInteractive();
    $data = [
        'email' => $this->option('email') ?: ($interactive ? $this->ask('Email') : env('INITIAL_ADMIN_EMAIL')),
        'first_name' => $this->option('first-name') ?: ($interactive ? $this->ask('First name') : env('INITIAL_ADMIN_FIRST_NAME', 'System')),
        'last_name' => $this->option('last-name') ?: ($interactive ? $this->ask('Last name') : env('INITIAL_ADMIN_LAST_NAME', 'Administrator')),
        'role' => $this->option('role'),
        'position' => $this->option('position') ?: null,
    ];
    if ($interactive) {
        $data['password'] = $this->secret('Password (at least 12 characters)');
        $data['password_confirmation'] = $this->secret('Repeat the password');
    } else {
        $data['password'] = $data['password_confirmation'] = (string) env('INITIAL_ADMIN_PASSWORD');
    }

    $validator = Validator::make($data, [
        'email' => ['required', 'email', 'max:100', 'unique:users,email'],
        'first_name' => ['required', 'string', 'max:50'],
        'last_name' => ['required', 'string', 'max:50'],
        'role' => ['required', 'in:'.implode(',', User::ROLES)],
        'position' => ['nullable', 'in:'.implode(',', User::POSITIONS)],
        'password' => ['required', 'string', 'min:12', 'confirmed'],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }

    unset($data['password_confirmation']);
    $user = User::create([...$data, 'is_active' => true]); // the password is hashed by the model's cast
    $this->info("Created {$user->role} account {$user->email}. Sign in at ".route('login').' and change the password if it was shared.');

    return 0;
})->purpose('Create the first staff account (Super Admin by default) on a fresh database');

// Mail check: sends one plain message with the current mail settings (e.g. after switching to Gmail SMTP).
Artisan::command('mail:test {to : Recipient address}', function (string $to) {
    $this->line('Mailer: '.config('mail.default').' · host: '.(config('mail.mailers.'.config('mail.default').'.host') ?? '—')
        .' · from: '.config('mail.from.address'));
    try {
        Mail::raw('This is a test email from '.config('app.name').' ('.config('app.url').'). If you can read this, email delivery works.',
            fn ($message) => $message->to($to)->subject(config('app.name').' – test email'));
    } catch (Throwable $e) {
        $this->error('Sending failed: '.$e->getMessage());

        return 1;
    }
    $this->info("Sent to {$to}. Check the inbox (and the Spam folder).");

    return 0;
})->purpose('Send a test email with the current mail settings');

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
