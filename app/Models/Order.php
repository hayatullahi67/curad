<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer's whole shopping request. It never belongs to one pharmacy:
 * each pharmacy's share is an OrderFulfillment.
 */
class Order extends Model
{
    /** Order states from the PRD (section 19), with the wording customers see. */
    public const STATUSES = [
        'awaiting_confirmation' => 'Waiting for pharmacy',
        'partially_confirmed' => 'Partly confirmed',
        'processing' => 'Confirmed',
        'ready_for_pickup' => 'Ready for pickup',
        'dispatched' => 'On the way',
        'completed' => 'Completed',
        'partially_fulfilled' => 'Partly fulfilled',
        'cancelled' => 'Cancelled',
    ];

    public const PAYMENT_METHODS = [
        'wallet' => 'CuraD wallet',
        'pay_on_collection' => 'Pay on collection',
    ];

    protected $fillable = [
        'order_number', 'user_id', 'status', 'fulfillment_method', 'subtotal', 'service_fee', 'delivery_fee',
        'total', 'refunded_total', 'payment_method', 'payment_status', 'contact_phone', 'customer_note',
        'placed_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'float', 'service_fee' => 'float', 'delivery_fee' => 'float', 'total' => 'float',
        'refunded_total' => 'float', 'placed_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function fulfillments() { return $this->hasMany(OrderFulfillment::class); }
    public function payments() { return $this->hasMany(OrderPayment::class); }
    public function delivery() { return $this->hasOne(OrderDelivery::class); }

    public function isDelivery(): bool
    {
        return $this->fulfillment_method === 'delivery';
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['completed', 'partially_fulfilled', 'cancelled'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /** Colour family for the status pill: amber (waiting), sky (moving), emerald (done), slate (closed). */
    public function statusTone(): string
    {
        return match ($this->status) {
            'awaiting_confirmation', 'partially_confirmed' => 'amber',
            'processing', 'dispatched' => 'sky',
            'ready_for_pickup', 'completed' => 'emerald',
            'partially_fulfilled' => 'amber',
            default => 'slate',
        };
    }

    public function paymentLabel(): string
    {
        if ($this->payment_method === 'pay_on_collection') {
            return $this->isDelivery() ? 'Pay on delivery' : 'Pay at the pharmacy';
        }

        return self::PAYMENT_METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'refunded' => 'Refunded',
            'partially_refunded' => 'Partly refunded',
            default => 'Not paid yet',
        };
    }

    /**
     * What the order is worth now, after any pharmacy's share was dropped:
     * the remaining products, their part of the service fee, and delivery.
     */
    public function currentTotal(): float
    {
        $active = $this->fulfillments->filter->isActive();

        if ($active->isEmpty() || $this->subtotal <= 0) {
            return 0.0;
        }

        $subtotal = $active->sum('subtotal');

        return round($subtotal + $this->service_fee * $subtotal / $this->subtotal + $this->delivery_fee, 2);
    }

    /** The customer may cancel until a pharmacy has accepted its part (older orders: until it is packed). */
    public function canBeCancelledByCustomer(): bool
    {
        $active = $this->fulfillments->filter->isActive();

        return $active->isNotEmpty()
            && $active->every(fn (OrderFulfillment $fulfillment) => in_array($fulfillment->status, ['awaiting_confirmation', 'confirmed'], true));
    }
}
