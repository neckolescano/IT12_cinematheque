<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision: admission no longer records the official physical-ticket control number.
 * That number belongs to FDCP's own ticketing process, outside this system.
 * The system's booking_reference (reservations table) remains the admission reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_control_number_unique');
            $table->dropColumn('control_number');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('control_number', 20)->nullable()->unique()->after('reservation_seat_id');
        });
    }
};
