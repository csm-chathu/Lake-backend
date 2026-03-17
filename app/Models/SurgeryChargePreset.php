<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurgeryChargePreset extends Model
{
    use HasFactory;

    protected $table = 'surgery_charge_presets';

    protected $fillable = [
        'name',
        'label',
        'value',
        'active'
    ];
}
