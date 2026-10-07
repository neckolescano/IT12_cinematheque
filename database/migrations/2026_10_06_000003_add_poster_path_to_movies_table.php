<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision 5d (2026-10-06): real poster images.
 * poster_path is relative to the "public" disk (storage/app/public), e.g. posters/himala.jpg.
 * Screenings without a film, or films without a poster, keep the generated gradient tile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('poster_path', 255)->nullable()->after('synopsis');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('poster_path');
        });
    }
};
