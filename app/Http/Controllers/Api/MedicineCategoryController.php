<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MedicineCategory;

class MedicineCategoryController extends Controller
{
    public function index()
    {
        $cats = MedicineCategory::orderBy('sort_order')->orderBy('id')->get();
        return response()->json($cats->map(fn($c) => [
            'id'         => $c->id,
            'name'       => $c->name,
            'sort_order' => (int) $c->sort_order,
            'active'     => (bool) $c->active,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'sort_order' => 'sometimes|integer',
            'active'     => 'sometimes|boolean',
        ]);

        $cat = MedicineCategory::create([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active'     => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);

        return response()->json([
            'id'         => $cat->id,
            'name'       => $cat->name,
            'sort_order' => (int) $cat->sort_order,
            'active'     => (bool) $cat->active,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cat = MedicineCategory::findOrFail($id);

        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'sort_order' => 'sometimes|integer',
            'active'     => 'sometimes|boolean',
        ]);

        $cat->update([
            'name'       => $data['name'],
            'sort_order' => $data['sort_order'] ?? $cat->sort_order,
            'active'     => array_key_exists('active', $data) ? (bool) $data['active'] : $cat->active,
        ]);

        return response()->json([
            'id'         => $cat->id,
            'name'       => $cat->name,
            'sort_order' => (int) $cat->sort_order,
            'active'     => (bool) $cat->active,
        ]);
    }

    public function destroy($id)
    {
        MedicineCategory::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
