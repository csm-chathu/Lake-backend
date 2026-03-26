<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisposabalChargePreset;
use Illuminate\Http\Request;

class DisposabalChargePresetController extends Controller
{
    public function index()
    {
        $presets = DisposabalChargePreset::orderBy('id')->get();
        return response()->json(
            $presets->map(fn ($preset) => [
                'id' => $preset->id,
                'name' => $preset->name,
                'label' => $preset->label,
                'value' => (float) $preset->value,
                'active' => (bool) $preset->active,
            ])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'label' => 'required|string|max:150',
            'value' => 'required|numeric',
            'active' => 'sometimes|boolean',
        ]);

        $preset = DisposabalChargePreset::create([
            'name' => $data['name'] ?? null,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
        ]);

        return response()->json([
            'id' => $preset->id,
            'name' => $preset->name,
            'label' => $preset->label,
            'value' => (float) $preset->value,
            'active' => (bool) $preset->active,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $preset = DisposabalChargePreset::findOrFail($id);
        $data = $request->validate([
            'name' => 'nullable|string|max:150',
            'label' => 'required|string|max:150',
            'value' => 'required|numeric',
            'active' => 'sometimes|boolean',
        ]);
        $preset->update([
            'name' => $data['name'] ?? $preset->name,
            'label' => $data['label'],
            'value' => (float) $data['value'],
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : $preset->active,
        ]);
        return response()->json([
            'id' => $preset->id,
            'name' => $preset->name,
            'label' => $preset->label,
            'value' => (float) $preset->value,
            'active' => (bool) $preset->active,
        ]);
    }

    public function destroy($id)
    {
        $preset = DisposabalChargePreset::findOrFail($id);
        $preset->delete();
        return response()->json(['success' => true]);
    }
}
