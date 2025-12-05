<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'reason',
        'status',
        'is_walk_in',
        'patient_id',
        'veterinarian_id',
        'doctor_charge',
        'total_charge',
        'discount',
        'payment_type',
        'payment_status',
        'settled_at',
        'notes'
    ];

    protected $casts = [
        'date' => 'datetime',
        'settled_at' => 'datetime',
        'is_walk_in' => 'boolean'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function veterinarian()
    {
        return $this->belongsTo(Veterinarian::class);
    }

    public function medicines()
    {
        return $this->hasMany(AppointmentMedicine::class);
    }
}
