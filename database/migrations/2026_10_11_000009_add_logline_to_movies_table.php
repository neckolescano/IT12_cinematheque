<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A film's one-line logline for the home banner (optional). When it is empty the banner shows the
 * synopsis's first sentence instead (Movie::loglineText()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('logline', 200)->nullable()->after('synopsis');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('logline');
        });
    }
};
