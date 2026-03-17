<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    use HasFactory;

    protected $table = 'stock_batches';

    protected $fillable = [
        'stock_item_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'cost_price',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'cost_price' => 'decimal:2',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }
}
