<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\TicketLookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer side - no login needed
|--------------------------------------------------------------------------
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');

// Step 2: date & time
Route::get('/movies/{movie}/book', [BookingController::class, 'showtimes'])->name('booking.showtimes');

// Step 3: seats
Route::get('/showtimes/{showtime}/seats', [BookingController::class, 'seats'])->name('booking.seats');
Route::post('/showtimes/{showtime}/seats', [BookingController::class, 'hold'])->name('booking.hold');

// Step 4: details (+ QR only when the cinema charges)
Route::get('/bookings/{booking}/checkout', [BookingController::class, 'checkout'])->name('booking.checkout');
Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])->name('booking.confirm');
Route::delete('/bookings/{booking}', [BookingController::class, 'cancel'])->name('booking.cancel');

// Step 5: confirmation + printable tickets
Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('booking.show');
Route::get('/bookings/{booking}/tickets', [BookingController::class, 'tickets'])->name('booking.tickets');

// "Tickets" in the navbar: find a booking with reference + email
Route::get('/tickets', [TicketLookupController::class, 'form'])->name('tickets.lookup');
Route::post('/tickets', [TicketLookupController::class, 'find'])->name('tickets.find');

/*
|--------------------------------------------------------------------------
| Admin - the only place with a login
|--------------------------------------------------------------------------
| The login route is named "login" on purpose: Laravel's auth middleware
| redirects guests to route('login') by default.
*/
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
        Route::get('/', fn () => view('admin.dashboard'))->name('admin.dashboard');
        // TODO: movies, cinemas (with QR upload), showtimes, bookings CRUD
    });
});
