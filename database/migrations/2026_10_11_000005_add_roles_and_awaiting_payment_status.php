<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11):
 * - users.role: super_admin (one account, held by FDCP Manila; client decision) or admin (staff).
 *   position (AVT/PDO) stays a job title only. Every existing account is an admin.
 * - reservations.status gains awaiting_payment: a paid booking holds its seats while PayMongo
 *   processes payment (client decision). pending stays until phase 2 moves bookings to
 *   auto-approval; that phase converts the remaining pending rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'admin'])->default('admin')->after('position');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'awaiting_payment', 'confirmed', 'cancelled'])->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('reservations')->where('status', 'awaiting_payment')->update(['status' => 'pending']);

        Schema::table('reservations', function (Blueprint $table) {
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
