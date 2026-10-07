<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revision: online payment through PayMongo (hosted Checkout).
 *
 * provider_session_id  PayMongo Checkout Session id (cs_...). Links this payment to the
 *                      session PayMongo reports on; unique so a session can only ever
 *                      settle one payment.
 * provider_payment_id  PayMongo payment id (pay_...) once the session is paid — the
 *                      reference to quote to PayMongo for disputes/refunds.
 * paid_at              When PayMongo reports the payment as paid.
 *
 * Existing columns are reused: amount, status (pending → verified), payment_channel
 * (now filled with the method PayMongo reports, e.g. gcash/card).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider_session_id', 64)->nullable()->unique()->after('status');
            $table->string('provider_payment_id', 64)->nullable()->after('provider_session_id');
            $table->dateTime('paid_at')->nullable()->after('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_session_id_unique');
            $table->dropColumn(['provider_session_id', 'provider_payment_id', 'paid_at']);
        });
    }
};
