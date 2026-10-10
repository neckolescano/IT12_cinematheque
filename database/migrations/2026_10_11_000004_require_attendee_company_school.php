<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11): School/Company is required (OWWA). The booking form already
 * required it; now the column does too. Older rows without one are marked "Not provided".
 */
return new class extends Migration
{
    public const NOT_PROVIDED = 'Not provided';

    public function up(): void
    {
        DB::table('reservation_attendees')
            ->where(fn ($q) => $q->whereNull('company_school')->orWhere('company_school', ''))
            ->update(['company_school' => self::NOT_PROVIDED]);

        Schema::table('reservation_attendees', function (Blueprint $table) {
            $table->string('company_school', 150)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_attendees', function (Blueprint $table) {
            $table->string('company_school', 150)->nullable()->change();
        });
    }
};
