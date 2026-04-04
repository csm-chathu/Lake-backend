<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Discount;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::orderBy('id')->get();
        return response()->json($discounts->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'label' => $p->label,
            'value' => (float) $p->value,
            'active' => (bool) $p->active
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'label' => 'required|string|max:150',
            'value' => 'required|numeric',
            'active' => 'sometimes|boolean'
        ]);

        $discount = Discount::create([
            'name' => $data['name'] ?? null,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true
        ]);

        return response()->json([
            'id' => $discount->id,
            'name' => $discount->name,
            'label' => $discount->label,
            'value' => (float) $discount->value,
            'active' => (bool) $discount->active
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $discount = Discount::findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'label' => 'required|string|max:150',
            'value' => 'required|numeric',
            'active' => 'sometimes|boolean'
        ]);

        $discount->update([
            'name' => $data['name'] ?? $discount->name,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $discount->active
        ]);

        return response()->json([
            'id' => $discount->id,
            'name' => $discount->name,
            'label' => $discount->label,
            'value' => (float) $discount->value,
            'active' => (bool) $discount->active
        ]);
    }

    public function destroy($id)
    {
        $discount = Discount::findOrFail($id);
        $discount->delete();
        return response()->json(null, 204);
    }
}
