<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One pharmacy the rider collects from on the way to the customer. */
class OrderDeliveryStop extends Model
{
    protected $fillable = ['order_delivery_id', 'order_fulfillment_id', 'pharmacy_id', 'sequence', 'status', 'collected_at'];

    protected $casts = ['collected_at' => 'datetime'];

    public function delivery() { return $this->belongsTo(OrderDelivery::class, 'order_delivery_id'); }
    public function fulfillment() { return $this->belongsTo(OrderFulfillment::class, 'order_fulfillment_id'); }
    public function pharmacy() { return $this->belongsTo(Pharmacy::class); }
}
