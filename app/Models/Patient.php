<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'species',
        'breed',
        'age',
        'owner_id',
        'notes',
        'passbook_number'
    ];

    protected static function booted()
    {
        static::creating(function ($patient) {
            if (empty($patient->passbook_number)) {
                $patient->passbook_number = static::generatePassbookNumber();
            }
        });
    }

    public static function generatePassbookNumber()
    {
        $prefix = 'PB' . date('Y');
        for ($i = 0; $i < 10; $i++) {
            $candidate = $prefix . '-' . str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $exists = DB::table('patients')->where('passbook_number', $candidate)->exists();
            if (! $exists) {
                return $candidate;
            }
        }
        return $prefix . '-' . time();
    }

    public function owner()
    {
        return $this->belongsTo(Owner::class);
    }
}
