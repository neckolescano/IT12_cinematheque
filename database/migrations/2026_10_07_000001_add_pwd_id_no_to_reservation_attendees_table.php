<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Version 5 spec: the reservation form asks for an optional PWD ID number instead of a
 * yes/no checkbox. pwd_indicator is kept and is set by the app when an ID number is given.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_attendees', function (Blueprint $table) {
            $table->string('pwd_id_no', 30)->nullable()->after('senior_card_no');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_attendees', function (Blueprint $table) {
            $table->dropColumn('pwd_id_no');
        });
    }
};
