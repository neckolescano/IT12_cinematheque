<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The hall is 10 rows (A–J) of 12 seats (1–12), not 12 rows of 10 (user decision 2026-10-08). Still 120 seats.
 * A1–J10 keep their labels; the 20 seats added as rows K and L become seats 11 and 12 of each row:
 * K1–K10 → A11–J11, L1–L10 → A12–J12. Seat ids are unchanged, so every reservation keeps its seat.
 * Sections follow the row: I–J are "Back", the rest "Main".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['K' => 11, 'L' => 12] as $oldRow => $number) {
            foreach (range(1, 10) as $n) {
                $row = chr(ord('A') + $n - 1);
                DB::table('seats')->where('seat_label', $oldRow.$n)
                    ->update(['seat_label' => $row.$number, 'section' => in_array($row, ['I', 'J'], true) ? 'Back' : 'Main']);
            }
        }
    }

    public function down(): void
    {
        foreach (['K' => 11, 'L' => 12] as $oldRow => $number) {
            foreach (range(1, 10) as $n) {
                DB::table('seats')->where('seat_label', chr(ord('A') + $n - 1).$number)
                    ->update(['seat_label' => $oldRow.$n, 'section' => 'Back']);
            }
        }
    }
};
