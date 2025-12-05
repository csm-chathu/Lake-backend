<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Medicine;
use App\Models\MedicineBrand;

class MedicineController extends Controller
{
    private function formatBrand($brand)
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'price' => (float) $brand->price
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
        $medicines = Medicine::with('brands')->orderBy('name')->get();
        return response()->json($medicines->map(fn($m) => $this->formatMedicine($m)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'brands' => 'nullable|array'
        ]);

        $medicine = Medicine::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null
        ]);

        if (isset($data['brands']) && is_array($data['brands'])) {
            foreach ($data['brands'] as $brandData) {
                $medicine->brands()->create([
                    'name' => $brandData['name'] ?? null,
                    'price' => $brandData['price'] ?? 0
                ]);
            }
        }

        $medicine->load('brands');
        return response()->json($this->formatMedicine($medicine), 201);
    }

    public function show($id)
    {
        $medicine = Medicine::with('brands')->find($id);
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

        $medicine->update($updateData);

        if (isset($data['brands'])) {
            $medicine->brands()->delete();
            foreach ($data['brands'] as $brandData) {
                $medicine->brands()->create([
                    'name' => $brandData['name'] ?? null,
                    'price' => $brandData['price'] ?? 0
                ]);
            }
        }

        $medicine->load('brands');
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
