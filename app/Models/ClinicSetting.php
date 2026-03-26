<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    use HasFactory;

    protected $table = 'clinic_settings';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'description',
        'pos_description',
        'timezone',
        'currency_code',
        'logo_url',
        'hero_image_url',
        'sms_sender_id',
        'shop_type'
    ];
}
