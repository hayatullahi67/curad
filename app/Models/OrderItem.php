<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One product and quantity in an order. Name and price are kept as they were when the order was placed. */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'order_fulfillment_id', 'medication_id', 'product_name', 'dosage', 'photo',
        'unit_price', 'quantity', 'line_total',
    ];

    protected $casts = ['unit_price' => 'float', 'line_total' => 'float', 'quantity' => 'integer'];

    public function order() { return $this->belongsTo(Order::class); }
    public function fulfillment() { return $this->belongsTo(OrderFulfillment::class, 'order_fulfillment_id'); }
    public function medication() { return $this->belongsTo(Medication::class); }

    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->attributes['photo'] ?? null;

        if (blank($photo)) {
            return null;
        }

        return preg_match('#^https?://#i', $photo) ? $photo : asset('storage/' . ltrim($photo, '/'));
    }
}
