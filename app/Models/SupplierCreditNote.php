<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierCreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_note_number',
        'supplier_id',
        'supplier_invoice_id',
        'credit_date',
        'total',
        'status',
        'items',
        'reason',
        'notes',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'total' => 'float',
        'items' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierInvoice()
    {
        return $this->belongsTo(SupplierInvoice::class);
    }
}
