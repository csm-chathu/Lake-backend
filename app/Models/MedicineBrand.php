<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineBrand extends Model
{
    use HasFactory;

    protected $table = 'medicine_brands';

    protected $fillable = [
        'medicine_id',
        'name',
        'price',
        'wholesale_price',
        'stock',
        'expiry_date',
        'barcode',
        'supplier_id',
        'batch_number',
        'image_url'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches()
    {
        return $this->hasMany(MedicineBrandBatch::class, 'medicine_brand_id');
    }
}
