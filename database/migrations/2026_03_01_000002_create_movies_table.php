<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('synopsis')->nullable();
            $table->string('genre');
            $table->string('language')->default('English');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('age_rating', 10)->nullable();       // PG-13
            $table->decimal('rating', 2, 1)->nullable();        // 4.5 (audience score)
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('status')->default('coming_soon');   // now_showing | coming_soon
            $table->date('release_date')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
