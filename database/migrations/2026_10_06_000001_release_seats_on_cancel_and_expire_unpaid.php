<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decisions 5a + 5b (2026-10-06): cancelled reservations release their seats, and unpaid
 * bookings for paid screenings expire after 15 minutes.
 *
 * reservations.cancellation_reason  why a booking was cancelled: 'staff' or 'payment_expired'
 * reservations.cancelled_at         when it was cancelled
 * reservation_seats.released_at     set when the booking is cancelled; the row (and its
 *                                   attendee and any attendance) stays as history
 * reservation_seats.held_seat_id    generated: seat_id while held, NULL once released.
 *                                   UNIQUE(screening_id, held_seat_id) replaces
 *                                   UNIQUE(screening_id, seat_id), so the database still
 *                                   blocks double booking but a released seat can be
 *                                   booked again (UNIQUE allows many NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('cancellation_reason', ['staff', 'payment_expired'])->nullable()->after('status');
            $table->dateTime('cancelled_at')->nullable()->after('cancellation_reason');
        });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dateTime('released_at')->nullable()->after('seat_id');
            $table->unsignedBigInteger('held_seat_id')->nullable()
                ->storedAs('IF(`released_at` IS NULL, `seat_id`, NULL)')->after('released_at');
            // Added before the old key is dropped: the screening_id foreign key needs an index.
            $table->unique(['screening_id', 'held_seat_id'], 'reservation_seats_held_unique');
        });

        // Everything cancelled so far was cancelled by staff; release those seats now.
        DB::table('reservations')->where('status', 'cancelled')->update(['cancellation_reason' => 'staff']);
        DB::table('reservation_seats')
            ->whereIn('reservation_id', DB::table('reservations')->where('status', 'cancelled')->select('reservation_id'))
            ->update(['released_at' => now()]);

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropUnique(['screening_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        // Fails if a released seat has since been booked again (the old key forbids that).
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->unique(['screening_id', 'seat_id']);
        });

        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropUnique('reservation_seats_held_unique');
            $table->dropColumn(['held_seat_id', 'released_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['cancellation_reason', 'cancelled_at']);
        });
    }
};
