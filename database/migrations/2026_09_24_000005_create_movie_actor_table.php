<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 4: movie_actor (pivot, composite primary key) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movie_actor', function (Blueprint $table) {
            $table->foreignId('movie_id')->constrained('movies', 'movie_id')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('actors', 'actor_id')->cascadeOnDelete();
            $table->primary(['movie_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_actor');
    }
};
