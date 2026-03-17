<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_invoice_number',
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax',
        'total',
        'paid_amount',
        'credited_amount',
        'due_amount',
        'status',
        'items',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'paid_amount' => 'float',
        'credited_amount' => 'float',
        'due_amount' => 'float',
        'items' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
