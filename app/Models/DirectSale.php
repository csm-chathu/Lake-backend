<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'sale_reference',
        'subtotal',
        'discount',
        'service_charge',
        'total',
        'payment_type',
        'payment_status',
        'notes'
    ];

    protected $casts = [
        'date' => 'datetime',
        'subtotal' => 'float',
        'discount' => 'float',
        'service_charge' => 'float',
        'total' => 'float'
    ];

    public function items()
    {
        return $this->hasMany(DirectSaleItem::class);
    }
}
