<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SparePartsSeeder extends Seeder
{
    public function run(): void
    {
        // Spare parts shop dummy users
        User::firstOrCreate(
            ['email' => 'sparesadmin@spares.test'],
            [
                'name' => 'Spares Admin',
                'password' => Hash::make('Spares123!'),
                'user_type' => 'pos_admin'
            ]
        );
        // Add more spare parts-specific dummy data here
    }
}
