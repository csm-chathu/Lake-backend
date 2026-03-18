<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClinicSetting;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default seeding for vet/clinic
        $this->call([
            UserSeeder::class,
        ]);
        \App\Models\ClinicSetting::firstOrCreate(
            [],
            [
                'name' => 'THE LAKE ANIMAL CLINIC',
                'phone' => '071 730 7641',
                'email' => null,
                'address' => 'Wewa Rd, Anuradhapura',
                'description' => 'Your trusted partner in pet healthcare. Manage appointments, medical records, and care for your beloved pets with confidence.',
                'pos_description' => 'POS System for billing, sales, and day-end operations.',
                'timezone' => config('app.timezone'),
                'currency_code' => 'LKR',
                'logo_url' => 'tr-logo.png',
                'hero_image_url' => 'vet-clinic-hero.jpg',
                'sms_sender_id' => config('sms.sender_id')
            ]
        );
    }
}
