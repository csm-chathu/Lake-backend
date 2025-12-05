<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Veterinarian;

class VeterinarianController extends Controller
{
    private function formatVeterinarian($vet)
    {
        return [
            'id' => $vet->id,
            'firstName' => $vet->first_name,
            'lastName' => $vet->last_name,
            'email' => $vet->email,
            'phone' => $vet->phone,
            'createdAt' => $vet->created_at,
            'updatedAt' => $vet->updated_at
        ];
    }

    public function index()
    {
        $vets = Veterinarian::orderBy('first_name')->get();
        return response()->json($vets->map(fn($v) => $this->formatVeterinarian($v)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstName' => 'nullable|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50'
        ]);

        $vet = Veterinarian::create([
            'first_name' => $data['firstName'] ?? null,
            'last_name' => $data['lastName'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null
        ]);

        return response()->json($this->formatVeterinarian($vet), 201);
    }

    public function show($id)
    {
        $vet = Veterinarian::find($id);
        if (! $vet) {
            return response()->json(['message' => 'Veterinarian not found'], 404);
        }
        return response()->json($this->formatVeterinarian($vet));
    }

    public function update(Request $request, $id)
    {
        $vet = Veterinarian::find($id);
        if (! $vet) {
            return response()->json(['message' => 'Veterinarian not found'], 404);
        }

        $data = $request->validate([
            'firstName' => 'nullable|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50'
        ]);

        $updateData = [];
        if (isset($data['firstName'])) $updateData['first_name'] = $data['firstName'];
        if (isset($data['lastName'])) $updateData['last_name'] = $data['lastName'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];

        $vet->update($updateData);
        return response()->json($this->formatVeterinarian($vet));
    }

    public function destroy($id)
    {
        $vet = Veterinarian::find($id);
        if (! $vet) {
            return response()->json(['message' => 'Veterinarian not found'], 404);
        }
        $vet->delete();
        return response()->noContent();
    }
}
