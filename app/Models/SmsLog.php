<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'sms_type',
        'recipient_contact',
        'recipient_name',
        'provider',
        'related_type',
        'related_id',
        'content',
    ];
}
