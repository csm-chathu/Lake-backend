<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_reference',
        'return_date',
        'customer_name',
        'original_sale_ref',
        'reason',
        'status',
        'refund_method',
        'refund_amount',
        'notes',
    ];

    protected $casts = [
        'return_date'   => 'date',
        'refund_amount' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(CustomerReturnItem::class);
    }
}
