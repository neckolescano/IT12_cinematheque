<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 17: attendances — the fact of admission only. control_number is recorded by staff, never generated. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id('attendance_id');
            $table->foreignId('reservation_seat_id')->unique()
                ->constrained('reservation_seats', 'reservation_seat_id')->restrictOnDelete();
            $table->string('control_number', 20)->nullable()->unique();
            $table->text('remarks')->nullable();
            $table->dateTime('checked_in_at');
            $table->foreignId('checked_in_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
