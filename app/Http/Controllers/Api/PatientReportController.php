<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientReportController extends Controller
{
    private function formatReport(AppointmentReport $report): array
    {
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

    public function index(Request $request)
    {
        $data = $request->validate([
            'patientId' => 'required|integer|exists:patients,id',
        ]);

        $reports = AppointmentReport::where('patient_id', $data['patientId'])
            ->orderByDesc('reported_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'patientId' => $data['patientId'],
            'reports' => $reports->map(fn ($report) => $this->formatReport($report)),
        ]);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'patientId' => 'required|integer|exists:patients,id',
            'reports' => 'nullable|array|max:20',
            'reports.*.type' => 'required|string|max:32',
            'reports.*.label' => 'nullable|string|max:255',
            'reports.*.fileUrl' => 'required|url|max:2048',
            'reports.*.filePublicId' => 'nullable|string|max:255',
            'reports.*.mimeType' => 'nullable|string|max:128',
            'reports.*.fileBytes' => 'nullable|integer|min:0',
            'reports.*.reportedAt' => 'nullable|date',
        ]);

        $patientId = $data['patientId'];
        $reportsPayload = $request->input('reports', []);

        $reports = DB::transaction(function () use ($patientId, $reportsPayload) {
            AppointmentReport::where('patient_id', $patientId)
                ->whereNull('appointment_id')
                ->delete();

            foreach ($reportsPayload as $report) {
                AppointmentReport::create([
                    'appointment_id' => null,
                    'patient_id' => $patientId,
                    'report_type' => $report['type'],
                    'label' => $report['label'] ?? null,
                    'file_url' => $report['fileUrl'],
                    'file_public_id' => $report['filePublicId'] ?? null,
                    'mime_type' => $report['mimeType'] ?? null,
                    'file_bytes' => $report['fileBytes'] ?? null,
                    'reported_at' => isset($report['reportedAt'])
                        ? Carbon::parse($report['reportedAt'])
                        : now(),
                ]);
            }

            return AppointmentReport::where('patient_id', $patientId)
                ->orderByDesc('reported_at')
                ->orderByDesc('created_at')
                ->get();
        });

        return response()->json([
            'patientId' => $patientId,
            'reports' => $reports->map(fn ($report) => $this->formatReport($report)),
        ]);
    }
}
