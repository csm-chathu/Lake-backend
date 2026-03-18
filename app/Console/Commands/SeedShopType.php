<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedShopType extends Command
{
    protected $signature = 'db:seed-shop {--shop=}';
    protected $description = 'Seed the database with dummy data for a specific shop type';

    public function handle()
    {
        $shop = $this->option('shop');
        $seeder = null;
        switch ($shop) {
            case 'retail':
                $seeder = \Database\Seeders\RetailShopSeeder::class;
                break;
            case 'pharmacy':
                $seeder = \Database\Seeders\PharmacySeeder::class;
                break;
            case 'restaurant':
                $seeder = \Database\Seeders\RestaurantSeeder::class;
                break;
            case 'spares':
            case 'spareparts':
                $seeder = \Database\Seeders\SparePartsSeeder::class;
                break;
            default:
                return 1;
        }
        $this->call('db:seed', ['--class' => $seeder]);
        return 0;
    }
}
