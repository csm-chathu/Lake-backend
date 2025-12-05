<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineBrand extends Model
{
    use HasFactory;

    protected $table = 'medicine_brands';

    protected $fillable = ['medicine_id', 'name', 'price'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
