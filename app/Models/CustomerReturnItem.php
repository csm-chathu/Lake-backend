<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_return_id',
        'medicine_brand_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
        'is_damaged',
        'restocked_at',
        'item_reason',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
        'is_damaged' => 'boolean',
        'restocked_at' => 'datetime',
    ];

    public function customerReturn()
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function medicineBrand()
    {
        return $this->belongsTo(MedicineBrand::class, 'medicine_brand_id');
    }
}
