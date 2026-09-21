<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('showtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cinema_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->timestamps();

            $table->unique(['cinema_id', 'starts_at']);
            $table->index(['movie_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
