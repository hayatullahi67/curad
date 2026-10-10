<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One pharmacy's share of an order. It has its own status, so each pharmacy
 * works through its part without waiting for the others.
 */
class OrderFulfillment extends Model
{
    public const STATUSES = [
        'awaiting_confirmation' => 'New',
        'confirmed' => 'Preparing',
        'ready' => 'Accepted',
        'dispatched' => 'With rider',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'order_id', 'pharmacy_id', 'status', 'subtotal', 'commission', 'payout_amount', 'paid_out', 'pickup_code',
        'handled_by', 'reject_reason', 'confirmed_at', 'ready_at', 'dispatched_at', 'completed_at', 'closed_at',
    ];

    protected $casts = [
        'subtotal' => 'float', 'commission' => 'float', 'payout_amount' => 'float', 'paid_out' => 'boolean',
        'confirmed_at' => 'datetime', 'ready_at' => 'datetime', 'dispatched_at' => 'datetime',
        'completed_at' => 'datetime', 'closed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function pharmacy() { return $this->belongsTo(Pharmacy::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function stop() { return $this->hasOne(OrderDeliveryStop::class); }

    /** Still part of the order (not rejected by the pharmacy or cancelled). */
    public function isActive(): bool
    {
        return ! in_array($this->status, ['rejected', 'cancelled'], true);
    }

    public function statusLabel(): string
    {
        if ($this->status === 'completed') {
            return $this->order?->isDelivery() ? 'Delivered' : 'Collected';
        }

        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'awaiting_confirmation' => 'amber',
            'confirmed', 'dispatched' => 'sky',
            'ready', 'completed' => 'emerald',
            'rejected' => 'rose',
            default => 'slate',
        };
    }

    /** Short reference the pharmacy and customer can both quote, e.g. CRD-000123-2. */
    public function reference(): string
    {
        return $this->order->order_number . '-' . $this->id;
    }
}
