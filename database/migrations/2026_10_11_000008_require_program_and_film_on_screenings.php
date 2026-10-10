<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revision phase 3 (2026-10-11): every screening is filed under one program and shows one film record.
 *
 * - A screening without a film (a shorts block, a talk) becomes a film record with the screening's title,
 *   so it has a poster tile and a film panel like any other film. films_count says how many films the
 *   block holds (the Manila report's "No. of films"; 1 for an ordinary screening).
 * - A screening without a program goes to "Unassigned" for staff to re-file.
 * - Each screening's film is linked to the screening's program (movie_program).
 * - movie_id and program_id become NOT NULL; a film with screenings can no longer be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenings', function (Blueprint $table) {
            $table->unsignedTinyInteger('films_count')->default(1)->after('movie_id');
        });

        foreach (DB::table('screenings')->whereNull('movie_id')->get() as $screening) {
            $movieId = DB::table('movies')->whereRaw('LOWER(title) = ?', [mb_strtolower($screening->event_title)])->value('movie_id')
                ?? DB::table('movies')->insertGetId(['title' => mb_substr($screening->event_title, 0, 150), 'status' => 'published']);
            DB::table('screenings')->where('screening_id', $screening->screening_id)->update(['movie_id' => $movieId]);
        }

        if (DB::table('screenings')->whereNull('program_id')->exists()) {
            $unassigned = DB::table('programs')->where('name', 'Unassigned')->value('program_id')
                ?? DB::table('programs')->insertGetId([
                    'name' => 'Unassigned',
                    'description' => 'Screenings created before programs existed. Move them to their real program.',
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('screenings')->whereNull('program_id')->update(['program_id' => $unassigned]);
        }

        DB::table('movie_program')->insertOrIgnore(
            DB::table('screenings')->select('movie_id', 'program_id')->distinct()->get()
                ->map(fn ($row) => ['movie_id' => $row->movie_id, 'program_id' => $row->program_id])->all()
        );

        Schema::table('screenings', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
        });
        Schema::table('screenings', function (Blueprint $table) {
            $table->unsignedBigInteger('movie_id')->nullable(false)->change();
            $table->unsignedBigInteger('program_id')->nullable(false)->change();
            $table->foreign('movie_id')->references('movie_id')->on('movies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('screenings', function (Blueprint $table) {
            $table->dropForeign(['movie_id']);
        });
        Schema::table('screenings', function (Blueprint $table) {
            $table->unsignedBigInteger('movie_id')->nullable()->change();
            $table->unsignedBigInteger('program_id')->nullable()->change();
            $table->foreign('movie_id')->references('movie_id')->on('movies')->nullOnDelete();
            $table->dropColumn('films_count');
        });
    }
};
