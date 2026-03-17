<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;

class MedicineController extends Controller
{
    private function persistBatches($brand, array $batchData): void
    {
        $brand->batches()->delete();

        $totalStock = 0;
        foreach ($batchData as $batch) {
            $batchNumber = trim((string) ($batch['batch_number'] ?? ''));
            if ($batchNumber === '') {
                continue;
            }

            $quantity = max(0, (int) ($batch['quantity'] ?? 0));
            $totalStock += $quantity;

            $brand->batches()->create([
                'batch_number' => $batchNumber,
                'expiry_date' => $batch['expiry_date'] ?? null,
                'quantity' => $quantity,
                'barcode' => !empty($batch['barcode']) ? trim((string) $batch['barcode']) : null,
                'supplier_id' => !empty($batch['supplier_id']) ? (int) $batch['supplier_id'] : null,
            ]);
        }

        $brand->update(['stock' => $totalStock]);
    }

    private function formatBrand($brand)
    {
        $batches = $brand->relationLoaded('batches')
            ? $brand->batches->map(fn ($batch) => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date,
                'quantity' => (int) $batch->quantity,
                'barcode' => $batch->barcode,
                'supplier_id' => $batch->supplier_id,
            ])->values()
            : collect();

        $stock = $batches->count() > 0
            ? (int) $batches->sum('quantity')
            : (int) ($brand->stock ?? 0);

        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'price' => (float) $brand->price,
            'wholesale_price' => (float) ($brand->wholesale_price ?? 0),
            'stock' => $stock,
            'expiry_date' => $brand->expiry_date,
            'barcode' => $brand->barcode,
            'supplier_id' => $brand->supplier_id,
            'batch_number' => $brand->batch_number,
            'image_url' => $brand->image_url,
            'batches' => $batches,
        ];
    }

    private function formatMedicine($medicine)
    {
        return [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'description' => $medicine->description,
            'brands' => $medicine->brands->map(fn($b) => $this->formatBrand($b)),
            'createdAt' => $medicine->created_at,
            'updatedAt' => $medicine->updated_at
        ];
    }

    public function index()
    {
        $medicines = Medicine::with(['brands.batches'])->orderBy('name')->get();
        return response()->json($medicines->map(fn($m) => $this->formatMedicine($m)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'brands' => 'nullable|array'
        ]);

        $medicine = DB::transaction(function () use ($data) {
            $medicine = Medicine::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null
            ]);

            if (isset($data['brands']) && is_array($data['brands'])) {
                foreach ($data['brands'] as $brandData) {
                    $brand = $medicine->brands()->create([
                        'name' => $brandData['name'] ?? null,
                        'price' => $brandData['price'] ?? 0,
                        'wholesale_price' => $brandData['wholesale_price'] ?? 0,
                        'stock' => $brandData['stock'] ?? 0,
                        'expiry_date' => $brandData['expiry_date'] ?? null,
                        'barcode' => $brandData['barcode'] ?? null,
                        'supplier_id' => $brandData['supplier_id'] ?? null,
                        'batch_number' => $brandData['batch_number'] ?? null,
                        'image_url' => $brandData['image_url'] ?? null
                    ]);

                    if (isset($brandData['batches']) && is_array($brandData['batches'])) {
                        $this->persistBatches($brand, $brandData['batches']);
                    }
                }
            }

            return $medicine;
        });

        $medicine->load(['brands.batches']);
        return response()->json($this->formatMedicine($medicine), 201);
    }

    public function show($id)
    {
        $medicine = Medicine::with(['brands.batches'])->find($id);
        if (! $medicine) {
            return response()->json(['message' => 'Medicine not found'], 404);
        }
        return response()->json($this->formatMedicine($medicine));
    }

    public function update(Request $request, $id)
    {
        $medicine = Medicine::find($id);
        if (! $medicine) {
            return response()->json(['message' => 'Medicine not found'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:200',
            'description' => 'nullable|string',
            'brands' => 'nullable|array'
        ]);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];

        DB::transaction(function () use ($medicine, $updateData, $data) {
            $medicine->update($updateData);

            if (isset($data['brands'])) {
                $medicine->brands()->delete();
                foreach ($data['brands'] as $brandData) {
                    $brand = $medicine->brands()->create([
                        'name' => $brandData['name'] ?? null,
                        'price' => $brandData['price'] ?? 0,
                        'wholesale_price' => $brandData['wholesale_price'] ?? 0,
                        'stock' => $brandData['stock'] ?? 0,
                        'expiry_date' => $brandData['expiry_date'] ?? null,
                        'barcode' => $brandData['barcode'] ?? null,
                        'supplier_id' => $brandData['supplier_id'] ?? null,
                        'batch_number' => $brandData['batch_number'] ?? null,
                        'image_url' => $brandData['image_url'] ?? null
                    ]);

                    if (isset($brandData['batches']) && is_array($brandData['batches'])) {
                        $this->persistBatches($brand, $brandData['batches']);
                    }
                }
            }
        });

        $medicine->load(['brands.batches']);
        return response()->json($this->formatMedicine($medicine));
    }

    public function destroy($id)
    {
        $medicine = Medicine::find($id);
        if (! $medicine) {
            return response()->json(['message' => 'Medicine not found'], 404);
        }
        $medicine->delete();
        return response()->noContent();
    }
}
