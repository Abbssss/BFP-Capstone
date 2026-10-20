<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@bfp.local'],
            [
                'name' => 'Super Admin',
                'role' => 'super_admin',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'officer@bfp.local'],
            [
                'name' => 'Maintenance Officer',
                'role' => 'maintenance',
                'status' => 'active',
                'password' => Hash::make('password'),
            ]
        );
    }
}
