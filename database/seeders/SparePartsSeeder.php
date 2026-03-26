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

        // Add sample supplier
        $supplier = \App\Models\Supplier::create([
            'name' => 'Auto Spares Supplier',
            'contact_person' => 'Mr. Perera',
            'email' => 'supplier@spares.test',
            'phone' => '0779876543',
            'address' => 'Colombo 10',
            'notes' => 'Main supplier for vehicle spare parts.'
        ]);

        // Clean up for repeatable seeding (disabled to preserve existing data)
        // \DB::table('purchase_orders')->truncate();
        // \DB::table('goods_receipts')->truncate();
        // \DB::table('medicine_brand_batches')->truncate();
        // \DB::table('medicine_brands')->truncate();
        // \DB::table('medicines')->truncate();
        if (\DB::table('medicine_brands')->count() === 0) {
            \Log::info('medicine_brands table is empty at start of SparePartsSeeder');
        }

        // Sample spare parts items (car, van, hybrid)
        $spareParts = [
            // Toyota/KDH specific parts
            ['name' => 'Toyota KDH Air Filter', 'desc' => 'Air filter for Toyota KDH van'],
            ['name' => 'Toyota KDH Oil Filter', 'desc' => 'Oil filter for Toyota KDH van'],
            ['name' => 'Toyota KDH Fuel Filter', 'desc' => 'Fuel filter for Toyota KDH van'],
            ['name' => 'Toyota KDH Brake Pad', 'desc' => 'Brake pad for Toyota KDH van'],
            ['name' => 'Toyota KDH Timing Belt', 'desc' => 'Timing belt for Toyota KDH van'],
            ['name' => 'Toyota KDH Water Pump', 'desc' => 'Water pump for Toyota KDH van'],
            ['name' => 'Toyota KDH Radiator', 'desc' => 'Radiator for Toyota KDH van'],
            ['name' => 'Toyota KDH Headlight', 'desc' => 'Headlight for Toyota KDH van'],
            ['name' => 'Toyota KDH Taillight', 'desc' => 'Taillight for Toyota KDH van'],
            ['name' => 'Toyota KDH Wiper Blade', 'desc' => 'Wiper blade for Toyota KDH van'],
            ['name' => 'Toyota KDH Shock Absorber', 'desc' => 'Shock absorber for Toyota KDH van'],
            ['name' => 'Toyota KDH Alternator', 'desc' => 'Alternator for Toyota KDH van'],
            ['name' => 'Toyota KDH Starter Motor', 'desc' => 'Starter motor for Toyota KDH van'],
            ['name' => 'Toyota KDH Clutch Plate', 'desc' => 'Clutch plate for Toyota KDH van'],
            ['name' => 'Toyota KDH Flywheel', 'desc' => 'Flywheel for Toyota KDH van'],
            ['name' => 'Toyota KDH Door Mirror', 'desc' => 'Door mirror for Toyota KDH van'],
            ['name' => 'Toyota KDH Window Regulator', 'desc' => 'Window regulator for Toyota KDH van'],
            ['name' => 'Toyota KDH Power Steering Pump', 'desc' => 'Power steering pump for Toyota KDH van'],
            ['name' => 'Toyota KDH AC Compressor', 'desc' => 'AC compressor for Toyota KDH van'],
            ['name' => 'Toyota KDH Fan Belt', 'desc' => 'Fan belt for Toyota KDH van'],
        ];
        // Add 80 more generic and Toyota/KDH parts
        for ($i = 1; $i <= 80; $i++) {
            $spareParts[] = [
                'name' => 'Toyota KDH Spare Part ' . $i,
                'desc' => 'Sample spare part ' . $i . ' for Toyota KDH van or similar vehicles.'
            ];
        }
        // Add some other vehicle parts for variety
        $spareParts = array_merge($spareParts, [
            ['name' => 'Nissan Leaf Brake Pad', 'desc' => 'Brake pad for Nissan Leaf (hybrid)'],
            ['name' => 'Suzuki Every Oil Filter', 'desc' => 'Oil filter for Suzuki Every (van)'],
            ['name' => 'Honda Fit Spark Plug', 'desc' => 'Spark plug for Honda Fit (hybrid)'],
            ['name' => 'Toyota Prius Wiper Blade', 'desc' => 'Wiper blade for Toyota Prius (hybrid)'],
            ['name' => 'Mazda Demio Headlight', 'desc' => 'Headlight for Mazda Demio (car)'],
            ['name' => 'Suzuki Alto Radiator', 'desc' => 'Radiator for Suzuki Alto (car)'],
            ['name' => 'Nissan Caravan Fuel Pump', 'desc' => 'Fuel pump for Nissan Caravan (van)'],
            ['name' => 'Toyota Hiace Timing Belt', 'desc' => 'Timing belt for Toyota Hiace (van)'],
            ['name' => 'Honda Grace Battery', 'desc' => 'Hybrid battery for Honda Grace (hybrid)'],
        ]);

        $medicines = [];
        foreach ($spareParts as $item) {
            $medicines[] = \App\Models\Medicine::create([
                'name' => $item['name'],
                'description' => $item['desc'],
                'type' => [ 'item'],
            ]);
        }

        // For each item, add 1-2 variants (brands) with prices, stock, barcode, supplier
        $variants = [];
        foreach ($medicines as $i => $medicine) {
            $variantCount = rand(1, 2);
            for ($v = 1; $v <= $variantCount; $v++) {
                $price = rand(2000, 80000) / 100;
                $stock = rand(5, 50);
                $barcode = 'SPBC' . str_pad($i+1, 3, '0', STR_PAD_LEFT) . $v;
                $variant = \App\Models\MedicineBrand::create([
                    'medicine_id' => $medicine->id,
                    'name' => $medicine->name . " - Variant $v",
                    'price' => $price,
                    'wholesale_price' => $price * 0.9,
                    'stock' => $stock,
                    'expiry_date' => now()->addMonths(rand(6, 36)),
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id,
                    'batch_number' => 'SPBATCH-' . $i . '-' . $v,
                    'image_url' => null
                ]);
                $variants[] = $variant;
                // Add a batch for each variant
                \App\Models\MedicineBrandBatch::create([
                    'medicine_brand_id' => $variant->id,
                    'batch_number' => 'SPBATCH-' . $i . '-' . $v,
                    'expiry_date' => now()->addMonths(rand(6, 36)),
                    'quantity' => $stock,
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id
                ]);
            }
        }

        // Add a sample purchase order and GRN with unique numbers
        $unique = uniqid();
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'SP-PO-' . $unique,
            'supplier_id' => $supplier->id,
            'order_date' => now()->subDays(10),
            'expected_date' => now()->addDays(2),
            'status' => 'received',
            'subtotal' => 25000,
            'discount' => 1000,
            'tax' => 500,
            'total' => 24500,
            'items' => array_map(function($variant) { return [
                'item_id' => $variant->id,
                'name' => $variant->name,
                'quantity' => 2,
                'price' => $variant->price
            ]; }, array_slice($variants, 0, 5)),
            'notes' => 'Initial spare parts order.'
        ]);

        // Add multiple GRNs and update stock via batches
        for ($g = 1; $g <= 3; $g++) {
            $grnItems = [];
            $totalCost = 0;
            foreach (array_slice($variants, ($g-1)*10, 10) as $variant) {
                $grnItems[] = [
                    'item_id' => $variant->id,
                    'name' => $variant->name,
                    'quantity' => 10,
                    'price' => $variant->price
                ];
                $totalCost += $variant->price * 10;
            }
            \App\Models\GoodsReceipt::create([
                'grn_number' => 'SP-GRN-' . $unique . '-' . $g,
                'supplier_id' => $supplier->id,
                'receipt_date' => now()->subDays($g*2),
                'total_cost' => $totalCost,
                'items' => $grnItems,
                'notes' => 'Spare parts GRN batch ' . $g
            ]);
        }
    }
}
