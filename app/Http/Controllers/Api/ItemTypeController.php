<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ItemType;

class ItemTypeController extends Controller
{
    public function index()
    {
        $types = ItemType::orderBy('sort_order')->orderBy('id')->get();
        return response()->json($types->map(fn($t) => [
            'id'         => $t->id,
            'label'      => $t->label,
            'value'      => $t->value,
            'sort_order' => (int) $t->sort_order,
            'active'     => (bool) $t->active,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label'      => 'required|string|max:150',
            'value'      => 'required|string|max:200',
            'sort_order' => 'sometimes|integer',
            'active'     => 'sometimes|boolean',
        ]);

        $type = ItemType::create([
            'label'      => $data['label'],
            'value'      => $data['value'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active'     => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);

        return response()->json([
            'id'         => $type->id,
            'label'      => $type->label,
            'value'      => $type->value,
            'sort_order' => (int) $type->sort_order,
            'active'     => (bool) $type->active,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $type = ItemType::findOrFail($id);

        $data = $request->validate([
            'label'      => 'required|string|max:150',
            'value'      => 'required|string|max:200',
            'sort_order' => 'sometimes|integer',
            'active'     => 'sometimes|boolean',
        ]);

        $type->update([
            'label'      => $data['label'],
            'value'      => $data['value'],
            'sort_order' => $data['sort_order'] ?? $type->sort_order,
            'active'     => array_key_exists('active', $data) ? (bool) $data['active'] : $type->active,
        ]);

        return response()->json([
            'id'         => $type->id,
            'label'      => $type->label,
            'value'      => $type->value,
            'sort_order' => (int) $type->sort_order,
            'active'     => (bool) $type->active,
        ]);
    }

    public function destroy($id)
    {
        ItemType::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
