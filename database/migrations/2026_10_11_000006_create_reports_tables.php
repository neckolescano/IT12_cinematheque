<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11): the Manila report, one per program (client decision: per program,
 * not per month), free and paid screenings together.
 *
 * reports       — the report and its lock: draft → submitted (read-only for admins) → unlocked by
 *                 the Super Admin → submitted again.
 * report_rows   — a frozen copy of each screening's line when generated, so a submitted report
 *                 never changes when bookings change later.
 * report_events — who generated, edited, submitted, asked to unlock or unlocked it, and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('program_id')->unique()->constrained('programs', 'program_id')->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'unlocked'])->default('draft');
            $table->foreignId('generated_by')->constrained('users', 'user_id')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('unlocked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_rows', function (Blueprint $table) {
            $table->id('report_row_id');
            $table->foreignId('report_id')->constrained('reports', 'report_id')->cascadeOnDelete();
            $table->foreignId('screening_id')->nullable()->constrained('screenings', 'screening_id')->nullOnDelete();
            $table->enum('type', ['free', 'paid']);
            $table->date('screening_date');
            $table->time('start_time');
            $table->string('film_title', 150);
            $table->unsignedSmallInteger('films_count')->default(1);
            $table->unsignedInteger('male')->default(0);
            $table->unsignedInteger('female')->default(0);
            $table->unsignedInteger('pwd')->default(0);
            $table->unsignedInteger('senior')->default(0);
            $table->unsignedInteger('total_audience')->default(0);
            $table->decimal('occupancy_rate', 5, 2)->default(0);
            $table->unsignedInteger('regular_count')->default(0);
            $table->unsignedInteger('discount_count')->default(0);
            $table->decimal('total_sales', 10, 2)->default(0);
            $table->string('partner', 150)->nullable();
            $table->string('agency_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->index(['report_id', 'screening_date']);
        });

        Schema::create('report_events', function (Blueprint $table) {
            $table->id('event_id');
            $table->foreignId('report_id')->constrained('reports', 'report_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', 'user_id')->restrictOnDelete();
            $table->enum('action', ['generated', 'edited', 'submitted', 'unlock_requested', 'unlocked', 'resubmitted']);
            $table->text('reason')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_events');
        Schema::dropIfExists('report_rows');
        Schema::dropIfExists('reports');
    }
};
