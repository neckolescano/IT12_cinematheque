<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cinemas', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // "Cinema 1"
            $table->unsignedTinyInteger('seat_rows')->default(10);     // A..J
            $table->unsignedTinyInteger('seats_per_row')->default(12); // 6 left + 6 right
            $table->decimal('ticket_price', 8, 2)->default(0);         // 0 = free
            $table->boolean('requires_payment')->default(false);
            $table->string('payment_qr_path')->nullable();             // uploaded by admin
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinemas');
    }
};
