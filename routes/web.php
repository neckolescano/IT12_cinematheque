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

    Route::get('screenings/{screening}/reserve', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('screenings/{screening}/reserve', [BookingController::class, 'store'])
        ->middleware('throttle:10,1')->name('bookings.store');

    Route::get('booking', [BookingController::class, 'lookup'])->name('bookings.lookup');
    Route::get('booking/{reservation:booking_reference}', [BookingController::class, 'show'])->name('bookings.show');

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

            // Screenings workspace (details + attendee checklist + admission on one page)
            Route::resource('screenings', Staff\ScreeningController::class);
            Route::post('screenings/{screening}/approve-pending', [Staff\ScreeningController::class, 'approvePending'])->name('screenings.approve-pending');

            Route::post('reservation-seats/{reservationSeat}/attendance', [Staff\AttendanceController::class, 'store'])->name('attendances.store');
            Route::patch('attendances/{attendance}', [Staff\AttendanceController::class, 'update'])->name('attendances.update');
            Route::delete('attendances/{attendance}', [Staff\AttendanceController::class, 'destroy'])->name('attendances.destroy');

            // Reservations across all screenings
            Route::get('reservations', [Staff\ReservationController::class, 'index'])->name('reservations.index');
            Route::get('reservations/{reservation}', [Staff\ReservationController::class, 'show'])->name('reservations.show');
            Route::patch('reservations/{reservation}/confirm', [Staff\ReservationController::class, 'confirm'])->name('reservations.confirm');
            Route::patch('reservations/{reservation}/cancel', [Staff\ReservationController::class, 'cancel'])->name('reservations.cancel');
            Route::post('reservations/{reservation}/resend', [Staff\ReservationController::class, 'resend'])->name('reservations.resend');
            Route::post('reservations/{reservation}/sync-payment', [Staff\ReservationController::class, 'syncPayment'])->name('reservations.sync-payment');

            Route::get('reports', [Staff\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [Staff\ReportController::class, 'export'])->name('reports.export');

            // Settings
            Route::resource('movies', Staff\MovieController::class)->except('show');
            Route::resource('actors', Staff\ActorController::class)->only('index', 'store', 'edit', 'update', 'destroy');
            Route::resource('directors', Staff\DirectorController::class)->only('index', 'store', 'edit', 'update', 'destroy');
            Route::resource('genres', Staff\GenreController::class)->only('index', 'store', 'edit', 'update', 'destroy');
            Route::resource('seats', Staff\SeatController::class)->only('index', 'store', 'destroy');
            Route::resource('users', Staff\UserController::class)->except('show', 'destroy');
        });
});
