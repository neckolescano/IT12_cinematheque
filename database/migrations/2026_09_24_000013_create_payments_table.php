<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 14: payments — at most one per reservation; never assumed verified on creation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('reservation_id')->unique()
                ->constrained('reservations', 'reservation_id')->cascadeOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('payment_channel', 30)->nullable();
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
