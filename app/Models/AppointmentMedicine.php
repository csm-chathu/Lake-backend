<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentMedicine extends Model
{
    use HasFactory;

    protected $fillable = ['appointment_id', 'medicine_brand_id', 'quantity', 'unit_price'];

    public function brand()
    {
        return $this->belongsTo(MedicineBrand::class, 'medicine_brand_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
