<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 13: reservation_attendees — exactly one declared attendee per reserved seat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_attendees', function (Blueprint $table) {
            $table->id('reservation_attendee_id');
            $table->foreignId('reservation_seat_id')->unique()
                ->constrained('reservation_seats', 'reservation_seat_id')->cascadeOnDelete();
            $table->boolean('is_lead_reserver')->default(false);
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->unsignedTinyInteger('age')->nullable();
            $table->enum('sex', ['M', 'F'])->nullable();
            $table->string('company_school', 150)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('senior_card_no', 30)->nullable();
            $table->boolean('pwd_indicator')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_attendees');
    }
};
