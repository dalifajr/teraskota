<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Admin Teras Kota Berlian Makmur',
                'email' => 'admin@teraskota.local',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['username' => 'kasir'],
            [
                'name' => 'Kasir POS Teras Kota',
                'email' => 'kasir@teraskota.local',
                'password' => Hash::make('kasir123'),
                'role' => 'kasir',
            ]
        );
    }
}
