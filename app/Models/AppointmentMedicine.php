<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentMedicine extends Model
{
    use HasFactory;

    // quantity can now be fractional (e.g. 0.5) so cast accordingly
    protected $fillable = ['appointment_id', 'medicine_brand_id', 'quantity', 'unit_price'];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
    ];

    public function brand()
    {
        return $this->belongsTo(MedicineBrand::class, 'medicine_brand_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
