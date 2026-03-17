<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'species',
        'breed',
        'gender',
        'age',
        'age_months',
        'weight',
        'owner_id',
        'notes',
        'passbook_number'
    ];

    protected static function booted()
    {
        // Ensure a passbook number exists before insert so DB constraints
        // (non-null, unique) do not fail.
        static::creating(function ($patient) {
            if (empty($patient->passbook_number)) {
                $patient->passbook_number = static::previewNextPassbookNumber();
            }
        });

        // After creation, prefer the definitive `PB-{id}` format using the
        // real id assigned by the database. Save quietly to avoid event loops.
        static::created(function ($patient) {
            $expected = static::generatePassbookNumber($patient->id);
            if ($patient->passbook_number !== $expected) {
                $patient->passbook_number = $expected;
                $patient->saveQuietly();
            }
        });
    }

    /**
     * Generate a passbook number. If an $id is provided, use it so the
     * passbook number is sequential and deterministic; otherwise fall back
     * to the previous random strategy.
     */
    public static function generatePassbookNumber($id = null)
    {
        return 'PB-' . (string) ((int) ($id ?? 0));
    }

    /**
     * Preview the next passbook number using max(id)+1 so the format stays
     * deterministic as PB-1, PB-2, ... across environments.
     */
    public static function previewNextPassbookNumber()
    {
        $nextId = ((int) static::max('id')) + 1;
        return static::generatePassbookNumber($nextId);
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }

    public function vaccinations()
    {
        return $this->hasMany(PatientVaccination::class);
    }
}
