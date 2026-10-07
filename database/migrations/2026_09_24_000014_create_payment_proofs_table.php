<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 15: payment_proofs — one row per uploaded screenshot; history is never overwritten. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id('proof_id');
            $table->foreignId('payment_id')
                ->constrained('payments', 'payment_id')->cascadeOnDelete();
            $table->string('proof_image', 255);
            $table->dateTime('submitted_at')->useCurrent();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
