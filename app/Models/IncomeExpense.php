<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'amount',
        'user_id',
    ];

    /**
     * Get the user that owns the income/expense.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
