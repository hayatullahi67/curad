<?php

namespace App\Services\Marketplace;

use App\Models\Cart;
use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderFulfillment;
use App\Models\OrderPayment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Everything that changes an online order: placing it, each pharmacy working
 * through its share, handing over to the customer or a rider, cancelling.
 *
 * Stock rules (PRD section 20): units are reserved the moment the order is
 * placed, leave the shelf when they are handed over, and are released if
 * the pharmacy rejects, the customer cancels or nobody confirms in time.
 *
 * Money rules: nothing is held in between. A wallet payment goes straight
 * to each pharmacy's wallet, less CuraD's commission, and is reversed if
 * that pharmacy's share is cancelled.
 */
class OrderService
{
    public function __construct(private FulfillmentPlanner $planner)
    {
    }

    /**
     * Turns a cart into an order.
     *
     * @param  array{method: string, payment_method: string, signature: string, phone: ?string, note: ?string, address: ?string, latitude: ?float, longitude: ?float, pin: ?string}  $options
     */
    public function place(User $user, Cart $cart, array $options): Order
    {
        return DB::transaction(function () use ($user, $cart, $options) {
            $wanted = $cart->items()->pluck('quantity', 'product_key');

            if ($wanted->isEmpty()) {
                throw new OrderException('Your cart is empty.');
            }

            // Hold every pharmacy's row for these products until the order is
            // saved, so two customers cannot both get the last unit.
            Medication::whereIn(DB::raw('LOWER(TRIM(name))'), $wanted->keys()->all())->lockForUpdate()->get();

            $plan = $this->planner->plan($wanted, $options['method'], $options['latitude'] ?? null, $options['longitude'] ?? null);

            if ($plan['unavailable'] || ! $plan['groups']) {
                throw new OrderException('Something in your cart just went out of stock. Please review your order.');
            }

            if (! hash_equals($plan['signature'], (string) $options['signature'])) {
                throw new OrderException('Prices or stock changed while you were checking out. Please review the updated order.');
            }

            $payer = User::whereKey($user->id)->lockForUpdate()->first();

            if ($options['payment_method'] === 'wallet') {
                if (! $payer->transaction_pin || ! Hash::check((string) ($options['pin'] ?? ''), $payer->transaction_pin)) {
                    throw new OrderException('The wallet PIN is not correct.');
                }

                if ((float) $payer->wallet_balance < $plan['total']) {
                    throw new OrderException('Your wallet balance is not enough for this order.');
                }
            }

            $order = Order::create([
                'order_number' => 'TMP-' . Str::random(14),
                'user_id' => $payer->id,
                'status' => 'awaiting_confirmation',
                'fulfillment_method' => $options['method'],
                'subtotal' => $plan['subtotal'],
                'service_fee' => $plan['service_fee'],
                'delivery_fee' => $plan['delivery_fee'],
                'total' => $plan['total'],
                'payment_method' => $options['payment_method'],
                'payment_status' => 'pending',
                'contact_phone' => $options['phone'] ?? $payer->phone,
                'customer_note' => $options['note'] ?? null,
                'placed_at' => now(),
            ]);
            $order->update(['order_number' => 'CRD-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

            $commissionRate = config('curad.marketplace.commission_percent') / 100;
            $fulfillments = [];

            foreach ($plan['groups'] as $group) {
                $commission = round($group['subtotal'] * $commissionRate, 2);

                $fulfillment = $order->fulfillments()->create([
                    'pharmacy_id' => $group['pharmacy']->id,
                    'status' => 'awaiting_confirmation',
                    'subtotal' => $group['subtotal'],
                    'commission' => $commission,
                    'payout_amount' => round($group['subtotal'] - $commission, 2),
                    'pickup_code' => $this->code(),
                ]);

                foreach ($group['lines'] as $line) {
                    $fulfillment->items()->create([
                        'order_id' => $order->id,
                        'medication_id' => $line['offer']->id,
                        'product_name' => $line['offer']->name,
                        'dosage' => $line['offer']->dosage,
                        'photo' => $line['offer']->getRawOriginal('photo'),
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                    ]);

                    Medication::whereKey($line['offer']->id)->increment('reserved_stock', $line['quantity']);
                }

                $fulfillments[$group['pharmacy']->id] = $fulfillment;
            }

            if ($options['method'] === 'delivery') {
                $delivery = $order->delivery()->create([
                    'status' => 'pending',
                    'address' => $options['address'],
                    'latitude' => $options['latitude'] ?? null,
                    'longitude' => $options['longitude'] ?? null,
                    'recipient_name' => $payer->name ?: 'Customer',
                    'recipient_phone' => $options['phone'] ?? $payer->phone,
                    'fee' => $plan['delivery_fee'],
                    'distance_km' => $plan['delivery']['distance_km'],
                    'duration_minutes' => $plan['delivery']['minutes'],
                    'fee_multiplier' => $plan['delivery']['multiplier'],
                    'delivery_code' => $this->code(),
                ]);

                foreach ($plan['delivery']['sequence'] as $index => $pharmacyId) {
                    $delivery->stops()->create([
                        'order_fulfillment_id' => $fulfillments[$pharmacyId]->id,
                        'pharmacy_id' => $pharmacyId,
                        'sequence' => $index + 1,
                    ]);
                }
            }

            if ($options['payment_method'] === 'wallet') {
                $reference = 'PAY-' . strtoupper(Str::random(12));
                $this->moveWallet($payer, $plan['total'], 'debit', "Order {$order->order_number}", $reference);
                $this->recordPayment($order, 'payment', 'wallet', $plan['total'], 'paid', $reference);

                foreach ($fulfillments as $fulfillment) {
                    $this->payPharmacy($fulfillment);
                }

                $order->update(['payment_status' => 'paid']);
            } else {
                $this->recordPayment($order, 'payment', 'pay_on_collection', $plan['total'], 'pending', 'DUE-' . strtoupper(Str::random(12)));
            }

            foreach ($fulfillments as $fulfillment) {
                $this->notify(
                    $fulfillment->pharmacy->user_id,
                    'New online order',
                    "Order {$order->order_number} for {$fulfillment->pharmacy->name} needs your confirmation: " . $fulfillment->items->count() . ' ' . Str::plural('product', $fulfillment->items->count()) . ', ₦' . number_format($fulfillment->subtotal, 2) . '.',
                    'pharmacy'
                );
                Audit::log('order.received', "Online order {$order->order_number} received (₦" . number_format($fulfillment->subtotal, 2) . ')', $fulfillment->pharmacy_id, $fulfillment);
            }

            $cart->items()->delete();

            return $order;
        });
    }

    /**
     * The pharmacy agrees to supply its share. Accepting is the only step
     * before the hand-over: the order is then ready for the rider
     * (delivery) or for the customer to collect (pickup).
     */
    public function accept(OrderFulfillment $fulfillment, User $staff): void
    {
        $this->transition($fulfillment, ['awaiting_confirmation'], function (OrderFulfillment $fulfillment) use ($staff) {
            $fulfillment->update(['status' => 'ready', 'confirmed_at' => now(), 'ready_at' => now(), 'handled_by' => $staff->id]);
            $order = $fulfillment->order;

            $this->notify(
                $order->user_id,
                $order->isDelivery() ? 'Order confirmed' : 'Ready for pickup',
                $order->isDelivery()
                    ? "{$fulfillment->pharmacy->name} accepted your order {$order->order_number}. You will be told when the rider has it."
                    : "{$fulfillment->pharmacy->name} accepted your order {$order->order_number}. Come and collect it, and show pickup code {$fulfillment->pickup_code} at the counter."
            );
            Audit::log('order.accepted', "Accepted online order {$order->order_number}", $fulfillment->pharmacy_id, $fulfillment);
        });
    }

    /** For orders accepted before accepting and getting ready became one step. */
    public function markReady(OrderFulfillment $fulfillment, User $staff): void
    {
        $this->transition($fulfillment, ['confirmed'], function (OrderFulfillment $fulfillment) use ($staff) {
            $fulfillment->update(['status' => 'ready', 'ready_at' => now(), 'handled_by' => $staff->id]);
            $order = $fulfillment->order;

            $this->notify(
                $order->user_id,
                $order->isDelivery() ? 'Order packed' : 'Ready for pickup',
                $order->isDelivery()
                    ? "{$fulfillment->pharmacy->name} has packed your order {$order->order_number}. It is waiting for the rider."
                    : "Your order {$order->order_number} is ready at {$fulfillment->pharmacy->name}. Show pickup code {$fulfillment->pickup_code} at the counter."
            );
            Audit::log('order.ready', "Marked online order {$order->order_number} ready", $fulfillment->pharmacy_id, $fulfillment);
        });
    }

    /** The pharmacy cannot supply its share. Stock is released and any payment for it returned. */
    public function reject(OrderFulfillment $fulfillment, ?User $staff, string $reason): void
    {
        $this->transition($fulfillment, ['awaiting_confirmation', 'confirmed', 'ready'], function (OrderFulfillment $fulfillment) use ($staff, $reason) {
            $this->close($fulfillment, 'rejected', $reason, $staff);

            $order = $fulfillment->order;
            $this->notify(
                $order->user_id,
                'Part of your order was cancelled',
                "{$fulfillment->pharmacy->name} could not supply its part of order {$order->order_number}: {$reason}"
                    . ($order->payment_method === 'wallet' ? ' The money for those items has been returned to your wallet.' : ' You will not be charged for those items.')
            );
            Audit::log('order.rejected', "Rejected online order {$order->order_number}: {$reason}", $fulfillment->pharmacy_id, $fulfillment);
        });
    }

    /** Pickup: the customer shows the code and takes the items. */
    public function collect(OrderFulfillment $fulfillment, User $staff, string $code): void
    {
        $this->transition($fulfillment, ['ready'], function (OrderFulfillment $fulfillment) use ($staff, $code) {
            if ($fulfillment->order->isDelivery()) {
                throw new OrderException('This order is for delivery. Hand it to the rider from the Logistics page.');
            }

            if (! hash_equals(strtoupper($fulfillment->pickup_code), strtoupper(trim($code)))) {
                throw new OrderException('That pickup code does not match this order. Check it with the customer.');
            }

            $this->takeFromShelf($fulfillment, $staff, 'collected by customer');
            $fulfillment->update(['status' => 'completed', 'completed_at' => now(), 'handled_by' => $staff->id]);

            $this->notify($fulfillment->order->user_id, 'Order collected', "You collected your items for order {$fulfillment->order->order_number} at {$fulfillment->pharmacy->name}. Thank you!");
            Audit::log('order.collected', "Handed over online order {$fulfillment->order->order_number} to the customer", $fulfillment->pharmacy_id, $fulfillment);
        });
    }

    /** Delivery: the pharmacy gives its package to the rider. */
    public function handToRider(OrderFulfillment $fulfillment, User $staff, string $riderName, string $riderPhone): void
    {
        $this->transition($fulfillment, ['ready'], function (OrderFulfillment $fulfillment) use ($staff, $riderName, $riderPhone) {
            $order = $fulfillment->order;
            $delivery = $order->delivery;

            if (! $order->isDelivery() || ! $delivery) {
                throw new OrderException('This order is for pickup, not delivery.');
            }

            $this->takeFromShelf($fulfillment, $staff, 'handed to rider');
            $fulfillment->update(['status' => 'dispatched', 'dispatched_at' => now(), 'handled_by' => $staff->id]);
            $fulfillment->stop?->update(['status' => 'collected', 'collected_at' => now()]);

            // One rider carries the whole order, so the first pharmacy to
            // hand over records who that is.
            $delivery->update([
                'rider_name' => $delivery->rider_name ?: $riderName,
                'rider_phone' => $delivery->rider_phone ?: $riderPhone,
                'dispatched_at' => $delivery->dispatched_at ?: now(),
            ]);

            Audit::log('order.dispatched', "Handed online order {$order->order_number} to rider {$riderName}", $fulfillment->pharmacy_id, $fulfillment);
        });

        $delivery = $fulfillment->order->fresh()->delivery;

        if ($delivery?->status === 'in_transit') {
            $this->notify($fulfillment->order->user_id, 'Your order is on the way', "Rider {$delivery->rider_name} ({$delivery->rider_phone}) is bringing order {$fulfillment->order->order_number}. When it is in your hands, open the order and press “Mark as delivered”.");
        }
    }

    /**
     * The rider has reached the customer, and the customer says so. Only the
     * customer closes a delivery; an order is never closed on its own.
     */
    public function deliver(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            $delivery = $order->delivery;

            if (! $delivery || $delivery->status !== 'in_transit') {
                throw new OrderException('This order is not out for delivery yet.');
            }

            foreach ($order->fulfillments()->where('status', 'dispatched')->get() as $fulfillment) {
                $fulfillment->update(['status' => 'completed', 'completed_at' => now()]);
                Audit::log('order.delivered', "Online order {$order->order_number} delivered to the customer", $fulfillment->pharmacy_id, $fulfillment);
                $this->notify($fulfillment->pharmacy->user_id, 'Order delivered', "Order {$order->order_number} was delivered to the customer.", 'pharmacy');
            }

            $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'delivered_by' => 'customer']);
            $this->sync($order);
        });
    }

    /** The customer changes their mind before a pharmacy has accepted the order. */
    public function cancel(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->canBeCancelledByCustomer()) {
                throw new OrderException('A pharmacy has already accepted this order, so it can no longer be cancelled here. Please message the pharmacy.');
            }

            foreach ($order->fulfillments->filter->isActive() as $fulfillment) {
                $this->close($fulfillment, 'cancelled', $reason, null, false);
                $this->notify($fulfillment->pharmacy->user_id, 'Order cancelled', "The customer cancelled order {$order->order_number}. The stock has been released.", 'pharmacy');
                Audit::log('order.cancelled', "Customer cancelled online order {$order->order_number}", $fulfillment->pharmacy_id, $fulfillment);
            }

            $order->update(['cancel_reason' => Str::limit($reason, 250, '')]);
            $this->sync($order->fresh());
        });
    }

    /** Rejects shares no pharmacy answered within the allowed time. */
    public function expireUnanswered(): int
    {
        $deadline = now()->subHours((int) config('curad.marketplace.confirmation_hours'));
        $stale = OrderFulfillment::where('status', 'awaiting_confirmation')->where('created_at', '<', $deadline)->get();

        foreach ($stale as $fulfillment) {
            try {
                $this->reject($fulfillment, null, 'The pharmacy did not confirm in time.');
            } catch (OrderException) {
                // Someone acted on it in the meantime.
            }
        }

        return $stale->count();
    }

    /** Works out the order's overall state from its pharmacies' shares. */
    public function sync(Order $order): void
    {
        $order->load('fulfillments', 'delivery');
        $all = $order->fulfillments;
        $active = $all->filter->isActive();
        $statuses = $active->pluck('status')->unique();
        $dropped = $all->count() > $active->count();

        $status = match (true) {
            $active->isEmpty() => 'cancelled',
            $statuses->all() === ['completed'] => $dropped ? 'partially_fulfilled' : 'completed',
            $statuses->all() === ['awaiting_confirmation'] => 'awaiting_confirmation',
            $statuses->contains('awaiting_confirmation') => 'partially_confirmed',
            $order->isDelivery() && $statuses->diff(['dispatched', 'completed'])->isEmpty() => 'dispatched',
            ! $order->isDelivery() && $statuses->diff(['ready', 'completed'])->isEmpty() => 'ready_for_pickup',
            default => 'processing',
        };

        $changes = ['status' => $status];

        if (in_array($status, ['completed', 'partially_fulfilled'], true) && ! $order->completed_at) {
            $changes['completed_at'] = now();
        }

        if ($status === 'cancelled' && ! $order->cancelled_at) {
            $changes['cancelled_at'] = now();
        }

        if ($order->payment_method === 'wallet') {
            $changes['payment_status'] = match (true) {
                $order->refunded_total <= 0 => 'paid',
                $active->isEmpty() => 'refunded',
                default => 'partially_refunded',
            };
        } else {
            $changes['payment_status'] = in_array($status, ['completed', 'partially_fulfilled'], true) ? 'paid' : 'pending';
        }

        $order->update($changes);

        if ($delivery = $order->delivery) {
            if ($delivery->status !== 'delivered') {
                $stops = $delivery->stops()->get();
                $open = $stops->whereIn('status', ['pending']);

                $delivery->update(['status' => match (true) {
                    $active->isEmpty() => 'cancelled',
                    $open->isEmpty() => 'in_transit',
                    $stops->contains('status', 'collected') => 'picking_up',
                    default => 'pending',
                }]);
            }
        }
    }

    /**
     * What the order is worth now, after any pharmacy's share was dropped:
     * the remaining products, their part of the service fee, and delivery.
     */
    public function currentTotal(Order $order): float
    {
        return $order->currentTotal();
    }

    /** Runs a change on one pharmacy's share, safely against double clicks and two staff acting at once. */
    private function transition(OrderFulfillment $fulfillment, array $from, callable $change): void
    {
        DB::transaction(function () use ($fulfillment, $from, $change) {
            $locked = OrderFulfillment::whereKey($fulfillment->id)->lockForUpdate()->first();

            if (! in_array($locked->status, $from, true)) {
                throw new OrderException('This order has already moved on. Refresh the page to see where it is now.');
            }

            $change($locked);
            $this->sync($locked->order->fresh());
        });

        $fulfillment->refresh();
    }

    /** Ends a share that will not be supplied: frees the stock and returns the money. */
    private function close(OrderFulfillment $fulfillment, string $status, string $reason, ?User $staff, bool $rejected = true): void
    {
        $order = Order::whereKey($fulfillment->order_id)->lockForUpdate()->first();
        $order->load('fulfillments');
        $before = $this->currentTotal($order);

        foreach ($fulfillment->items as $item) {
            Medication::whereKey($item->medication_id)->update([
                'reserved_stock' => DB::raw('GREATEST(CAST(reserved_stock AS SIGNED) - ' . (int) $item->quantity . ', 0)'),
            ]);
        }

        $fulfillment->update([
            'status' => $status,
            'reject_reason' => Str::limit($reason, 250, ''),
            'closed_at' => now(),
            'handled_by' => $staff?->id ?? $fulfillment->handled_by,
        ]);
        $fulfillment->stop?->update(['status' => 'skipped']);

        if ($order->payment_method !== 'wallet') {
            return;
        }

        $order->load('fulfillments');
        $refund = round($before - $this->currentTotal($order), 2);

        if ($fulfillment->paid_out) {
            $owner = User::whereKey($fulfillment->pharmacy->user_id)->lockForUpdate()->first();
            $this->moveWallet($owner, $fulfillment->payout_amount, 'debit', "Order {$order->order_number} cancelled", 'REV-' . strtoupper(Str::random(12)));
            $fulfillment->update(['paid_out' => false]);
        }

        if ($refund > 0) {
            $reference = 'REF-' . strtoupper(Str::random(12));
            $customer = User::whereKey($order->user_id)->lockForUpdate()->first();
            $this->moveWallet($customer, $refund, 'credit', "Refund for order {$order->order_number}", $reference);
            $this->recordPayment($order, 'refund', 'wallet', $refund, 'paid', $reference, $rejected ? 'Pharmacy could not supply' : 'Cancelled by customer', $fulfillment->id);
            $order->increment('refunded_total', $refund);
        }
    }

    /** The units physically leave: shelf stock and the reservation both drop. */
    private function takeFromShelf(OrderFulfillment $fulfillment, User $staff, string $how): void
    {
        foreach ($fulfillment->items as $item) {
            $medication = Medication::whereKey($item->medication_id)->lockForUpdate()->first();

            if (! $medication) {
                continue;
            }

            $medication->stock_level = max(0, (int) $medication->stock_level - $item->quantity);
            $medication->reserved_stock = max(0, (int) $medication->reserved_stock - $item->quantity);
            $medication->save();

            InventoryMovement::create([
                'pharmacy_id' => $fulfillment->pharmacy_id,
                'medication_id' => $medication->id,
                'user_id' => $staff->id,
                'type' => 'online_order',
                'quantity_change' => -$item->quantity,
                'quantity_after' => $medication->stock_level,
                'reason' => "Online order {$fulfillment->order->order_number} {$how}",
                'source_type' => OrderFulfillment::class,
                'source_id' => $fulfillment->id,
            ]);
        }
    }

    private function payPharmacy(OrderFulfillment $fulfillment): void
    {
        $owner = User::whereKey($fulfillment->pharmacy->user_id)->lockForUpdate()->first();

        if (! $owner) {
            return;
        }

        $this->moveWallet($owner, $fulfillment->payout_amount, 'credit', "Online order {$fulfillment->order->order_number}", 'OUT-' . strtoupper(Str::random(12)));
        $fulfillment->update(['paid_out' => true]);
    }

    private function moveWallet(User $user, float $amount, string $direction, string $description, string $reference): void
    {
        if ($amount <= 0) {
            return;
        }

        $before = (float) $user->wallet_balance;
        $direction === 'credit' ? $user->addBalance($amount) : $user->deductBalance($amount);

        DB::table('transactions')->insert([
            'user_id' => $user->id,
            'service_id' => 'curad_order',
            'trans_id' => $reference,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => (float) $user->wallet_balance,
            'type' => $direction,
            'status' => 'success',
            'description' => Str::limit($description, 250, ''),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordPayment(Order $order, string $type, string $method, float $amount, string $status, string $reference, ?string $note = null, ?int $fulfillmentId = null): void
    {
        OrderPayment::create([
            'order_id' => $order->id,
            'order_fulfillment_id' => $fulfillmentId,
            'type' => $type,
            'method' => $method,
            'amount' => $amount,
            'status' => $status,
            'reference' => $reference,
            'note' => $note,
            'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }

    /** $audience is who it is written for: the 'customer' who ordered, or the 'pharmacy' owner supplying it. */
    private function notify(?int $userId, string $title, string $message, string $audience = 'customer'): void
    {
        if (! $userId) {
            return;
        }

        // A customer can switch order updates off under Notifications. The pharmacy always hears about its orders.
        if ($audience === 'customer') {
            $settings = (array) (User::whereKey($userId)->value('notification_settings') ?? []);

            if (($settings['system'] ?? true) === false) {
                return;
            }
        }

        Notification::create(['user_id' => $userId, 'audience' => $audience, 'type' => 'order', 'title' => $title, 'message' => $message]);
    }

    /** Six characters with no look-alikes (no 0/O or 1/I), easy to read out at a counter. */
    private function code(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
