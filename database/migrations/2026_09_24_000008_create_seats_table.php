<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Table 9: seats — physical venue seats, reused across screenings. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id('seat_id');
            $table->string('seat_label', 10)->unique();
            $table->string('section', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
    }
};
