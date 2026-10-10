<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'medication_id',
        'quantity',
        'status',
        'is_paid',
        'patient_charge',
        'vendor_commission',
        'expires_at',
        'pickup_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }
}
