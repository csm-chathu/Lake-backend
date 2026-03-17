<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_month',
        'base_salary',
        'bonus_amount',
        'deductions',
        'net_salary',
        'payment_date',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'payment_date' => 'date',
        'base_salary' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
