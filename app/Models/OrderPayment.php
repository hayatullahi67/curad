<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Money moving for an order: the customer's payment, and any refund back to them. */
class OrderPayment extends Model
{
    protected $fillable = ['order_id', 'order_fulfillment_id', 'type', 'method', 'amount', 'status', 'reference', 'note', 'paid_at'];

    protected $casts = ['amount' => 'float', 'paid_at' => 'datetime'];

    public function order() { return $this->belongsTo(Order::class); }
}
