<?php

use App\Services\UnpaidReservationExpiry;
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
