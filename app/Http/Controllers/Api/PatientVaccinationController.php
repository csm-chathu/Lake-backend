<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientVaccination;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientVaccinationController extends Controller
{
    public function index(Patient $patient)
    {
        $vaccinations = $patient->vaccinations()
            ->with('appointment')
            ->orderByDesc('next_due_at')
            ->get();

        return response()->json($vaccinations->map(fn ($vaccination) => $this->format($vaccination)));
    }

    public function store(Request $request, Patient $patient)
    {
        $data = $this->validatePayload($request);
        $payload = $this->mapPayload($data);

        $vaccination = $patient->vaccinations()->create($payload);

        return response()->json($this->format($vaccination->fresh('appointment')), 201);
    }

    public function update(Request $request, PatientVaccination $patientVaccination)
    {
        $data = $this->validatePayload($request, true);
        $payload = $this->mapPayload($data, true);

        $patientVaccination->fill($payload);
        $patientVaccination->save();

        return response()->json($this->format($patientVaccination->fresh('appointment')));
    }

    public function destroy(PatientVaccination $patientVaccination)
    {
        $patientVaccination->delete();
        return response()->noContent();
    }

    private function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $presence = $isUpdate ? 'sometimes|required' : 'required';

        return $request->validate([
            'appointmentId' => 'nullable|integer|exists:appointments,id',
            'vaccineName' => $presence . '|string|max:255',
            'doseNumber' => 'nullable|integer|min:0|max:255',
            'administeredAt' => 'nullable|date',
            'nextDueAt' => $presence . '|date',
            'remindBeforeDays' => 'nullable|integer|min:0|max:365',
            'notes' => 'nullable|string',
        ]);
    }

    private function mapPayload(array $data, bool $isUpdate = false): array
    {
        $payload = [];

        if (array_key_exists('appointmentId', $data)) {
            $payload['appointment_id'] = $data['appointmentId'];
        }

        foreach ([
            'vaccine_name' => 'vaccineName',
            'dose_number' => 'doseNumber',
            'notes' => 'notes',
        ] as $column => $key) {
            if (array_key_exists($key, $data)) {
                $payload[$column] = $data[$key];
            }
        }

        foreach ([
            'administered_at' => 'administeredAt',
            'next_due_at' => 'nextDueAt',
        ] as $column => $key) {
            if (array_key_exists($key, $data)) {
                $payload[$column] = $data[$key] ? Carbon::parse($data[$key]) : null;
            }
        }

        if (array_key_exists('remindBeforeDays', $data)) {
            $payload['remind_before_days'] = $data['remindBeforeDays'] ?? 1;
        } elseif (! $isUpdate) {
            $payload['remind_before_days'] = 1;
        }

        return $payload;
    }

    private function format(PatientVaccination $vaccination): array
    {
        return [
            'id' => $vaccination->id,
            'patientId' => $vaccination->patient_id,
            'appointmentId' => $vaccination->appointment_id,
            'vaccineName' => $vaccination->vaccine_name,
            'doseNumber' => $vaccination->dose_number,
            'administeredAt' => $vaccination->administered_at,
            'nextDueAt' => $vaccination->next_due_at,
            'remindBeforeDays' => $vaccination->remind_before_days,
            'reminderStatus' => $vaccination->reminder_status,
            'reminderSentAt' => $vaccination->reminder_sent_at,
            'reminderAttempts' => $vaccination->reminder_attempts,
            'notes' => $vaccination->notes,
            'createdAt' => $vaccination->created_at,
            'updatedAt' => $vaccination->updated_at,
        ];
    }
}
