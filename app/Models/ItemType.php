<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    protected $table = 'item_types';

    protected $fillable = ['label', 'value', 'sort_order', 'active'];
}
