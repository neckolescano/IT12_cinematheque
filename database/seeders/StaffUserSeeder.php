<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The "RBAC seeder".
 *
 * Per the RBAC spec there are no roles/permissions tables: being an active row
 * in `users` is the only access tier. So seeding "roles" means seeding the
 * default staff accounts — one per position — which have identical access.
 *
 * Change these passwords immediately after the first login.
 */
class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['first_name' => 'Default', 'last_name' => 'AVT', 'email' => 'avt@cinematheque.test', 'position' => 'AVT'],
            ['first_name' => 'Default', 'last_name' => 'PDO', 'email' => 'pdo@cinematheque.test', 'position' => 'PDO'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [...$account, 'password' => 'password', 'is_active' => true],
            );
        }

        // An inactive account, useful for checking that deactivated staff are locked out.
        User::updateOrCreate(
            ['email' => 'inactive@cinematheque.test'],
            ['first_name' => 'Former', 'last_name' => 'Staff', 'position' => 'PDO', 'password' => 'password', 'is_active' => false],
        );
    }
}
