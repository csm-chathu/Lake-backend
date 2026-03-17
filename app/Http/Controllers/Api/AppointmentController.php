<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Appointment;
use App\Models\AppointmentMedicine;
use App\Models\AppointmentReport;
use App\Models\MedicineBrand;
use App\Models\PatientVaccination;
use App\Services\SmsGateway;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AppointmentController extends Controller
{
    public function __construct(private SmsGateway $smsGateway)
    {
    }

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

    private function formatReport($report)
    {
        if (!$report) return null;
        return [
            'id' => $report->id,
            'appointmentId' => $report->appointment_id,
            'patientId' => $report->patient_id,
            'type' => $report->report_type,
            'label' => $report->label,
            'fileUrl' => $report->file_url,
            'filePublicId' => $report->file_public_id,
            'mimeType' => $report->mime_type,
            'fileBytes' => $report->file_bytes,
            'reportedAt' => $report->reported_at,
            'createdAt' => $report->created_at,
            'updatedAt' => $report->updated_at,
        ];
    }

    private function formatVaccination($vaccination)
    {
        if (!$vaccination) return null;

        return [
            'id' => $vaccination->id,
            'patientId' => $vaccination->patient_id,
            'appointmentId' => $vaccination->appointment_id,
            'vaccineName' => $vaccination->vaccine_name,
            'doseNumber' => $vaccination->dose_number,
            'administeredAt' => $vaccination->administered_at,
            'nextDueAt' => $vaccination->next_due_at,
            'remindBeforeDays' => $vaccination->remind_before_days,
            'notes' => $vaccination->notes,
            'reminderStatus' => $vaccination->reminder_status,
            'reminderSentAt' => $vaccination->reminder_sent_at,
            'reminderAttempts' => $vaccination->reminder_attempts,
            'createdAt' => $vaccination->created_at,
            'updatedAt' => $vaccination->updated_at,
        ];
    }

    private function syncVaccinationReminder(Appointment $appointment, ?array $plan = null): void
    {
        $reason = Str::lower($appointment->reason ?? '');
        $containsKeyword = Str::contains($reason, 'vaccine');

        $planProvided = false;
        if (is_array($plan)) {
            foreach ($plan as $value) {
                if ($value !== null && $value !== '') {
                    $planProvided = true;
                    break;
                }
            }
        }

        if ((!$containsKeyword && ! $planProvided) || $appointment->status !== 'completed' || ! $appointment->patient_id) {
            PatientVaccination::where('appointment_id', $appointment->id)->delete();
            return;
        }

        $administeredAt = $this->parsePlanDate($plan['administeredAt'] ?? null)
            ?? ($appointment->date ? Carbon::parse($appointment->date) : now());

        $nextDueAt = $this->parsePlanDate($plan['nextDueAt'] ?? null)
            ?? (clone $administeredAt)->addYear();

        $vaccineName = trim((string)($plan['vaccineName'] ?? $appointment->reason ?? 'Vaccine'));
        if ($vaccineName === '') {
            $vaccineName = 'Vaccine';
        }

        $doseNumber = null;
        if (is_array($plan) && array_key_exists('doseNumber', $plan) && $plan['doseNumber'] !== null && $plan['doseNumber'] !== '') {
            $doseNumber = (int)$plan['doseNumber'];
        }

        $remindBeforeDays = 7;
        if (is_array($plan) && array_key_exists('remindBeforeDays', $plan) && $plan['remindBeforeDays'] !== null && $plan['remindBeforeDays'] !== '') {
            $remindBeforeDays = max(0, (int)$plan['remindBeforeDays']);
        }

        $notes = is_array($plan) && array_key_exists('notes', $plan)
            ? $plan['notes']
            : 'Auto-generated from appointment #' . $appointment->id;

        PatientVaccination::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_id' => $appointment->patient_id,
                'vaccine_name' => $vaccineName,
                'dose_number' => $doseNumber,
                'administered_at' => $administeredAt->toDateString(),
                'next_due_at' => $nextDueAt,
                'remind_before_days' => $remindBeforeDays,
                'notes' => $notes,
            ]
        );
    }

    private function parsePlanDate($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function formatAppointment($appointment)
    {
        $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine', 'reports', 'vaccinations']);

        $formattedMedicines = [];
        if ($appointment->medicines && count($appointment->medicines) > 0) {
            foreach ($appointment->medicines as $med) {
                $formattedMedicines[] = [
                    'id' => $med->id,
                    'appointmentId' => $med->appointment_id,
                    'medicineBrandId' => $med->medicine_brand_id,
                    'quantity' => (float)$med->quantity,
                    'unitPrice' => (float)$med->unit_price,
                    'brand' => $med->brand ? $this->formatBrand($med->brand) : null,
                    'createdAt' => $med->created_at,
                    'updatedAt' => $med->updated_at,
                ];
            }
        }

        $formattedReports = [];
        if ($appointment->reports && count($appointment->reports) > 0) {
            foreach ($appointment->reports as $report) {
                $formattedReports[] = $this->formatReport($report);
            }
        }

        $vaccinationEntry = null;
        if ($appointment->relationLoaded('vaccinations') && $appointment->vaccinations) {
            $vaccinationEntry = $appointment->vaccinations->sortByDesc('next_due_at')->first();
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
            'surgeryCharge' => (float)$appointment->surgery_charge,
            'otherCharge' => (float)$appointment->other_charge,
            'otherChargeReason' => $appointment->other_charge_reason,
            'discount' => (float)$appointment->discount,
            'totalCharge' => (float)$appointment->total_charge,
            'medicines' => $formattedMedicines,
            'paymentType' => $appointment->payment_type,
            'paymentStatus' => $appointment->payment_status,
            'settledAt' => $appointment->settled_at,
            'notes' => $appointment->notes,
            'reports' => $formattedReports,
            'vaccination' => $this->formatVaccination($vaccinationEntry),
            'createdAt' => $appointment->created_at,
            'updatedAt' => $appointment->updated_at,
        ];
    }

    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 0);
        if ($limit < 0) {
            $limit = 0;
        }

        $queryText = trim((string) $request->string('q', ''));
        $paymentType = trim((string) $request->string('paymentType', ''));
        $dateFrom = trim((string) $request->string('dateFrom', ''));
        $dateTo = trim((string) $request->string('dateTo', ''));
        $perPage = max(5, min(100, (int) $request->integer('perPage', 10)));

        $query = Appointment::with(['patient.owner', 'veterinarian', 'medicines.brand.medicine', 'reports', 'vaccinations'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc');

        if (in_array($paymentType, ['cash', 'credit'], true)) {
            $query->where('payment_type', $paymentType);
        }

        if ($dateFrom !== '') {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('date', '<=', $dateTo);
        }

        if ($queryText !== '') {
            $query->where(function ($q) use ($queryText) {
                $q->where('reason', 'like', "%{$queryText}%")
                    ->orWhere('status', 'like', "%{$queryText}%")
                    ->orWhere('notes', 'like', "%{$queryText}%")
                    ->orWhere('payment_type', 'like', "%{$queryText}%")
                    ->orWhere('payment_status', 'like', "%{$queryText}%")
                    ->orWhereHas('patient', function ($patientQ) use ($queryText) {
                        $patientQ->where('name', 'like', "%{$queryText}%")
                            ->orWhereHas('owner', function ($ownerQ) use ($queryText) {
                                $ownerQ->where('first_name', 'like', "%{$queryText}%")
                                    ->orWhere('last_name', 'like', "%{$queryText}%")
                                    ->orWhere('phone', 'like', "%{$queryText}%");
                            });
                    })
                    ->orWhereHas('veterinarian', function ($vetQ) use ($queryText) {
                        $vetQ->where('first_name', 'like', "%{$queryText}%")
                            ->orWhere('last_name', 'like', "%{$queryText}%");
                    })
                    ->orWhereHas('medicines.brand', function ($brandQ) use ($queryText) {
                        $brandQ->where('name', 'like', "%{$queryText}%")
                            ->orWhereHas('medicine', function ($medicineQ) use ($queryText) {
                                $medicineQ->where('name', 'like', "%{$queryText}%");
                            });
                    });
            });
        }

        if ($limit > 0) {
            $query->limit(min($limit, 50));
        }

        $wantsPaginated = $request->has('page') || $request->has('perPage') || $queryText !== '' || in_array($paymentType, ['cash', 'credit'], true) || $dateFrom !== '' || $dateTo !== '';
        if ($wantsPaginated) {
            $paginator = $query->paginate($perPage);
            $paginator->setCollection($paginator->getCollection()->map(fn($apt) => $this->formatAppointment($apt)));
            return response()->json($paginator);
        }

        $appointments = $query->get();
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
            // instead of existing ID, allow full patient object
            'patient' => 'nullable|array',
            'patient.name' => 'required_with:patient|string|max:120',
            'patient.species' => 'nullable|string|max:80',
            'patient.breed' => 'nullable|string|max:120',
            'patient.gender' => ['nullable', 'string', Rule::in(['male','female','unknown'])],
            'patient.age' => 'nullable|integer|min:0|max:100',
            'patient.ageYears' => 'nullable|integer|min:0|max:100',
            'patient.ageMonths' => 'nullable|integer|min:0|max:11',
            'patient.weight' => 'nullable|numeric|min:0',
            'patient.ownerId' => 'nullable|integer|exists:owners,id',
            'patient.owner' => 'nullable|array',
            'patient.owner.firstName' => 'nullable|string|max:80',
            'patient.owner.lastName' => 'nullable|string|max:80',
            'patient.owner.phone' => 'nullable|string|max:20',
            'patient.owner.email' => 'nullable|email|max:120',
            'veterinarianId' => 'nullable|integer|exists:veterinarians,id',
            'doctorCharge' => 'nullable|numeric|min:0',
            'surgeryCharge' => 'nullable|numeric|min:0',
            'otherCharge' => 'nullable|numeric|min:0',
            'otherChargeReason' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'medicines' => 'nullable|array',
            'medicines.*.medicineBrandId' => 'required|integer|exists:medicine_brands,id',
            'medicines.*.quantity' => 'nullable|numeric|min:0',
            'diagnosticReports' => 'nullable|array|max:25',
            'diagnosticReports.*.type' => 'required|string|max:32',
            'diagnosticReports.*.label' => 'nullable|string|max:255',
            'diagnosticReports.*.fileUrl' => 'required|url|max:2048',
            'diagnosticReports.*.filePublicId' => 'nullable|string|max:255',
            'diagnosticReports.*.mimeType' => 'nullable|string|max:128',
            'diagnosticReports.*.fileBytes' => 'nullable|integer|min:0',
            'diagnosticReports.*.reportedAt' => 'nullable|date',
            'vaccinationPlan' => 'nullable|array',
            'vaccinationPlan.vaccineName' => 'nullable|string|max:255',
            'vaccinationPlan.doseNumber' => 'nullable|integer|min:0|max:255',
            'vaccinationPlan.administeredAt' => 'nullable|date',
            'vaccinationPlan.nextDueAt' => 'nullable|date',
            'vaccinationPlan.remindBeforeDays' => 'nullable|integer|min:0|max:365',
            'vaccinationPlan.notes' => 'nullable|string',
            'paymentType' => 'nullable|in:cash,credit',
            'paymentStatus' => 'nullable|in:pending,paid',
            'settledAt' => 'nullable|date'
        ]);

        $medicinesPayload = $request->input('medicines', []);
        $reportsPayload = $request->input('diagnosticReports', []);
        $vaccinationPlan = $data['vaccinationPlan'] ?? null;

        return DB::transaction(function () use ($data, $medicinesPayload, $reportsPayload, $vaccinationPlan) {
            // create nested patient (and owner) if provided and no ID supplied
            $patientId = $data['patientId'] ?? null;
            if (!$patientId && isset($data['patient']) && !empty($data['patient']['name'] ?? '')) {
                $patientData = $data['patient'];
                $ownerId = $patientData['ownerId'] ?? null;
                if (!$ownerId && isset($patientData['owner'])) {
                    $ownerData = $patientData['owner'];
                    $owner = \App\Models\Owner::create([
                        'first_name' => $ownerData['firstName'] ?? null,
                        'last_name' => $ownerData['lastName'] ?? null,
                        'phone' => $ownerData['phone'] ?? null,
                        'email' => $ownerData['email'] ?? null,
                    ]);
                    $ownerId = $owner->id;
                }
                $patient = \App\Models\Patient::create([
                    'name' => $patientData['name'],
                    'species' => $patientData['species'] ?? null,
                    'breed' => $patientData['breed'] ?? null,
                    'gender' => $patientData['gender'] ?? null,
                    'age' => $patientData['ageYears'] ?? $patientData['age'] ?? null,
                    'age_months' => $patientData['ageMonths'] ?? null,
                    'weight' => isset($patientData['weight']) ? $patientData['weight'] : null,
                    'owner_id' => $ownerId,
                    'notes' => null,
                ]);
                $patientId = $patient->id;
            }

            $appointment = Appointment::create([
                'date' => $data['date'] ?? null,
                'reason' => $data['reason'] ?? null,
                'status' => $data['status'] ?? 'scheduled',
                'patient_id' => $patientId,
                'veterinarian_id' => $data['veterinarianId'] ?? null,
                'is_walk_in' => $data['isWalkIn'] ?? false,
                'doctor_charge' => $data['doctorCharge'] ?? 0,
                'surgery_charge' => $data['surgeryCharge'] ?? 0,
                'other_charge' => $data['otherCharge'] ?? 0,
                'other_charge_reason' => $data['otherChargeReason'] ?? null,
                'discount' => $data['discount'] ?? 0,
                'payment_type' => $data['paymentType'] ?? 'cash',
                'payment_status' => $data['paymentStatus'] ?? ($data['paymentType'] === 'credit' ? 'pending' : 'paid'),
                'settled_at' => $data['settledAt'] ?? null,
                'notes' => $data['notes'] ?? null
            ]);

            $medicinesTotal = 0;
            foreach ($medicinesPayload as $entry) {
                $brandId = isset($entry['medicineBrandId']) ? $entry['medicineBrandId'] : (isset($entry['medicine_brand_id']) ? $entry['medicine_brand_id'] : null);
                $qty = isset($entry['quantity']) ? max(0, floatval($entry['quantity'])) : 0;
                if (! $brandId || $qty <= 0) {
                    continue;
                }
                $brand = MedicineBrand::find($brandId);
                if (! $brand) {
                    continue;
                }

                $unitPrice = $brand->price ?? 0;
                $med = AppointmentMedicine::create([
                    'appointment_id' => $appointment->id,
                    'medicine_brand_id' => $brand->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice
                ]);

                $medicinesTotal += $unitPrice * $qty;
            }

            // foreach ($reportsPayload as $report) {
            //     AppointmentReport::create([
            //         'appointment_id' => $appointment->id,
            //         'patient_id' => $appointment->patient_id,
            //         'report_type' => $report['type'],
            //         'label' => $report['label'] ?? null,
            //         'file_url' => $report['fileUrl'],
            //         'file_public_id' => $report['filePublicId'] ?? null,
            //         'mime_type' => $report['mimeType'] ?? null,
            //         'file_bytes' => $report['fileBytes'] ?? null,
            //         'reported_at' => isset($report['reportedAt']) ? Carbon::parse($report['reportedAt']) : now(),
            //     ]);
            // }

            $gross = ($appointment->doctor_charge ?? 0) + ($appointment->surgery_charge ?? 0) + $medicinesTotal;
            $totalCharge = max(0, $gross - ($appointment->discount ?? 0));
            $appointment->total_charge = $totalCharge;
            $appointment->save();

            $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine', 'reports', 'vaccinations']);
            $this->syncVaccinationReminder($appointment, $vaccinationPlan);
            return response()->json($this->formatAppointment($appointment), 201);
        });
    }

    public function show($id)
    {
        $appointment = Appointment::with(['patient.owner', 'veterinarian', 'medicines.brand.medicine', 'reports', 'vaccinations'])->find($id);
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
            'surgeryCharge' => 'nullable|numeric|min:0',
            'otherCharge' => 'nullable|numeric|min:0',
            'otherChargeReason' => 'nullable|string|max:255',
            'discount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'medicines' => 'nullable|array',
            'diagnosticReports' => 'nullable|array|max:25',
            'diagnosticReports.*.type' => 'required|string|max:32',
            'diagnosticReports.*.label' => 'nullable|string|max:255',
            'diagnosticReports.*.fileUrl' => 'required|url|max:2048',
            'diagnosticReports.*.filePublicId' => 'nullable|string|max:255',
            'diagnosticReports.*.mimeType' => 'nullable|string|max:128',
            'diagnosticReports.*.fileBytes' => 'nullable|integer|min:0',
            'diagnosticReports.*.reportedAt' => 'nullable|date',
            'vaccinationPlan' => 'nullable|array',
            'vaccinationPlan.vaccineName' => 'nullable|string|max:255',
            'vaccinationPlan.doseNumber' => 'nullable|integer|min:0|max:255',
            'vaccinationPlan.administeredAt' => 'nullable|date',
            'vaccinationPlan.nextDueAt' => 'nullable|date',
            'vaccinationPlan.remindBeforeDays' => 'nullable|integer|min:0|max:365',
            'vaccinationPlan.notes' => 'nullable|string',
            'paymentType' => 'nullable|in:cash,credit',
            'paymentStatus' => 'nullable|in:pending,paid',
            'settledAt' => 'nullable|date'
        ]);

        $vaccinationPlan = $data['vaccinationPlan'] ?? null;

        return DB::transaction(function () use ($appointment, $data, $vaccinationPlan) {
            $appointment->update([
                'date' => $data['date'] ?? $appointment->date,
                'reason' => $data['reason'] ?? $appointment->reason,
                'status' => $data['status'] ?? $appointment->status,
                'patient_id' => $data['patientId'] ?? $appointment->patient_id,
                'veterinarian_id' => $data['veterinarianId'] ?? $appointment->veterinarian_id,
                'is_walk_in' => $data['isWalkIn'] ?? $appointment->is_walk_in,
                'doctor_charge' => $data['doctorCharge'] ?? $appointment->doctor_charge,
                'surgery_charge' => $data['surgeryCharge'] ?? $appointment->surgery_charge,
                'other_charge' => $data['otherCharge'] ?? $appointment->other_charge,
                'other_charge_reason' => $data['otherChargeReason'] ?? $appointment->other_charge_reason,
                'discount' => $data['discount'] ?? $appointment->discount,
                'payment_type' => $data['paymentType'] ?? $appointment->payment_type,
                'payment_status' => $data['paymentStatus'] ?? $appointment->payment_status,
                'settled_at' => $data['settledAt'] ?? $appointment->settled_at,
                'notes' => $data['notes'] ?? $appointment->notes
            ]);

            $medicinesTotal = null;

            if (isset($data['medicines'])) {
                // Replace medicines
                $appointment->medicines()->delete();
                $medicinesTotal = 0;
                foreach ($data['medicines'] as $entry) {
                    $brandId = isset($entry['medicineBrandId']) ? $entry['medicineBrandId'] : (isset($entry['medicine_brand_id']) ? $entry['medicine_brand_id'] : null);
                    $qty = isset($entry['quantity']) ? max(0, floatval($entry['quantity'])) : 0;
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
            }

            if ($medicinesTotal === null) {
                $medicinesTotal = $appointment->medicines()->get()->reduce(
                    fn ($carry, $medicine) => $carry + (($medicine->unit_price ?? 0) * ($medicine->quantity ?? 0)),
                    0
                );
            }

            $gross = ($appointment->doctor_charge ?? 0) + ($appointment->surgery_charge ?? 0) + $medicinesTotal;
            $appointment->total_charge = max(0, $gross - ($appointment->discount ?? 0));
            $appointment->save();

            if (array_key_exists('diagnosticReports', $data)) {
                $appointment->reports()->delete();
                foreach ($data['diagnosticReports'] ?? [] as $report) {
                    AppointmentReport::create([
                        'appointment_id' => $appointment->id,
                        'patient_id' => $appointment->patient_id,
                        'report_type' => $report['type'],
                        'label' => $report['label'] ?? null,
                        'file_url' => $report['fileUrl'],
                        'file_public_id' => $report['filePublicId'] ?? null,
                        'mime_type' => $report['mimeType'] ?? null,
                        'file_bytes' => $report['fileBytes'] ?? null,
                        'reported_at' => isset($report['reportedAt']) ? Carbon::parse($report['reportedAt']) : now(),
                    ]);
                }
            }

            $appointment->load(['patient.owner', 'veterinarian', 'medicines.brand.medicine', 'reports', 'vaccinations']);
            $this->syncVaccinationReminder($appointment, $vaccinationPlan);
            return response()->json($this->formatAppointment($appointment));
        });
    }

    public function sendInvoiceSms(Appointment $appointment)
    {
        if ($appointment->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed appointments can send invoices'
            ], 422);
        }

        $appointment->loadMissing(['patient.owner', 'medicines.brand.medicine']);
        $sent = $this->smsGateway->sendInvoiceForAppointment($appointment);

        if (! $sent) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice SMS could not be delivered'
            ], 422);
        }

        return response()->json(['success' => true]);
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
