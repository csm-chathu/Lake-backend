<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Appointment;
use App\Models\AppointmentMedicine;
use App\Models\MedicineBrand;

class AppointmentController extends Controller
{
    private function formatBrand($brand)
    {
        if (!$brand) return null;
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'price' => (float)$brand->price,
            'medicineId' => $brand->medicine_id,
            'createdAt' => $brand->created_at,
            'updatedAt' => $brand->updated_at,
        ];
    }

    private function formatMedicine($medicine)
    {
        if (!$medicine) return null;
        return [
            'id' => $medicine->id,
            'name' => $medicine->name,
            'createdAt' => $medicine->created_at,
            'updatedAt' => $medicine->updated_at,
        ];
    }

    private function formatOwner($owner)
    {
        if (!$owner) return null;
        return [
            'id' => $owner->id,
            'firstName' => $owner->first_name,
            'lastName' => $owner->last_name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'createdAt' => $owner->created_at,
            'updatedAt' => $owner->updated_at,
        ];
    }

    private function formatVeterinarian($vet)
    {
        if (!$vet) return null;
        return [
            'id' => $vet->id,
            'firstName' => $vet->first_name,
            'lastName' => $vet->last_name,
            'email' => $vet->email,
            'phone' => $vet->phone,
            'createdAt' => $vet->created_at,
            'updatedAt' => $vet->updated_at,
        ];
    }

    private function formatPatient($patient)
    {
        if (!$patient) return null;
        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'species' => $patient->species,
            'breed' => $patient->breed,
            'age' => (int)$patient->age,
            'weight' => (float)$patient->weight,
            'passbookNumber' => $patient->passbook_number,
            'ownerId' => $patient->owner_id,
            'owner' => $this->formatOwner($patient->owner),
            'createdAt' => $patient->created_at,
            'updatedAt' => $patient->updated_at,
        ];
    }

    private function formatAppointment($appointment)
    {
        $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine']);

        $formattedMedicines = [];
        if ($appointment->medicines && count($appointment->medicines) > 0) {
            foreach ($appointment->medicines as $med) {
                $formattedMedicines[] = [
                    'id' => $med->id,
                    'appointmentId' => $med->appointment_id,
                    'medicineBrandId' => $med->medicine_brand_id,
                    'quantity' => (int)$med->quantity,
                    'unitPrice' => (float)$med->unit_price,
                    'brand' => $med->brand ? $this->formatBrand($med->brand) : null,
                    'createdAt' => $med->created_at,
                    'updatedAt' => $med->updated_at,
                ];
            }
        }

        return [
            'id' => $appointment->id,
            'date' => $appointment->date,
            'reason' => $appointment->reason,
            'status' => $appointment->status,
            'patientId' => $appointment->patient_id,
            'patient' => $this->formatPatient($appointment->patient),
            'veterinarianId' => $appointment->veterinarian_id,
            'veterinarian' => $this->formatVeterinarian($appointment->veterinarian),
            'isWalkIn' => (bool)$appointment->is_walk_in,
            'doctorCharge' => (float)$appointment->doctor_charge,
            'discount' => (float)$appointment->discount,
            'totalCharge' => (float)$appointment->total_charge,
            'medicines' => $formattedMedicines,
            'paymentType' => $appointment->payment_type,
            'paymentStatus' => $appointment->payment_status,
            'settledAt' => $appointment->settled_at,
            'notes' => $appointment->notes,
            'createdAt' => $appointment->created_at,
            'updatedAt' => $appointment->updated_at,
        ];
    }

    public function index()
    {
        $appointments = Appointment::with(['patient.owner', 'veterinarian', 'medicines.brand.medicine'])
            ->orderBy('date', 'desc')
            ->get();

        $formatted = $appointments->map(fn($apt) => $this->formatAppointment($apt));
        return response()->json($formatted);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'nullable|date',
            'reason' => 'nullable|string|max:1024',
            'status' => 'nullable|in:scheduled,completed,cancelled',
            'patientId' => 'nullable|integer|exists:patients,id',
            'veterinarianId' => 'nullable|integer|exists:veterinarians,id',
            'isWalkIn' => 'nullable|boolean',
            'doctorCharge' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'medicines' => 'nullable|array',
            'paymentType' => 'nullable|in:cash,credit',
            'paymentStatus' => 'nullable|in:pending,paid',
            'settledAt' => 'nullable|date'
        ]);

        $medicinesPayload = $request->input('medicines', []);

        return DB::transaction(function () use ($data, $medicinesPayload) {
            $appointment = Appointment::create([
                'date' => $data['date'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => $data['status'] ?? 'scheduled',
                'patient_id' => $data['patientId'] ?? null,
                'veterinarian_id' => $data['veterinarianId'] ?? null,
                'is_walk_in' => $data['isWalkIn'] ?? false,
                'doctor_charge' => $data['doctorCharge'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'payment_type' => $data['paymentType'] ?? 'cash',
                'payment_status' => $data['paymentStatus'] ?? ($data['paymentType'] === 'credit' ? 'pending' : 'paid'),
                'settled_at' => $data['settledAt'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);

            $medicinesTotal = 0;
            foreach ($medicinesPayload as $entry) {
                $brandId = isset($entry['medicineBrandId']) ? $entry['medicineBrandId'] : (isset($entry['medicine_brand_id']) ? $entry['medicine_brand_id'] : null);
                $qty = isset($entry['quantity']) ? max(0, intval($entry['quantity'])) : 0;
                if (! $brandId || $qty <= 0) continue;

                $brand = MedicineBrand::find($brandId);
                if (! $brand) continue;

                $unitPrice = $brand->price ?? 0;
                $med = AppointmentMedicine::create([
                    'appointment_id' => $appointment->id,
                    'medicine_brand_id' => $brand->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice
                ]);

                $medicinesTotal += $unitPrice * $qty;
            }

            $gross = ($appointment->doctor_charge ?? 0) + $medicinesTotal;
            $totalCharge = max(0, $gross - ($appointment->discount ?? 0));
            $appointment->total_charge = $totalCharge;
            $appointment->save();

            $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine']);
            return response()->json($this->formatAppointment($appointment), 201);
        });
    }

    public function show($id)
    {
        $appointment = Appointment::with(['patient.owner', 'veterinarian', 'medicines.brand.medicine'])->find($id);
        if (! $appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }
        return response()->json($this->formatAppointment($appointment));
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        $data = $request->validate([
            'date' => 'nullable|date',
            'reason' => 'nullable|string|max:1024',
            'status' => 'nullable|in:scheduled,completed,cancelled',
            'patientId' => 'nullable|integer|exists:patients,id',
            'veterinarianId' => 'nullable|integer|exists:veterinarians,id',
            'isWalkIn' => 'nullable|boolean',
            'doctorCharge' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'medicines' => 'nullable|array',
            'paymentType' => 'nullable|in:cash,credit',
            'paymentStatus' => 'nullable|in:pending,paid',
            'settledAt' => 'nullable|date'
        ]);

        return DB::transaction(function () use ($appointment, $data) {
            $appointment->update([
                'date' => $data['date'] ?? $appointment->date,
                'reason' => $data['reason'] ?? $appointment->reason,
                'status' => $data['status'] ?? $appointment->status,
                'patient_id' => $data['patientId'] ?? $appointment->patient_id,
                'veterinarian_id' => $data['veterinarianId'] ?? $appointment->veterinarian_id,
                'is_walk_in' => $data['isWalkIn'] ?? $appointment->is_walk_in,
                'doctor_charge' => $data['doctorCharge'] ?? $appointment->doctor_charge,
                'discount' => $data['discount'] ?? $appointment->discount,
                'payment_type' => $data['paymentType'] ?? $appointment->payment_type,
                'payment_status' => $data['paymentStatus'] ?? $appointment->payment_status,
                'settled_at' => $data['settledAt'] ?? $appointment->settled_at,
                'notes' => $data['notes'] ?? $appointment->notes
            ]);

            if (isset($data['medicines'])) {
                // Replace medicines
                $appointment->medicines()->delete();
                $medicinesTotal = 0;
                foreach ($data['medicines'] as $entry) {
                    $brandId = isset($entry['medicineBrandId']) ? $entry['medicineBrandId'] : (isset($entry['medicine_brand_id']) ? $entry['medicine_brand_id'] : null);
                    $qty = isset($entry['quantity']) ? max(0, intval($entry['quantity'])) : 0;
                    if (! $brandId || $qty <= 0) continue;
                    $brand = MedicineBrand::find($brandId);
                    if (! $brand) continue;
                    $unitPrice = $brand->price ?? 0;
                    AppointmentMedicine::create([
                        'appointment_id' => $appointment->id,
                        'medicine_brand_id' => $brand->id,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice
                    ]);
                    $medicinesTotal += $unitPrice * $qty;
                }
                $gross = ($appointment->doctor_charge ?? 0) + $medicinesTotal;
                $appointment->total_charge = max(0, $gross - ($appointment->discount ?? 0));
                $appointment->save();
            }

            $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine']);
            return response()->json($this->formatAppointment($appointment));
        });
    }

    public function destroy($id)
    {
        $appointment = Appointment::find($id);
        if (! $appointment) {
            return response()->json(['message' => 'Appointment not found'], 404);
        }

        $appointment->delete();
        return response()->noContent();
    }
}
