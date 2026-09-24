<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentProofUploadController;
use App\Http\Controllers\PublicScreeningController;
use App\Http\Controllers\Staff;
use App\Http\Middleware\EnsureStaffIsActive;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes — moviegoers never log in
|--------------------------------------------------------------------------
*/
Route::get('/', [PublicScreeningController::class, 'index'])->name('home');
Route::get('/screenings/{screening}', [PublicScreeningController::class, 'show'])->name('screenings.show');

Route::get('/screenings/{screening}/reserve', [BookingController::class, 'create'])->name('bookings.create');
Route::post('/screenings/{screening}/reserve', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')->name('bookings.store');

Route::get('/booking', [BookingController::class, 'lookup'])->name('bookings.lookup');
Route::get('/booking/{reservation:booking_reference}', [BookingController::class, 'show'])->name('bookings.show');
Route::post('/booking/{reservation:booking_reference}/proof', [PaymentProofUploadController::class, 'store'])
    ->middleware('throttle:10,1')->name('bookings.proof.store');

/*
|--------------------------------------------------------------------------
| Staff authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| Staff routes — the entire "RBAC" boundary.
| `auth` = is a staff account; EnsureStaffIsActive = account not deactivated.
| AVT and PDO pass identically; policies add only record-state rules.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsureStaffIsActive::class])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', Staff\DashboardController::class)->name('dashboard');

        Route::resource('screenings', Staff\ScreeningController::class);
        Route::resource('movies', Staff\MovieController::class)->except('show');
        Route::resource('actors', Staff\ActorController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::resource('directors', Staff\DirectorController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::resource('genres', Staff\GenreController::class)->only('index', 'store', 'edit', 'update', 'destroy');
        Route::resource('seats', Staff\SeatController::class)->only('index', 'store', 'destroy');

        Route::get('reservations', [Staff\ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{reservation}', [Staff\ReservationController::class, 'show'])->name('reservations.show');
        Route::patch('reservations/{reservation}/confirm', [Staff\ReservationController::class, 'confirm'])->name('reservations.confirm');
        Route::patch('reservations/{reservation}/cancel', [Staff\ReservationController::class, 'cancel'])->name('reservations.cancel');

        Route::get('payment-proofs', [Staff\PaymentProofController::class, 'index'])->name('payment-proofs.index');
        Route::patch('payment-proofs/{proof}/accept', [Staff\PaymentProofController::class, 'accept'])->name('payment-proofs.accept');
        Route::patch('payment-proofs/{proof}/reject', [Staff\PaymentProofController::class, 'reject'])->name('payment-proofs.reject');

        Route::get('qr-codes', [Staff\PaymentQrCodeController::class, 'index'])->name('qr-codes.index');
        Route::post('qr-codes', [Staff\PaymentQrCodeController::class, 'store'])->name('qr-codes.store');

        Route::post('reservation-seats/{reservationSeat}/attendance', [Staff\AttendanceController::class, 'store'])->name('attendances.store');
        Route::patch('attendances/{attendance}', [Staff\AttendanceController::class, 'update'])->name('attendances.update');

        Route::resource('users', Staff\UserController::class)->except('show', 'destroy');

        Route::get('reports', [Staff\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [Staff\ReportController::class, 'export'])->name('reports.export');
    });
