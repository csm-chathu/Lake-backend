<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientVaccination extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'vaccine_name',
        'dose_number',
        'administered_at',
        'next_due_at',
        'remind_before_days',
        'reminder_status',
        'reminder_sent_at',
        'reminder_attempts',
        'notes',
    ];

    protected $casts = [
        'administered_at' => 'date',
        'next_due_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'dose_number' => 'integer',
        'remind_before_days' => 'integer',
        'reminder_attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (PatientVaccination $vaccination) {
            if ($vaccination->isDirty('next_due_at') || $vaccination->isDirty('remind_before_days')) {
                $vaccination->reminder_status = self::STATUS_PENDING;
                $vaccination->reminder_sent_at = null;
                $vaccination->reminder_attempts = 0;
            }
        });
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeDueForReminder($query)
    {
        $now = Carbon::now();

        return $query
            ->where('reminder_status', self::STATUS_PENDING)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('next_due_at')
            ->whereRaw('DATE_SUB(next_due_at, INTERVAL remind_before_days DAY) <= ?', [$now]);
    }

    public function markReminderSent(): void
    {
        $this->reminder_status = self::STATUS_SENT;
        $this->reminder_sent_at = now();
        $this->reminder_attempts = ($this->reminder_attempts ?? 0) + 1;
        $this->save();
    }

    public function markReminderFailed(): void
    {
        $this->reminder_status = self::STATUS_FAILED;
        $this->reminder_attempts = ($this->reminder_attempts ?? 0) + 1;
        $this->save();
    }
}
