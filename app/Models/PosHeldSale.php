<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A sale parked at the till with "Save for later", waiting to be resumed.
 */
class PosHeldSale extends Model
{
    protected $fillable = ['pharmacy_id', 'user_id', 'customer_id', 'customer_name', 'customer_phone', 'items', 'discount_amount'];

    protected $casts = ['items' => 'array', 'discount_amount' => 'float'];

    public function user() { return $this->belongsTo(User::class); }
}
