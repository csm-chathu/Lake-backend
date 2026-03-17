<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DayEndCashClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_date',
        'opening_cash',
        'cash_in',
        'cash_out',
        'expected_cash',
        'counted_cash',
        'variance',
        'closed_by',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'business_date' => 'date',
        'opening_cash' => 'float',
        'cash_in' => 'float',
        'cash_out' => 'float',
        'expected_cash' => 'float',
        'counted_cash' => 'float',
        'variance' => 'float',
        'closed_at' => 'datetime',
    ];
}
