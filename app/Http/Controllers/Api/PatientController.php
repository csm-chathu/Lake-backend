<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Owner;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    private function formatPatient($patient)
    {
        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'passbookNumber' => $patient->passbook_number,
            'species' => $patient->species,
            'breed' => $patient->breed,
            'age' => $patient->age,
            'owner' => $patient->owner ? [
                'id' => $patient->owner->id,
                'firstName' => $patient->owner->first_name,
                'lastName' => $patient->owner->last_name,
                'email' => $patient->owner->email,
                'phone' => $patient->owner->phone
            ] : null,
            'notes' => $patient->notes,
            'createdAt' => $patient->created_at,
            'updatedAt' => $patient->updated_at
        ];
    }

    public function index()
    {
        $patients = Patient::with('owner')->orderBy('created_at', 'desc')->get();
        return response()->json($patients->map(fn($p) => $this->formatPatient($p)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'species' => 'required|string|max:80',
            'breed' => 'nullable|string|max:120',
            'age' => 'nullable|integer|min:0|max:100',
            'ownerId' => 'required|integer|exists:owners,id',
            'notes' => 'nullable|string|max:2000'
        ]);

        // Map camelCase to snake_case for database
        $createData = [
            'name' => $data['name'],
            'species' => $data['species'],
            'breed' => $data['breed'] ?? null,
            'age' => $data['age'] ?? null,
            'owner_id' => $data['ownerId'],
            'notes' => $data['notes'] ?? null
        ];

        $patient = Patient::create($createData);
        $patient->load('owner');

        return response()->json($this->formatPatient($patient), 201);
    }

    public function show($id)
    {
        $patient = Patient::with('owner')->find($id);
        if (! $patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }
        return response()->json($this->formatPatient($patient));
    }

    public function update(Request $request, $id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'species' => 'sometimes|required|string|max:80',
            'breed' => 'nullable|string|max:120',
            'age' => 'nullable|integer|min:0|max:100',
            'ownerId' => 'nullable|integer|exists:owners,id',
            'notes' => 'nullable|string|max:2000'
        ]);

        // Map camelCase to snake_case for database
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['species'])) $updateData['species'] = $data['species'];
        if (isset($data['breed'])) $updateData['breed'] = $data['breed'];
        if (isset($data['age'])) $updateData['age'] = $data['age'];
        if (isset($data['ownerId'])) $updateData['owner_id'] = $data['ownerId'];
        if (isset($data['notes'])) $updateData['notes'] = $data['notes'];

        $patient->update($updateData);
        $patient->load('owner');

        return response()->json($this->formatPatient($patient));
    }

    public function destroy($id)
    {
        $patient = Patient::find($id);
        if (! $patient) {
            return response()->json(['message' => 'Patient not found'], 404);
        }

        $patient->delete();
        return response()->noContent();
    }
}

