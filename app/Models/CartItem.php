<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_key', 'medication_id', 'quantity'];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /** The pharmacy's copy the customer was looking at; used for name and photo. */
    public function medication()
    {
        return $this->belongsTo(Medication::class);
    }
}
