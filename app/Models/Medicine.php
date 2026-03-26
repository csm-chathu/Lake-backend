<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

        protected $fillable = ['name', 'description', 'type'];

        protected $casts = [
            'type' => 'array',
        ];
    public function brands()
    {
        return $this->hasMany(MedicineBrand::class);
    }
}
