<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 10: screenings */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenings', function (Blueprint $table) {
            $table->id('screening_id');
            $table->string('event_title', 150);
            $table->foreignId('movie_id')->nullable()
                ->constrained('movies', 'movie_id')->nullOnDelete();
            $table->date('event_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['free', 'paid'])->default('free');
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('total_seats')->default(100);
            $table->foreignId('created_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenings');
    }
};
