<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasFactory;

    protected $table = 'stock_adjustments';

    protected $fillable = [
        'stock_item_id',
        'stock_batch_id',
        'type',
        'quantity',
        'before_quantity',
        'after_quantity',
        'reason',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }
}
