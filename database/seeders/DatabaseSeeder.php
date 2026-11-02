<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun Admin Utama
        User::firstOrCreate(
            ['email' => 'admin@pos.test'],
            [
                'name' => 'Store Owner',
                'password' => 'password',
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ]
        );

        // Akun Kasir Default
        User::firstOrCreate(
            ['email' => 'cashier@pos.test'],
            [
                'name' => 'Kasir 1',
                'password' => 'password',
                'role' => UserRole::CASHIER,
                'is_active' => true,
            ]
        );
    }
}
