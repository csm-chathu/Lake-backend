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
            'gender' => $patient->gender,
            'age' => $patient->age,
            'ageMonths' => $patient->age_months,
            'ageYears' => $patient->age,
            'weight' => $patient->weight !== null ? (float)$patient->weight : null,
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
        // Some production databases may not have `created_at` if migrations weren't run.
        // Support simple search via `q` query parameter (searches name, passbook, owner name).
        $q = request()->query('q');

        $query = Patient::with('owner');
        if ($q) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%' . $q . '%')
                    ->orWhere('passbook_number', 'like', '%' . $q . '%')
                    ->orWhereHas('owner', function ($b) use ($q) {
                        $b->where('first_name', 'like', '%' . $q . '%')
                          ->orWhere('last_name', 'like', '%' . $q . '%');
                    });
            });
        }

        $patients = $query->orderBy('id', 'desc')->get();
        return response()->json($patients->map(fn($p) => $this->formatPatient($p)));
    }

    // Return the next passbook number (preview) without creating a patient
    public function nextPassbook()
    {
        $next = Patient::previewNextPassbookNumber();
        return response()->json(['passbookNumber' => $next]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'species' => 'nullable|string|max:80',
            'breed' => 'nullable|string|max:120',
            'gender' => ['nullable', 'string', Rule::in(['male','female','unknown'])],
            // accept either `age` (years) for backward compatibility or `ageYears`/`ageMonths`
            'age' => 'nullable|integer|min:0|max:100',
            'ageYears' => 'nullable|integer|min:0|max:100',
            'ageMonths' => 'nullable|integer|min:0|max:11',
            'weight' => 'nullable|numeric|min:0',
            'ownerId' => 'required|integer|exists:owners,id',
            'notes' => 'nullable|string|max:2000'
        ]);

        // Map camelCase to snake_case for database
        $createData = [
            'name' => $data['name'],
            'species' => $data['species'] ?? null,
            'breed' => $data['breed'] ?? null,
            'gender' => $data['gender'] ?? null,
            // prefer explicit ageYears if provided, fall back to legacy `age`
            'age' => $data['ageYears'] ?? $data['age'] ?? null,
            'age_months' => $data['ageMonths'] ?? null,
            'weight' => array_key_exists('weight',$data) ? $data['weight'] : null,
            'owner_id' => $data['ownerId'],
            'notes' => $data['notes'] ?? null
        ];

        $patient = Patient::create($createData);
        $patient = $patient->fresh();
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
            'species' => 'sometimes|nullable|string|max:80',
            'breed' => 'nullable|string|max:120',
            'gender' => ['nullable', 'string', Rule::in(['male','female','unknown'])],
            'age' => 'nullable|integer|min:0|max:100',
            'ageYears' => 'nullable|integer|min:0|max:100',
            'ageMonths' => 'nullable|integer|min:0|max:11',
            'weight' => 'nullable|numeric|min:0',
            'ownerId' => 'nullable|integer|exists:owners,id',
            'notes' => 'nullable|string|max:2000'
        ]);

        // Map camelCase to snake_case for database
        $updateData = [];
        if (array_key_exists('name', $data)) $updateData['name'] = $data['name'];
        if (array_key_exists('species', $data)) $updateData['species'] = $data['species'];
        if (array_key_exists('breed', $data)) $updateData['breed'] = $data['breed'];
        if (array_key_exists('gender', $data)) $updateData['gender'] = $data['gender'];
        if (array_key_exists('age', $data)) $updateData['age'] = $data['age'];
        if (array_key_exists('ageYears', $data)) $updateData['age'] = $data['ageYears'];
        if (array_key_exists('ageMonths', $data)) $updateData['age_months'] = $data['ageMonths'];
        if (array_key_exists('weight', $data)) $updateData['weight'] = $data['weight'];
        if (array_key_exists('ownerId', $data)) $updateData['owner_id'] = $data['ownerId'];
        if (array_key_exists('notes', $data)) $updateData['notes'] = $data['notes'];

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

