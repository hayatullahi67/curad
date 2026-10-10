<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The delivery of one order: where it goes, what it costs, who carries it.
 * It has one stop per pharmacy, in the order the rider should visit them.
 */
class OrderDelivery extends Model
{
    public const STATUSES = [
        'pending' => 'Waiting for rider',
        'picking_up' => 'Rider collecting',
        'in_transit' => 'On the way',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_id', 'status', 'address', 'latitude', 'longitude', 'recipient_name', 'recipient_phone', 'fee',
        'distance_km', 'duration_minutes', 'fee_multiplier', 'delivery_code', 'rider_name', 'rider_phone', 'dispatched_at', 'delivered_at', 'delivered_by',
    ];

    protected $casts = [
        'fee' => 'float', 'distance_km' => 'float', 'duration_minutes' => 'integer', 'fee_multiplier' => 'float', 'latitude' => 'float', 'longitude' => 'float',
        'dispatched_at' => 'datetime', 'delivered_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function stops() { return $this->hasMany(OrderDeliveryStop::class)->orderBy('sequence'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** Link that opens the drop-off point in a maps app. */
    public function mapUrl(): string
    {
        $query = $this->latitude && $this->longitude ? "{$this->latitude},{$this->longitude}" : $this->address;

        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query);
    }
}
