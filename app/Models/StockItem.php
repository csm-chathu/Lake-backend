<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use HasFactory;

    protected $table = 'stock_items';

    protected $fillable = [
        'name',
        'sku',
        'quantity',
        'purchase_price',
        'sale_price',
        'notes',
    ];

    public function batches()
    {
        return $this->hasMany(StockBatch::class);
    }

    public function adjustments()
    {
        return $this->hasMany(StockAdjustment::class);
    }
}
