<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'source_type',
        'source_id',
        'patient_id',
        'owner_id',
        'customer_name',
        'subtotal',
        'discount',
        'tax',
        'total',
        'paid_amount',
        'refunded_amount',
        'due_amount',
        'status',
        'line_items',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'paid_amount' => 'float',
        'refunded_amount' => 'float',
        'due_amount' => 'float',
        'line_items' => 'array',
    ];

    public function transactions()
    {
        return $this->hasMany(InvoiceTransaction::class);
    }
}
