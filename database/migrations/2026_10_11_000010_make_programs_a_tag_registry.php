<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programs become free-text tags typed on the film form (no Programs page). The table stays as a hidden
 * registry so "Cinemalaya", "cinemalaya" and "Cinemalaya " are one program (name_key = lowercased, single
 * spaces) and reports and their lock keep a stable record. Same-key duplicates are merged into the oldest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('name_key', 100)->nullable()->after('name');
        });

        $keep = [];
        foreach (DB::table('programs')->orderBy('program_id')->get() as $program) {
            $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $program->name)));
            if (isset($keep[$key])) {
                $into = $keep[$key];
                DB::table('screenings')->where('program_id', $program->program_id)->update(['program_id' => $into]);
                DB::table('movie_program')->where('program_id', $program->program_id)
                    ->whereIn('movie_id', DB::table('movie_program')->where('program_id', $into)->pluck('movie_id'))->delete();
                DB::table('movie_program')->where('program_id', $program->program_id)->update(['program_id' => $into]);
                if (! DB::table('reports')->where('program_id', $into)->exists()) {
                    DB::table('reports')->where('program_id', $program->program_id)->update(['program_id' => $into]);
                }
                if (! DB::table('reports')->where('program_id', $program->program_id)->exists()) {
                    DB::table('programs')->where('program_id', $program->program_id)->delete();

                    continue;
                }
            }
            $keep[$key] ??= $program->program_id;
            DB::table('programs')->where('program_id', $program->program_id)->update(['name_key' => $key]);
        }

        Schema::table('programs', function (Blueprint $table) {
            $table->string('name_key', 100)->nullable(false)->change();
            $table->unique('name_key');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['name_key']);
            $table->dropColumn('name_key');
        });
    }
};
