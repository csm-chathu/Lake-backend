<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role',
        'phone',
        'email',
        'basic_salary',
        'status',
        'join_date',
        'notes',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'join_date' => 'date',
    ];

    public function salaryPayments()
    {
        return $this->hasMany(SalaryPayment::class);
    }

    public function bonuses()
    {
        return $this->hasMany(EmployeeBonus::class);
    }
}
