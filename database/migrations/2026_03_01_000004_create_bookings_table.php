<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // No customer accounts: a booking stores the guest's details directly.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 10)->unique();
            $table->foreignId('showtime_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('held');          // held | confirmed | cancelled | expired
            $table->string('payment_status')->default('free');  // free | unverified | paid
            $table->decimal('total_amount', 8, 2)->default(0);
            $table->timestamp('expires_at')->nullable();        // only while status = held
            $table->timestamps();
        });

        Schema::create('booking_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('showtime_id')->constrained()->cascadeOnDelete();
            $table->string('seat_label', 4);                    // J6

            // The database itself prevents two people from getting the same seat.
            $table->unique(['showtime_id', 'seat_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_seats');
        Schema::dropIfExists('bookings');
    }
};
