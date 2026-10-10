<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 2 (2026-10-11): reservations are approved automatically; "pending" is gone.
 * Free screening: confirmed on submit. Paid screening: awaiting_payment (seats held while PayMongo
 * processes the payment), then confirmed when PayMongo reports it paid.
 *
 * Existing pending bookings: those with a payment row are paid bookings → awaiting_payment;
 * the rest were free bookings waiting for staff → confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')->where('status', 'pending')
            ->whereExists(fn ($q) => $q->from('payments')->whereColumn('payments.reservation_id', 'reservations.reservation_id'))
            ->update(['status' => 'awaiting_payment']);
        DB::table('reservations')->where('status', 'pending')->update(['status' => 'confirmed']);

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['awaiting_payment', 'confirmed', 'cancelled'])->default('confirmed')->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'awaiting_payment', 'confirmed', 'cancelled'])->default('pending')->change();
        });
    }
};
