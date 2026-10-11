<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan db:seed` / `migrate --seed`: the essential data everywhere, plus the demo data outside
 * production. In production only the essential data is loaded — never demo accounts with the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EssentialSeeder::class); // the venue's 120 seats

        if (app()->isProduction()) {
            $this->command?->warn('Production: demo data skipped. Create the first account with php artisan staff:create-admin.');

            return;
        }

        $this->call(DemoSeeder::class);      // demo staff accounts, film catalog, screenings and bookings
    }
}
