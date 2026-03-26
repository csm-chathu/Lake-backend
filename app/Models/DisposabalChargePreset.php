<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisposabalChargePreset extends Model
{
    use HasFactory;

    protected $table = 'disposabal_charge_presets';

    protected $fillable = [
        'name',
        'label',
        'value',
        'active'
    ];
}
