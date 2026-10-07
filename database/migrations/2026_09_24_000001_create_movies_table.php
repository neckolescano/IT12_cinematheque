<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 2: movies — optional catalog. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->id('movie_id');
            $table->string('title', 150);
            $table->unsignedSmallInteger('runtime_minutes')->nullable();
            $table->string('rating', 10)->nullable();
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->text('synopsis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
