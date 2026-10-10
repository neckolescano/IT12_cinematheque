<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11): one seat = one ticket, priced on its own.
 * unit_price is the screening price when booked; discount_type is pwd or senior when the
 * attendee's ID gives the 20% discount; amount_due is what that ticket costs. Stored at booking
 * time, so a later price change never rewrites past sales.
 *
 * Existing tickets: no discount was ever applied, so amount_due = unit_price = the screening
 * price (0 for free screenings). (Ticket control numbers are left out, client decision.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->decimal('unit_price', 8, 2)->default(0)->after('seat_id');
            $table->enum('discount_type', ['none', 'pwd', 'senior'])->default('none')->after('unit_price');
            $table->decimal('amount_due', 8, 2)->default(0)->after('discount_type');
        });

        DB::table('reservation_seats')
            ->join('screenings', 'screenings.screening_id', '=', 'reservation_seats.screening_id')
            ->where('screenings.type', 'paid')
            ->update([
                'reservation_seats.unit_price' => DB::raw('COALESCE(screenings.price, 0)'),
                'reservation_seats.amount_due' => DB::raw('COALESCE(screenings.price, 0)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('reservation_seats', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'discount_type', 'amount_due']);
        });
    }
};
