<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        // Pharmacy dummy users
        User::firstOrCreate(
            ['email' => 'pharmacyadmin@pharmacy.test'],
            [
                'name' => 'Pharmacy Admin',
                'password' => Hash::make('Pharmacy123!'),
                'user_type' => 'pos_admin'
            ]
        );

        // Add sample supplier
        $supplier = \App\Models\Supplier::create([
            'name' => 'Pharmacy Supplier 1',
            'contact_person' => 'John Doe',
            'email' => 'supplier1@pharmacy.test',
            'phone' => '0771234567',
            'address' => 'Colombo 01',
            'notes' => 'Main supplier for pharmacy items.'
        ]);

        // Clean up for repeatable seeding
        \DB::table('purchase_orders')->truncate();
        \DB::table('goods_receipts')->truncate();
        \DB::table('stock_batches')->truncate();
        \DB::table('medicine_brand_batches')->truncate();
        \DB::table('medicine_brands')->truncate();
        \DB::table('medicines')->truncate();

        // Add 35 real pharmacy items (medicines) with English names and descriptions
        $pharmacyItems = [
            ['name' => 'Paracetamol', 'desc' => '500mg tablets for pain relief'],
            ['name' => 'Amoxicillin', 'desc' => '250mg capsules antibiotic'],
            ['name' => 'Cetirizine', 'desc' => '10mg allergy tablets'],
            ['name' => 'Metformin', 'desc' => '500mg diabetes tablets'],
            ['name' => 'Ibuprofen', 'desc' => '200mg pain relief tablets'],
            ['name' => 'Omeprazole', 'desc' => '20mg gastric capsules'],
            ['name' => 'Amlodipine', 'desc' => '5mg hypertension tablets'],
            ['name' => 'Atorvastatin', 'desc' => '10mg cholesterol tablets'],
            ['name' => 'Ciprofloxacin', 'desc' => '500mg antibiotic tablets'],
            ['name' => 'Loratadine', 'desc' => '10mg allergy tablets'],
            ['name' => 'Azithromycin', 'desc' => '250mg antibiotic tablets'],
            ['name' => 'Vitamin C', 'desc' => '500mg immune booster tablets'],
            ['name' => 'Prednisolone', 'desc' => '5mg steroid tablets'],
            ['name' => 'Salbutamol', 'desc' => '2mg asthma tablets'],
            ['name' => 'Losartan', 'desc' => '50mg hypertension tablets'],
            ['name' => 'Furosemide', 'desc' => '40mg diuretic tablets'],
            ['name' => 'Clopidogrel', 'desc' => '75mg antiplatelet tablets'],
            ['name' => 'Gliclazide', 'desc' => '80mg diabetes tablets'],
            ['name' => 'Enalapril', 'desc' => '5mg hypertension tablets'],
            ['name' => 'Simvastatin', 'desc' => '20mg cholesterol tablets'],
            ['name' => 'Doxycycline', 'desc' => '100mg antibiotic capsules'],
            ['name' => 'Ranitidine', 'desc' => '150mg gastric tablets'],
            ['name' => 'Hydrochlorothiazide', 'desc' => '25mg diuretic tablets'],
            ['name' => 'Warfarin', 'desc' => '5mg anticoagulant tablets'],
            ['name' => 'Levothyroxine', 'desc' => '50mcg thyroid tablets'],
            ['name' => 'Diazepam', 'desc' => '5mg sedative tablets'],
            ['name' => 'Fluconazole', 'desc' => '150mg antifungal tablets'],
            ['name' => 'Spironolactone', 'desc' => '25mg diuretic tablets'],
            ['name' => 'Pantoprazole', 'desc' => '20mg gastric tablets'],
            ['name' => 'Erythromycin', 'desc' => '250mg antibiotic tablets'],
            ['name' => 'Meloxicam', 'desc' => '7.5mg pain relief tablets'],
            ['name' => 'Tramadol', 'desc' => '50mg pain relief capsules'],
            ['name' => 'Cefuroxime', 'desc' => '250mg antibiotic tablets'],
            ['name' => 'Metronidazole', 'desc' => '400mg antibiotic tablets'],
            ['name' => 'Bisoprolol', 'desc' => '5mg hypertension tablets'],
        ];
        $medicines = [];
        foreach ($pharmacyItems as $item) {
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
                $barcode = 'PHBC' . str_pad($i+1, 3, '0', STR_PAD_LEFT) . $v;
                $variant = \App\Models\MedicineBrand::create([
                    'medicine_id' => $medicine->id,
                    'name' => $medicine->name . " - Variant $v",
                    'price' => $price,
                    'wholesale_price' => $price * 0.9,
                    'stock' => $stock,
                    'expiry_date' => now()->addMonths(rand(6, 24)),
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id,
                    'batch_number' => 'PHBATCH-' . $i . '-' . $v,
                    'image_url' => null
                ]);
                $variants[] = $variant;
                // Add a batch for each variant
                \App\Models\MedicineBrandBatch::create([
                    'medicine_brand_id' => $variant->id,
                    'batch_number' => 'PHBATCH-' . $i . '-' . $v,
                    'expiry_date' => now()->addMonths(rand(6, 24)),
                    'quantity' => $stock,
                    'barcode' => $barcode,
                    'supplier_id' => $supplier->id
                ]);
            }
        }

        // Add a sample purchase order and GRN with unique numbers
        $unique = uniqid();
        $po = \App\Models\PurchaseOrder::create([
            'po_number' => 'PH-PO-' . $unique,
            'supplier_id' => $supplier->id,
            'order_date' => now()->subDays(7),
            'expected_date' => now()->addDays(3),
            'status' => 'received',
            'subtotal' => 10000,
            'discount' => 500,
            'tax' => 300,
            'total' => 9800,
            'items' => array_map(function($variant) { return [
                'item_id' => $variant->id,
                'name' => $variant->name,
                'quantity' => 5,
                'price' => $variant->price
            ]; }, array_slice($variants, 0, 5)),
            'notes' => 'Initial pharmacy stock purchase.'
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
                'grn_number' => 'PH-GRN-' . $unique . '-' . $g,
                'supplier_id' => $supplier->id,
                'receipt_date' => now()->subDays($g*2),
                'total_cost' => $totalCost,
                'items' => $grnItems,
                'notes' => 'Pharmacy GRN batch ' . $g
            ]);
        }
    }
}
