<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffUserSeeder::class, // required: default staff accounts ("RBAC")
            SeatSeeder::class,      // required: the venue's 120 physical seats (rows A–J × 1–12)
            CatalogSeeder::class,   // demo: genres, movies, cast, directors
            DemoScreeningSeeder::class, // demo: screenings, reservations, payments, attendance
        ]);
    }
}
