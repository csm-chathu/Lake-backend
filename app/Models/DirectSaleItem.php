<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'direct_sale_id',
        'medicine_brand_id',
        'quantity',
        'unit_price',
        'line_total'
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float'
    ];

    public function directSale()
    {
        return $this->belongsTo(DirectSale::class);
    }

    public function brand()
    {
        return $this->belongsTo(MedicineBrand::class, 'medicine_brand_id');
    }
}
