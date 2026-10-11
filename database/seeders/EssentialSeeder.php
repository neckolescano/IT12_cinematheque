<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production / first-run data: only what the system cannot work without, and safe to run more than once.
 *
 * - The venue's 120 seats (rows A–J × 1–12).
 * - The default programs are created by their migrations, so they need nothing here.
 *
 * No staff accounts and no passwords: the first Super Admin is created with `php artisan staff:create-admin`,
 * and that account creates the AVT and PDO accounts from the Staff accounts page.
 *
 *   php artisan migrate --force
 *   php artisan db:seed --class=EssentialSeeder --force
 */
class EssentialSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SeatSeeder::class,
        ]);
    }
}
