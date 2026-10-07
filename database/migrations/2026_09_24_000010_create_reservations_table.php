<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 11: reservations */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('reservation_id');
            $table->foreignId('screening_id')
                ->constrained('screenings', 'screening_id')->restrictOnDelete();
            $table->string('booking_reference', 20)->unique();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending')->index();
            $table->dateTime('reservation_datetime')->useCurrent();
            $table->string('lead_first_name', 50);
            $table->string('lead_middle_name', 50)->nullable();
            $table->string('lead_last_name', 50);
            $table->string('lead_contact_no', 20);
            $table->string('lead_email', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
