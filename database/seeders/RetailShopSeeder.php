<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class RetailShopSeeder extends Seeder
{
    public function run(): void
    {
        // Retail shop dummy users
        User::firstOrCreate(
            ['email' => 'retailadmin@shop.test'],
            [
                'name' => 'Retail Admin',
                'password' => Hash::make('Retail123!'),
                'user_type' => 'pos_admin'
            ]
        );

        // Add sample suppliers
        $supplier = \App\Models\Supplier::create([
            'name' => 'සැපයුම්කරු 1 (Supplier 1)',
            'contact_person' => 'කොටිපාල (Kothipala)',
            'email' => 'supplier1@shop.test',
            'phone' => '0771234567',
            'address' => 'කොළඹ 01, Colombo 01',
            'notes' => 'Main supplier for retail items.'
        ]);

        // Clean up for repeatable seeding
        if (env('PROTECT_DATA', false) !== true) {
            // DB::table('purchase_orders')->truncate();
            // DB::table('goods_receipts')->truncate();
            // DB::table('stock_batches')->truncate();
            // DB::table('medicine_brand_batches')->truncate();
            // DB::table('medicine_brands')->truncate();
            if (DB::table('medicine_brands')->count() === 0) {
                \Log::info('medicine_brands table is empty at start of RetailShopSeeder');
            }
            // DB::table('medicines')->truncate();
        } else {
            echo "[Seeder] Skipped truncation due to PROTECT_DATA=true\n";
        }

        // Add 35 real retail items (medicines) with Sinhala/English names and descriptions
        $realItems = [
            ['name' => 'සීනි (Sugar)', 'desc' => '1kg packet refined sugar'],
            ['name' => 'පාන් (Bread)', 'desc' => 'Standard bakery bread loaf'],
            ['name' => 'කිරි (Milk Powder)', 'desc' => '400g Anchor milk powder'],
            ['name' => 'තේ (Tea Leaves)', 'desc' => '100g loose Ceylon tea'],
            ['name' => 'කෝපි (Coffee)', 'desc' => '50g instant coffee jar'],
            ['name' => 'බිස්කට් (Biscuits)', 'desc' => 'Munchee Lemon Puff 200g'],
            ['name' => 'සෝයා (Soya Meat)', 'desc' => '100g Soya meat packet'],
            ['name' => 'පරිප්පු (Dhal)', 'desc' => '500g red lentils'],
            ['name' => 'සම්බෝල (Sambol Paste)', 'desc' => 'Seeni sambol bottle'],
            ['name' => 'තෙල් (Coconut Oil)', 'desc' => '500ml bottle coconut oil'],
            ['name' => 'අල (Potato)', 'desc' => '1kg fresh potatoes'],
            ['name' => 'ලූනු (Onion)', 'desc' => '1kg big onions'],
            ['name' => 'මිරිස් (Chili Powder)', 'desc' => '100g chili powder packet'],
            ['name' => 'ලුණු (Salt)', 'desc' => '400g iodized salt'],
            ['name' => 'කහ (Turmeric Powder)', 'desc' => '50g turmeric powder'],
            ['name' => 'කුරුඳු (Cinnamon)', 'desc' => '50g cinnamon sticks'],
            ['name' => 'කැරට් (Carrot)', 'desc' => '500g fresh carrots'],
            ['name' => 'බෝංචි (Beans)', 'desc' => '500g green beans'],
            ['name' => 'තක්කාලි (Tomato)', 'desc' => '500g tomatoes'],
            ['name' => 'බටර් (Butter)', 'desc' => '100g Astra butter'],
            ['name' => 'චීස් (Cheese)', 'desc' => '100g Happy Cow cheese'],
            ['name' => 'මිරිස් (Green Chili)', 'desc' => '100g green chili'],
            ['name' => 'කොස් (Jackfruit)', 'desc' => '1kg jackfruit pieces'],
            ['name' => 'පොල් (Coconut)', 'desc' => '1 whole coconut'],
            ['name' => 'බඩඉරිඟු (Rice)', 'desc' => '1kg Nadu rice'],
            ['name' => 'පරිප්පු වඩ (Dhal Wade)', 'desc' => 'Pack of 5 Dhal Wade'],
            ['name' => 'කිරි පැණි (Condensed Milk)', 'desc' => '400g condensed milk tin'],
            ['name' => 'සෝයා සෝස් (Soy Sauce)', 'desc' => '200ml soy sauce bottle'],
            ['name' => 'වැනිලා (Vanilla Essence)', 'desc' => '20ml vanilla essence bottle'],
            ['name' => 'බේකින් පවුඩර් (Baking Powder)', 'desc' => '50g baking powder'],
            ['name' => 'බිත්තර (Eggs)', 'desc' => '1 dozen eggs'],
            ['name' => 'කිරි පැණි (Evaporated Milk)', 'desc' => '400ml evaporated milk tin'],
            ['name' => 'කිරි බිස්කට් (Milk Biscuits)', 'desc' => '200g Maliban milk biscuits'],
            ['name' => 'කිරි තේ (Milk Tea)', 'desc' => 'Ready-to-drink milk tea bottle'],
            ['name' => 'කිරි කෝපි (Milk Coffee)', 'desc' => 'Ready-to-drink milk coffee bottle'],
        ];
        $medicines = [];
        foreach ($realItems as $item) {
            $medicines[] = \App\Models\Medicine::create([
                'name' => $item['name'],
                'description' => $item['desc']
            ]);
        }

        // For each medicine, add 1-2 variants (brands) with prices, stock, barcode, supplier
        $variants = [];
        foreach ($medicines as $i => $medicine) {
            $variantCount = rand(1, 2);
            for ($v = 1; $v <= $variantCount; $v++) {
                $price = rand(100, 5000) / 100;
                $stock = rand(10, 100);
                $barcode = 'BC' . str_pad($i+1, 3, '0', STR_PAD_LEFT) . $v;
                $variant = \App\Models\MedicineBrand::create([
                    'medicine_id' => $medicine->id,
                    'name' => $medicine->name . " - වර්ගය $v (Variant $v)",
                    'price' => $price,
                    'wholesale_price' => $price * 0.9,
                    'stock' => $stock,
                    'expiry_date' => now()->addMonths(rand(6, 24)),
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id,
                    'batch_number' => 'MBATCH-' . $i . '-' . $v,
                    'image_url' => null
                ]);
                $variants[] = $variant;
                // Add a batch for each variant
                \App\Models\MedicineBrandBatch::create([
                    'medicine_brand_id' => $variant->id,
                    'batch_number' => 'MBATCH-' . $i . '-' . $v,
                    'expiry_date' => now()->addMonths(rand(6, 24)),
                    'quantity' => $stock,
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id
                ]);
            }
        }

        // Add 35 stock items with Sinhala/English names in notes
        $items = [];
        for ($i = 1; $i <= 35; $i++) {
            $item = \App\Models\StockItem::create([
                'name' => "භාණ්ඩය $i (Item $i)",
                'sku' => "SKU$i",
                'quantity' => rand(10, 100),
                'purchase_price' => rand(100, 5000) / 100,
                'sale_price' => rand(120, 6000) / 100,
                'notes' => "සිංහල නම භාණ්ඩය $i / English name Item $i"
            ]);
            $items[] = $item;
        }

        // Add a sample purchase order and GRN with unique numbers
        $unique = uniqid();
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PO-' . $unique,
            'supplier_id' => $supplier->id,
            'order_date' => now()->subDays(7),
            'expected_date' => now()->addDays(3),
            'status' => 'received',
            'subtotal' => 10000,
            'discount' => 500,
            'tax' => 300,
            'total' => 9800,
            'items' => array_map(function($item) { return [
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => 5,
                'price' => $item->purchase_price
            ]; }, array_slice($items, 0, 5)),
            'notes' => 'Initial stock purchase.'
        ]);

        // Add multiple GRNs and update stock via batches
        for ($g = 1; $g <= 3; $g++) {
            $grnItems = [];
            $totalCost = 0;
            foreach (array_slice($items, ($g-1)*10, 10) as $item) {
                $batchNumber = 'BATCH-' . $g . '-' . $item->id;
                $qty = rand(5, 20);
                $cost = $item->purchase_price * $qty;
                $totalCost += $cost;
                // Create a stock batch (simulate variant)
                $batch = \App\Models\StockBatch::create([
                    'stock_item_id' => $item->id,
                    'batch_number' => $batchNumber,
                    'expiry_date' => now()->addMonths(rand(6, 24)),
                    'quantity' => $qty,
                    'cost_price' => $item->purchase_price,
                    'notes' => 'Batch for ' . $item->name
                ]);
                // Update item quantity
                $item->quantity += $qty;
                $item->save();
                $grnItems[] = [
                    'item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $qty,
                    'price' => $item->purchase_price,
                    'batch_number' => $batchNumber
                ];
            }
            \App\Models\GoodsReceipt::create([
                'grn_number' => 'GRN-100' . $g,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $po->id,
                'receipt_date' => now()->subDays(5 - $g),
                'total_cost' => $totalCost,
                'items' => $grnItems,
                'notes' => 'Received stock batch ' . $g
            ]);
        }
    }
}
