<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 1 (2026-10-11): Program is the parent of films and screenings.
 *
 * A film can appear in more than one program (a festival may re-screen it), so programs and
 * movies are many-to-many (movie_program). Each screening belongs to exactly one program:
 * the Manila report is built per program from its screenings.
 *
 * Existing films and screenings are put in an "Unassigned" program, for staff to re-file.
 * screenings.program_id stays nullable until the screening form asks for a program.
 */
return new class extends Migration
{
    public const UNASSIGNED = 'Unassigned';

    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id('program_id');
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('movie_program', function (Blueprint $table) {
            $table->foreignId('movie_id')->constrained('movies', 'movie_id')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs', 'program_id')->cascadeOnDelete();
            $table->primary(['movie_id', 'program_id']);
        });

        Schema::table('screenings', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('movie_id')
                ->constrained('programs', 'program_id')->restrictOnDelete();
        });

        // Re-file what already exists under one placeholder program.
        if (DB::table('movies')->exists() || DB::table('screenings')->exists()) {
            $id = DB::table('programs')->insertGetId([
                'name' => self::UNASSIGNED,
                'description' => 'Films and screenings created before programs existed. Move them to their real program.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('movie_program')->insertUsing(['movie_id', 'program_id'],
                DB::table('movies')->select('movie_id', DB::raw((int) $id)));
            DB::table('screenings')->update(['program_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('screenings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
        });
        Schema::dropIfExists('movie_program');
        Schema::dropIfExists('programs');
    }
};
