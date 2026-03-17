<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorChargePreset extends Model
{
    use HasFactory;

    protected $table = 'doctor_charge_presets';

    protected $fillable = [
        'name',
        'label',
        'value',
        'active'
    ];
}
