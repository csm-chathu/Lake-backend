<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Doctor user
        User::firstOrCreate(
            ['email' => 'doctor@lakeclinic.test'],
            [
                'name' => 'Dr. John Smith',
                'password' => Hash::make('Doctor123!'),
                'user_type' => 'doctor'
            ]
        );

        // Create Cashier user
        User::firstOrCreate(
            ['email' => 'cashier@lakeclinic.test'],
            [
                'name' => 'Sarah Johnson',
                'password' => Hash::make('Cashier123!'),
                'user_type' => 'cashier'
            ]
        );

        // Create POS Admin user
        User::firstOrCreate(
            ['email' => 'posadmin@lakeclinic.test'],
            [
                'name' => 'POS Admin',
                'password' => Hash::make('PosAdmin123!'),
                'user_type' => 'pos_admin'
            ]
        );

        // Create Admin user (Doctor)
        User::firstOrCreate(
            ['email' => 'admin@lakeclinic.test'],
            [
                'name' => 'Clinic Admin',
                'password' => Hash::make('Admin123!'),
                'user_type' => 'doctor'
            ]
        );
    }
}
