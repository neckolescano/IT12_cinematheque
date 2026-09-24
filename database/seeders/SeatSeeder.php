<?php

namespace Database\Seeders;

use App\Models\Seat;
use Illuminate\Database\Seeder;

/** 100 seats: rows A–J, 1–10. Rows I–J are labelled "Back"; the rest "Main". */
class SeatSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range('A', 'J') as $row) {
            foreach (range(1, 10) as $number) {
                Seat::firstOrCreate(
                    ['seat_label' => $row.$number],
                    ['section' => in_array($row, ['I', 'J'], true) ? 'Back' : 'Main'],
                );
            }
        }
    }
}
