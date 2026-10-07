<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home showcase (2026-10-07): a one-sentence curator's note ("why see it") shown on the poster card
 * and spotlight, and a trailer link (YouTube/Vimeo play in a modal; other links open in a new tab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->string('curator_note', 200)->nullable()->after('synopsis');
            $table->string('trailer_url', 255)->nullable()->after('curator_note');
        });
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn(['curator_note', 'trailer_url']);
        });
    }
};
