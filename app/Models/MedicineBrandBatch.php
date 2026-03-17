<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineBrandBatch extends Model
{
    use HasFactory;

    protected $table = 'medicine_brand_batches';

    protected $fillable = [
        'medicine_brand_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'barcode',
        'supplier_id'
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public function brand()
    {
        return $this->belongsTo(MedicineBrand::class, 'medicine_brand_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
