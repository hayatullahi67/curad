<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSale extends Model
{
    protected $fillable = [
        'staff_id',
        'pharmacy_id',
        'receipt_no',
        'medication_id',
        'medication_name',
        'batch_number',
        'is_rx',
        'quantity',
        'unit_price',
        'total_amount',
        'customer_name',
        'customer_phone',
        'payment_method',
        'discount_amount',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function medication()
    {
        return $this->belongsTo(Medication::class, 'medication_id');
    }
}
