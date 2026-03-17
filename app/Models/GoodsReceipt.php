<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_number',
        'supplier_id',
        'purchase_order_id',
        'receipt_date',
        'total_cost',
        'items',
        'notes',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_cost' => 'float',
        'items' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
