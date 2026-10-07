<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The venue has exactly 120 seats (user decision 2026-10-07): rows A–L × 1–10.
 * Adds rows K and L to the original 100 (A–J) without touching existing seats, so every
 * reservation keeps its seat_id; and sets every screening's capacity to 120.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['K', 'L'] as $row) {
            foreach (range(1, 10) as $n) {
                DB::table('seats')->insertOrIgnore(['seat_label' => $row.$n, 'section' => 'Back']);
            }
        }

        DB::table('screenings')->update(['total_seats' => 120]);
    }

    public function down(): void
    {
        DB::table('screenings')->update(['total_seats' => 100]);
        DB::table('seats')->whereIn('seat_label', collect(['K', 'L'])->crossJoin(range(1, 10))->map(fn ($p) => $p[0].$p[1]))
            ->whereNotExists(fn ($q) => $q->from('reservation_seats')->whereColumn('reservation_seats.seat_id', 'seats.seat_id'))
            ->delete();
    }
};
