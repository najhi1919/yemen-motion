<?php

namespace Database\Seeders;

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
        $this->call(AuthRolesSeeder::class);

        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@yemenmotion.com'],
            [
                'name' => 'مدير المنصة',
                'password' => bcrypt('password123'),
            ],
        );

        if (! $superAdmin->hasRole('super-admin')) {
            $superAdmin->assignRole('super-admin');
        }

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ],
        );
    }
}
