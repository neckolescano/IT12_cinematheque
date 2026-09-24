<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 6: movie_director (pivot, composite primary key) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_director', function (Blueprint $table) {
            $table->foreignId('movie_id')->constrained('movies', 'movie_id')->cascadeOnDelete();
            $table->foreignId('director_id')->constrained('directors', 'director_id')->cascadeOnDelete();
            $table->primary(['movie_id', 'director_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_director');
    }
};
