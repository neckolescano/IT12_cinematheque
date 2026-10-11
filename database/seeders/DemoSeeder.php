<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Development / demonstration data only: default staff accounts with the password "password", the film
 * catalog with posters, and sample screenings, bookings, payments and admissions. Never for production.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StaffUserSeeder::class,     // demo staff accounts (avt@, pdo@, manila@, inactive@ cinematheque.test)
            CatalogSeeder::class,       // genres, movies, cast, directors, posters
            DemoScreeningSeeder::class, // screenings, reservations, payments, attendance
        ]);
    }
}
