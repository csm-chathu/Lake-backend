<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\PatientVaccination;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsGateway
{
    protected string $baseUrl;
    protected ?string $userId;
    protected ?string $apiKey;
    protected ?string $senderId;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('sms.base_url', 'https://smslenz.lk/api'), '/');
        $this->userId = config('sms.user_id');
        $this->apiKey = config('sms.api_key');
        $this->senderId = config('sms.sender_id');
    }

    public function sendInvoiceForAppointment(?Appointment $appointment): bool
    {
        if (! $appointment || $appointment->status !== 'completed') {
            return false;
        }

        $ownerPhone = optional($appointment->patient?->owner)->phone;
        $contact = $this->formatContact($ownerPhone);
        if (! $contact || ! $this->isConfigured()) {
            return false;
        }

        $patientName = $appointment->patient?->name ?? 'your pet';
        $dateLabel = optional($appointment->date)->timezone(config('app.timezone'))
            ?->format('d M, h:i A');
        $total = number_format((float) ($appointment->total_charge ?? 0), 2, '.', '');
        $doctorCharge = number_format((float) ($appointment->doctor_charge ?? 0), 2, '.', '');
        $surgeryCharge = number_format((float) ($appointment->surgery_charge ?? 0), 2, '.', '');
        $otherCharge = number_format((float) ($appointment->other_charge ?? 0), 2, '.', '');
        $otherChargeReason = trim((string) ($appointment->other_charge_reason ?? ''));
        $otherChargeLabel = $otherChargeReason !== ''
            ? sprintf('Other/Service (%s)', $otherChargeReason)
            : 'Other/Service';
        $medicineTotal = number_format($this->calculateMedicinesTotal($appointment), 2, '.', '');
        $discount = number_format((float) ($appointment->discount ?? 0), 2, '.', '');

        $message = sprintf(
            "Lake Clinic invoice for %s (%s). Doctor: LKR %s, Surgery: LKR %s, %s: LKR %s, Meds: LKR %s, Discount: LKR %s, Total: LKR %s. Thank you!",
            $patientName,
            $dateLabel ?? 'today',
            $doctorCharge,
            $surgeryCharge,
            $otherChargeLabel,
            $otherCharge,
            $medicineTotal,
            $discount,
            $total
        );

        $owner = $appointment->patient?->owner;

        return $this->sendSms($contact, $message, [
            'sms_type' => 'invoice',
            'recipient_name' => $owner ? trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) : null,
            'related_type' => Appointment::class,
            'related_id' => $appointment->id,
            'content' => [
                'patient' => $patientName,
                'appointment_id' => $appointment->id,
                'doctor_charge' => $appointment->doctor_charge,
                'surgery_charge' => $appointment->surgery_charge,
                'other_charge' => $appointment->other_charge,
                'other_charge_reason' => $appointment->other_charge_reason,
                'medicines_total' => $medicineTotal,
                'discount' => $appointment->discount,
                'total' => $appointment->total_charge,
                'occurred_at' => $appointment->date,
            ]
        ]);
    }

    public function sendVaccinationReminder(PatientVaccination $vaccination): bool
    {
        $vaccination->loadMissing('patient.owner');

        $patient = $vaccination->patient;
        $owner = $patient?->owner;
        $contact = $this->formatContact($owner?->phone);

        if (! $contact || ! $this->isConfigured()) {
            return false;
        }

        $patientName = $patient?->name ?? 'your pet';
        $vaccineName = $vaccination->vaccine_name;
        $dueDateLabel = optional($vaccination->next_due_at)
            ?->timezone(config('app.timezone'))
            ?->format('d M Y') ?? 'soon';

        $message = sprintf(
            'Reminder: %s is due for %s vaccine on %s. Please contact Lake Clinic to schedule.',
            $patientName,
            $vaccineName,
            $dueDateLabel
        );

        return $this->sendSms($contact, $message, [
            'sms_type' => 'vaccination_reminder',
            'recipient_name' => $owner ? trim(($owner->first_name ?? '') . ' ' . ($owner->last_name ?? '')) : null,
            'related_type' => PatientVaccination::class,
            'related_id' => $vaccination->id,
            'content' => [
                'patient' => $patientName,
                'vaccine' => $vaccineName,
                'due_date' => $vaccination->next_due_at,
                'remind_before_days' => $vaccination->remind_before_days,
            ]
        ]);
    }

    protected function sendSms(string $contact, string $message, array $meta = []): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('SMS gateway is not configured');
            return false;
        }

        try {
            $response = Http::asForm()->post($this->baseUrl . '/send-sms', [
                'user_id' => $this->userId,
                'api_key' => $this->apiKey,
                'sender_id' => $this->senderId,
                'contact' => $contact,
                'message' => Str::limit($message, 621, '')
            ]);

            if ($response->successful()) {
                $this->logSms($contact, $message, $meta);
                return true;
            }

            Log::warning('SMS gateway responded with error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to send SMS', ['message' => $exception->getMessage()]);
        }

        return false;
    }

    protected function logSms(string $contact, string $message, array $meta): void
    {
        try {
            $contentPayload = $meta['content'] ?? [];
            $contentPayload['message'] = $message;

            SmsLog::create([
                'sms_type' => $meta['sms_type'] ?? null,
                'recipient_contact' => $contact,
                'recipient_name' => $meta['recipient_name'] ?? null,
                'provider' => $meta['provider'] ?? 'smslenz',
                'related_type' => $meta['related_type'] ?? null,
                'related_id' => $meta['related_id'] ?? null,
                'content' => json_encode($contentPayload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to log SMS', ['message' => $exception->getMessage()]);
        }
    }

    protected function isConfigured(): bool
    {
        return ! empty($this->userId) && ! empty($this->apiKey) && ! empty($this->senderId);
    }

    protected function formatContact(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '+94')) {
            return $digits;
        }

        if (str_starts_with($digits, '94')) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+94' . substr($digits, 1);
        }

        return null;
    }

    protected function calculateMedicinesTotal(Appointment $appointment): float
    {
        if (! $appointment->relationLoaded('medicines')) {
            $appointment->loadMissing('medicines');
        }

        return $appointment->medicines->reduce(
            fn ($carry, $medicine) => $carry + (($medicine->unit_price ?? 0) * ($medicine->quantity ?? 0)),
            0.0
        );
    }
}
