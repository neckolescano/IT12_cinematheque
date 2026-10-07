<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision 5c (2026-10-06): remove the tables of the retired QR + screenshot payment flow.
 * PayMongo replaced that flow on 2026-09-26 and nothing has read or written them since.
 * down() recreates them exactly as 2026_09_24_000014 / 000015 did (empty).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payment_qr_codes');
    }

    public function down(): void
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

        Schema::create('payment_qr_codes', function (Blueprint $table) {
            $table->id('qr_code_id');
            $table->string('qr_image', 255);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('uploaded_at')->useCurrent();
        });
    }
};
