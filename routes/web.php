<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\PublicScreeningController;
use App\Http\Controllers\Staff;
use App\Http\Middleware\EnsureStaffIsActive;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/cinemathequecentredavao');

/*
|--------------------------------------------------------------------------
| Customer site — /cinemathequecentredavao (moviegoers never log in)
|--------------------------------------------------------------------------
*/
Route::prefix('cinemathequecentredavao')->group(function () {
    Route::get('/', [PublicScreeningController::class, 'index'])->name('home');
    Route::get('screenings/{screening}', [PublicScreeningController::class, 'show'])->name('screenings.show');
    Route::view('about', 'public.about')->name('about');

    Route::get('screenings/{screening}/reserve', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('screenings/{screening}/reserve/review', [BookingController::class, 'review'])
        ->middleware('throttle:30,1')->name('bookings.review');
    Route::post('screenings/{screening}/reserve', [BookingController::class, 'store'])
        ->middleware('throttle:10,1')->name('bookings.store');

    Route::get('booking', [BookingController::class, 'lookup'])->name('bookings.lookup');
    Route::get('booking/{reservation:booking_reference}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('booking/{reservation:booking_reference}/ticket', [BookingController::class, 'ticket'])->name('bookings.ticket');

    // PayMongo hosted checkout: start (or resume) payment, and the page PayMongo returns to.
    Route::get('booking/{reservation:booking_reference}/pay', [BookingController::class, 'pay'])
        ->middleware('throttle:20,1')->name('bookings.pay');
    Route::get('booking/{reservation:booking_reference}/payment/return', [BookingController::class, 'paymentReturn'])
        ->middleware('throttle:20,1')->name('bookings.payment.return');
});

/*
| PayMongo → server webhook. No CSRF token (PayMongo can't send one); the request is
| authenticated by its Paymongo-Signature header instead.
*/
Route::post('webhooks/paymongo', PayMongoWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->middleware('throttle:60,1')
    ->name('webhooks.paymongo');

/*
|--------------------------------------------------------------------------
| Staff area — /ccdadmin. Not linked from the customer site, but that is NOT the
| protection: every page below requires a logged-in, active staff account.
| AVT and PDO pass identically; policies only add record-state rules.
|--------------------------------------------------------------------------
*/
Route::prefix('ccdadmin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
    });
    Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    Route::middleware(['auth', EnsureStaffIsActive::class])
        ->name('staff.')
        ->group(function () {
            Route::get('/', Staff\DashboardController::class)->name('dashboard');
            Route::get('search', Staff\SearchController::class)->name('search');

            // Screenings workspace (details + attendee checklist + admission on one page)
            Route::resource('screenings', Staff\ScreeningController::class);
            // Review (the customer page as it will look) and Publish, for screenings and films
            Route::get('screenings/{screening}/preview', [Staff\ScreeningController::class, 'preview'])->name('screenings.preview');
            Route::post('screenings/{screening}/publish', [Staff\ScreeningController::class, 'publish'])->name('screenings.publish');
            Route::get('movies/{movie}/preview', [Staff\MovieController::class, 'preview'])->name('movies.preview');
            Route::post('movies/{movie}/publish', [Staff\MovieController::class, 'publish'])->name('movies.publish');

            Route::post('reservation-seats/{reservationSeat}/attendance', [Staff\AttendanceController::class, 'store'])->name('attendances.store');
            Route::post('reservations/{reservation}/admit', [Staff\AttendanceController::class, 'storeGroup'])->name('reservations.admit');
            Route::patch('attendances/{attendance}', [Staff\AttendanceController::class, 'update'])->name('attendances.update');
            Route::delete('attendances/{attendance}', [Staff\AttendanceController::class, 'destroy'])->name('attendances.destroy');

            // Booking actions from the screening roster (the separate Reservations pages now redirect to it)
            Route::get('reservations', [Staff\ReservationController::class, 'index'])->name('reservations.index');
            Route::get('reservations/{reservation}', [Staff\ReservationController::class, 'show'])->name('reservations.show');
            Route::patch('reservations/{reservation}/cancel', [Staff\ReservationController::class, 'cancel'])->name('reservations.cancel');
            Route::post('reservations/{reservation}/resend', [Staff\ReservationController::class, 'resend'])->name('reservations.resend');
            Route::post('reservations/{reservation}/sync-payment', [Staff\ReservationController::class, 'syncPayment'])->name('reservations.sync-payment');

            // Program reports (the Manila format): generate → submit (locked) → unlock request → Super Admin unlock
            Route::get('program-reports', [Staff\ProgramReportController::class, 'index'])->name('program-reports.index');
            Route::post('program-reports', [Staff\ProgramReportController::class, 'store'])->name('program-reports.store');
            Route::get('program-reports/{report}', [Staff\ProgramReportController::class, 'show'])->name('program-reports.show');
            Route::put('program-reports/{report}', [Staff\ProgramReportController::class, 'update'])->name('program-reports.update');
            Route::post('program-reports/{report}/submit', [Staff\ProgramReportController::class, 'submit'])->name('program-reports.submit');
            Route::post('program-reports/{report}/request-unlock', [Staff\ProgramReportController::class, 'requestUnlock'])->name('program-reports.request-unlock');
            Route::post('program-reports/{report}/unlock', [Staff\ProgramReportController::class, 'unlock'])->name('program-reports.unlock');
            Route::get('program-reports/{report}/export', [Staff\ProgramReportController::class, 'export'])->name('program-reports.export');
            // One-click export from the list: the locked snapshot once submitted, otherwise live figures
            Route::get('program-reports/programs/{program}/export.{format}', [Staff\ProgramReportController::class, 'exportProgram'])
                ->whereIn('format', ['xlsx', 'csv'])->name('program-reports.export-program');

            // Date-range summary (attendance and demographics, CSV)
            Route::get('reports', [Staff\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [Staff\ReportController::class, 'export'])->name('reports.export');

            // Settings (directors, cast and genres are typed on the screening/movie forms; no separate admin)
            // Programs are free-text tags on the film form now; the old Programs page lands on the catalog.
            Route::redirect('programs', '/ccdadmin/movies')->name('programs.index');
            // A film's panel (show): poster, details, Edit movie, Add screening schedule and its screenings
            Route::resource('movies', Staff\MovieController::class);
            Route::resource('users', Staff\UserController::class)->except('show', 'destroy');
        });
});
