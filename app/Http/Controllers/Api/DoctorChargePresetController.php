<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DoctorChargePreset;

class DoctorChargePresetController extends Controller
{
    public function index()
    {
        $presets = DoctorChargePreset::orderBy('id')->get();
        return response()->json($presets->map(fn($p) => [
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

        $preset = DoctorChargePreset::create([
            'name' => $data['name'] ?? null,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true
        ]);

        return response()->json([
            'id' => $preset->id,
            'name' => $preset->name,
            'label' => $preset->label,
            'value' => (float) $preset->value,
            'active' => (bool) $preset->active
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $preset = DoctorChargePreset::findOrFail($id);

        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'label' => 'required|string|max:150',
            'value' => 'required|numeric',
            'active' => 'sometimes|boolean'
        ]);

        $preset->update([
            'name' => $data['name'] ?? $preset->name,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $preset->active
        ]);

        return response()->json([
            'id' => $preset->id,
            'name' => $preset->name,
            'label' => $preset->label,
            'value' => (float) $preset->value,
            'active' => (bool) $preset->active
        ]);
    }

    public function destroy($id)
    {
        $preset = DoctorChargePreset::findOrFail($id);
        $preset->delete();
        return response()->json(null, 204);
    }
}
