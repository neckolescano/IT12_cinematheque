<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11):
 * - movies.status and screenings.status (draft | published) for "Save as draft" and Review before Publish.
 *   Existing rows are published, so the customer site does not change.
 * - screenings.partner, agency_type (free text, client decision) and notes: columns of the Manila report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('published')->after('poster_path');
        });

        Schema::table('screenings', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('published')->after('total_seats');
            $table->string('partner', 150)->nullable()->after('status');
            $table->string('agency_type', 100)->nullable()->after('partner');
            $table->text('notes')->nullable()->after('agency_type');
        });
    }

    public function down(): void
    {
        Schema::table('screenings', function (Blueprint $table) {
            $table->dropColumn(['status', 'partner', 'agency_type', 'notes']);
        });

        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
