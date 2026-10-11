<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development / demonstration data only: default staff accounts with the password "password", the film
 * catalog (CCD's Cinemalaya 2026 schedule), and sample screenings, bookings, payments and admissions. Never for production.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffUserSeeder::class,     // demo staff accounts (avt@, pdo@, manila@, inactive@ cinematheque.test)
            CatalogSeeder::class,       // genres, the six Cinemalaya 2026 films, cast, directors
            DemoScreeningSeeder::class, // screenings, reservations, payments, attendance
        ]);
    }
}
