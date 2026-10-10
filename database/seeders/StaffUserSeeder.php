<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The "RBAC seeder": the default staff accounts.
 *
 * Two roles (users.role): one Super Admin, held by FDCP Manila (locks and unlocks reports,
 * manages staff), and admins for daily operations — one admin per position (AVT, PDO).
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
                [...$account, 'role' => 'admin', 'password' => 'password', 'is_active' => true],
            );
        }

        // The one Super Admin account (FDCP Manila).
        User::updateOrCreate(
            ['email' => 'manila@cinematheque.test'],
            ['first_name' => 'FDCP', 'last_name' => 'Manila', 'position' => null, 'role' => 'super_admin', 'password' => 'password', 'is_active' => true],
        );

        // An inactive account, useful for checking that deactivated staff are locked out.
        User::updateOrCreate(
            ['email' => 'inactive@cinematheque.test'],
            ['first_name' => 'Former', 'last_name' => 'Staff', 'position' => 'PDO', 'password' => 'password', 'is_active' => false],
        );
    }
}
