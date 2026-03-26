<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // Restaurant dummy users
        User::firstOrCreate(
            ['email' => 'restaurantadmin@restaurant.test'],
            [
                'name' => 'Restaurant Admin',
                'password' => Hash::make('Restaurant123!'),
                'user_type' => 'pos_admin'
            ]
        );

        // Add sample supplier
        $supplier = \App\Models\Supplier::create([
            'name' => 'Restaurant Supplier 1',
            'contact_person' => 'Jane Smith',
            'email' => 'supplier1@restaurant.test',
            'phone' => '0779876543',
            'address' => 'Colombo 02',
            'notes' => 'Main supplier for restaurant items.'
        ]);

        // Clean up for repeatable seeding
        // \DB::table('purchase_orders')->truncate();
        // \DB::table('goods_receipts')->truncate();
        // \DB::table('stock_batches')->truncate();
        // \DB::table('medicine_brand_batches')->truncate();
        // \DB::table('medicine_brands')->truncate();
        if (\DB::table('medicine_brands')->count() === 0) {
            \Log::info('medicine_brands table is empty at start of RestaurantSeeder');
        }
        // \DB::table('medicines')->truncate();

        // Add 35 real restaurant items (ingredients/menu items) with English names and descriptions
        $restaurantItems = [
            ['name' => 'Chicken Breast', 'desc' => 'Boneless chicken breast fillet'],
            ['name' => 'Basmati Rice', 'desc' => 'Premium long grain rice'],
            ['name' => 'Mozzarella Cheese', 'desc' => 'Shredded mozzarella cheese'],
            ['name' => 'Tomato Sauce', 'desc' => 'Rich tomato pizza sauce'],
            ['name' => 'Spaghetti', 'desc' => 'Italian spaghetti pasta'],
            ['name' => 'Olive Oil', 'desc' => 'Extra virgin olive oil'],
            ['name' => 'Black Pepper', 'desc' => 'Ground black pepper'],
            ['name' => 'Beef Steak', 'desc' => 'Tenderloin beef steak'],
            ['name' => 'Potato', 'desc' => 'Fresh potatoes for fries'],
            ['name' => 'Lettuce', 'desc' => 'Crisp iceberg lettuce'],
            ['name' => 'Mayonnaise', 'desc' => 'Creamy mayonnaise sauce'],
            ['name' => 'Eggs', 'desc' => 'Farm fresh eggs'],
            ['name' => 'Butter', 'desc' => 'Salted cooking butter'],
            ['name' => 'Garlic', 'desc' => 'Fresh garlic bulbs'],
            ['name' => 'Onion', 'desc' => 'Red onions'],
            ['name' => 'Carrot', 'desc' => 'Fresh carrots'],
            ['name' => 'Green Peas', 'desc' => 'Frozen green peas'],
            ['name' => 'Paneer', 'desc' => 'Indian cottage cheese'],
            ['name' => 'Fish Fillet', 'desc' => 'Boneless fish fillet'],
            ['name' => 'Soy Sauce', 'desc' => 'Dark soy sauce'],
            ['name' => 'Sweet Corn', 'desc' => 'Canned sweet corn'],
            ['name' => 'Bell Pepper', 'desc' => 'Mixed color bell peppers'],
            ['name' => 'Cucumber', 'desc' => 'Fresh cucumbers'],
            ['name' => 'Pasta Penne', 'desc' => 'Italian penne pasta'],
            ['name' => 'Bacon', 'desc' => 'Smoked pork bacon'],
            ['name' => 'Parmesan Cheese', 'desc' => 'Grated parmesan cheese'],
            ['name' => 'Mushroom', 'desc' => 'Button mushrooms'],
            ['name' => 'Spinach', 'desc' => 'Fresh spinach leaves'],
            ['name' => 'Tuna', 'desc' => 'Canned tuna chunks'],
            ['name' => 'Pizza Base', 'desc' => 'Medium size pizza base'],
            ['name' => 'Chicken Sausage', 'desc' => 'Cooked chicken sausage'],
            ['name' => 'Red Chili Flakes', 'desc' => 'Spicy chili flakes'],
            ['name' => 'Basil Leaves', 'desc' => 'Dried basil leaves'],
            ['name' => 'Cream', 'desc' => 'Cooking cream'],
            ['name' => 'Yogurt', 'desc' => 'Plain yogurt'],
        ];
        $medicines = [];
        foreach ($restaurantItems as $item) {
            $medicines[] = \App\Models\Medicine::create([
                'name' => $item['name'],
                'description' => $item['desc']
            ]);
        }

        // For each item, add 1-2 variants (brands) with prices, stock, barcode, supplier
        $variants = [];
        foreach ($medicines as $i => $medicine) {
            $variantCount = rand(1, 2);
            for ($v = 1; $v <= $variantCount; $v++) {
                $price = rand(200, 8000) / 100;
                $stock = rand(10, 100);
                $barcode = 'RSBC' . str_pad($i+1, 3, '0', STR_PAD_LEFT) . $v;
                $variant = \App\Models\MedicineBrand::create([
                    'medicine_id' => $medicine->id,
                    'name' => $medicine->name . " - Variant $v",
                    'price' => $price,
                    'wholesale_price' => $price * 0.9,
                    'stock' => $stock,
                    'expiry_date' => now()->addMonths(rand(3, 18)),
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id,
                    'batch_number' => 'RSBATCH-' . $i . '-' . $v,
                    'image_url' => null
                ]);
                $variants[] = $variant;
                // Add a batch for each variant
                \App\Models\MedicineBrandBatch::create([
                    'medicine_brand_id' => $variant->id,
                    'batch_number' => 'RSBATCH-' . $i . '-' . $v,
                    'expiry_date' => now()->addMonths(rand(3, 18)),
                    'quantity' => $stock,
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id
                ]);
            }
        }

        // Add a sample purchase order and GRN with unique numbers
        $unique = uniqid();
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'RS-PO-' . $unique,
            'supplier_id' => $supplier->id,
            'order_date' => now()->subDays(7),
            'expected_date' => now()->addDays(3),
            'status' => 'received',
            'subtotal' => 12000,
            'discount' => 600,
            'tax' => 400,
            'total' => 11800,
            'items' => array_map(function($variant) { return [
                'item_id' => $variant->id,
                'name' => $variant->name,
                'quantity' => 5,
                'price' => $variant->price
            ]; }, array_slice($variants, 0, 5)),
            'notes' => 'Initial restaurant stock purchase.'
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
                'grn_number' => 'RS-GRN-' . $unique . '-' . $g,
                'supplier_id' => $supplier->id,
                'receipt_date' => now()->subDays($g*2),
                'total_cost' => $totalCost,
                'items' => $grnItems,
                'notes' => 'Restaurant GRN batch ' . $g
            ]);
        }
    }
}
