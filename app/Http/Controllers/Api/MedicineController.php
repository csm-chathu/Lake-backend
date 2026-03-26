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
            $batchNumber = array_key_exists('batch_number', $batch) ? trim((string) $batch['batch_number']) : '';
            // If batch_number is empty, generate a UUID (only for medicine type)
            if ($batchNumber === '') {
                // Use PHP's uniqid for a simple unique string, or use Str::uuid() if available
                if (class_exists('Illuminate\\Support\\Str')) {
                    $batchNumber = \Illuminate\Support\Str::uuid()->toString();
                } else {
                    $batchNumber = uniqid('batch_', true);
                }
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
            'unit_type' => $brand->unit_type,
            'scale' => $brand->scale,
            'conversion' => (int) $brand->conversion,
            'unit_cost' => (float) $brand->unit_cost,
        ];
    }

    private function formatMedicine($medicine)
    {
            return [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'description' => $medicine->description,
                'type' => is_array($medicine->type) ? $medicine->type : (is_string($medicine->type) ? [$medicine->type] : []),
                'brands' => $medicine->brands->map(fn($b) => $this->formatBrand($b)),
                'createdAt' => $medicine->created_at,
                'updatedAt' => $medicine->updated_at
            ];
    }

    public function index(Request $request)
    {
        $query = Medicine::with(['brands.batches'])->orderBy('name');
        $typeFilter = $request->input('type');
        $isSqlite = \DB::getDriverName() === 'sqlite';
        if ($typeFilter) {
            if ($isSqlite) {
                // SQLite: fetch all, filter in PHP
                $medicines = $query->get()->filter(function ($m) use ($typeFilter) {
                    $types = is_array($m->type) ? $m->type : (is_string($m->type) ? json_decode($m->type, true) : []);
                    return is_array($types) && in_array($typeFilter, $types);
                })->values();
            } else {
                $medicines = $query->whereJsonContains('type', $typeFilter)->get();
            }
        } else {
            $medicines = $query->get();
        }
        return response()->json($medicines->map(fn($m) => $this->formatMedicine($m)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'type' => 'nullable', // Accept any type, validate below
            'brands' => 'nullable|array'
        ]);

            // Accept type as string or array, always store as array
            if (isset($data['type'])) {
                if (is_string($data['type'])) {
                    $data['type'] = [$data['type']];
                } elseif (!is_array($data['type'])) {
                    $data['type'] = [];
                }
            }
        $medicine = DB::transaction(function () use ($data) {
            $medicine = Medicine::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'medicine',
            ]);

            if (isset($data['brands']) && is_array($data['brands'])) {
                foreach ($data['brands'] as $brandData) {
                    if (($data['type'] ?? 'medicine') === 'service') {
                        $brand = $medicine->brands()->create([
                            'name' => $brandData['name'] ?? null,
                            'price' => $brandData['price'] ?? 0
                        ]);
                    } else {
                        $brand = $medicine->brands()->create([
                            'name' => $brandData['name'] ?? null,
                            'price' => $brandData['price'] ?? 0,
                            'wholesale_price' => $brandData['wholesale_price'] ?? 0,
                            'stock' => $brandData['stock'] ?? 0,
                            'expiry_date' => $brandData['expiry_date'] ?? null,
                            'barcode' => $brandData['barcode'] ?? null,
                            'supplier_id' => $brandData['supplier_id'] ?? null,
                            'batch_number' => $brandData['batch_number'] ?? null,
                            'image_url' => $brandData['image_url'] ?? null,
                            'unit_type' => $brandData['unit_type'] ?? 'unit',
                            'scale' => $brandData['scale'] ?? 'ml',
                            'conversion' => $brandData['conversion'] ?? 1,
                            'unit_cost' => $brandData['unit_cost'] ?? 0,
                        ]);
                        $batches = (isset($brandData['batches']) && is_array($brandData['batches'])) ? $brandData['batches'] : [];
                        if (empty($batches)) {
                            // Generate a batch with UUID batch_number if none provided
                            if (class_exists('Illuminate\\Support\\Str')) {
                                $uuid = \Illuminate\Support\Str::uuid()->toString();
                            } else {
                                $uuid = uniqid('batch_', true);
                            }
                            $batches = [[
                                'batch_number' => $uuid,
                                'quantity' => $brandData['stock'] ?? 0
                            ]];
                        }
                        $this->persistBatches($brand, $batches);
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
            'type' => 'nullable', // Accept any type, validate below
            'brands' => 'nullable|array'
        ]);

            // Accept type as string or array, always store as array
            if (isset($data['type'])) {
                if (is_string($data['type'])) {
                    $data['type'] = [$data['type']];
                } elseif (!is_array($data['type'])) {
                    $data['type'] = [];
                }
            }
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['type'])) $updateData['type'] = $data['type'];

        DB::transaction(function () use ($medicine, $updateData, $data) {
            $medicine->update($updateData);

            if (isset($data['brands'])) {
                foreach ($data['brands'] as $brandData) {
                    if (($data['type'] ?? $medicine->type) === 'service') {
                        $brandPayload = [
                            'name' => $brandData['name'] ?? null,
                            'price' => $brandData['price'] ?? 0
                        ];
                    } else {
                        $brandPayload = [
                            'name' => $brandData['name'] ?? null,
                            'price' => $brandData['price'] ?? 0,
                            'wholesale_price' => $brandData['wholesale_price'] ?? 0,
                            'stock' => $brandData['stock'] ?? 0,
                            'expiry_date' => $brandData['expiry_date'] ?? null,
                            'barcode' => $brandData['barcode'] ?? null,
                            'supplier_id' => $brandData['supplier_id'] ?? null,
                            'batch_number' => $brandData['batch_number'] ?? null,
                            'image_url' => $brandData['image_url'] ?? null,
                            'unit_type' => $brandData['unit_type'] ?? 'unit',
                            'scale' => $brandData['scale'] ?? 'ml',
                            'conversion' => $brandData['conversion'] ?? 1,
                            'unit_cost' => $brandData['unit_cost'] ?? 0,
                        ];
                    }
                    if (isset($brandData['id'])) {
                        $brand = $medicine->brands()->where('id', $brandData['id'])->first();
                        if ($brand) {
                            $brand->update($brandPayload);
                            if (($data['type'] ?? $medicine->type) !== 'service' && isset($brandData['batches']) && is_array($brandData['batches'])) {
                                $this->persistBatches($brand, $brandData['batches']);
                            }
                        }
                    } else {
                        $brand = $medicine->brands()->create($brandPayload);
                        if (($data['type'] ?? $medicine->type) !== 'service' && isset($brandData['batches']) && is_array($brandData['batches'])) {
                            $this->persistBatches($brand, $brandData['batches']);
                        }
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
