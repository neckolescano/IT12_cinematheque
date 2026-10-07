<?php

namespace Database\Seeders;

use App\Models\Seat;
use Illuminate\Database\Seeder;

/** The venue's fixed 120 seats: rows A–L, 1–10. Rows I–L are labelled "Back"; the rest "Main". */
class SeatSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range('A', 'L') as $row) {
            foreach (range(1, 10) as $number) {
                Seat::firstOrCreate(
                    ['seat_label' => $row.$number],
                    ['section' => in_array($row, ['I', 'J', 'K', 'L'], true) ? 'Back' : 'Main'],
                );
            }
        }
    }
}
