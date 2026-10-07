<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 16: payment_qr_codes — staff-managed display content, not linked to payments. Single active row enforced in PaymentQrCodeController. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_qr_codes', function (Blueprint $table) {
            $table->id('qr_code_id');
            $table->string('qr_image', 255);
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')
                ->constrained('users', 'user_id')->restrictOnDelete();
            $table->dateTime('uploaded_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_qr_codes');
    }
};
