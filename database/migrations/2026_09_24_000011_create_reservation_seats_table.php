<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 12: reservation_seats — one row per held seat. UNIQUE(screening_id, seat_id) is the double-booking guarantee. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_seats', function (Blueprint $table) {
            $table->id('reservation_seat_id');
            $table->foreignId('reservation_id')
                ->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->foreignId('screening_id')
                ->constrained('screenings', 'screening_id')->restrictOnDelete();
            $table->foreignId('seat_id')
                ->constrained('seats', 'seat_id')->restrictOnDelete();
            $table->unique(['screening_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_seats');
    }
};
