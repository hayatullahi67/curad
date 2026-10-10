<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\Marketplace\FulfillmentPlanner;
use App\Services\Marketplace\OrderException;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Checkout: the customer chooses pickup or delivery, sees which pharmacies
 * will supply the order and the full total, and only then pays (PRD 11, 15-18).
 */
class CheckoutController extends Controller
{
    public function show(FulfillmentPlanner $planner)
    {
        $cart = Cart::current(false);
        $wanted = $cart ? $cart->items()->pluck('quantity', 'product_key') : collect();

        if ($wanted->isEmpty()) {
            return redirect()->route('cart');
        }

        $options = $this->options();
        $plan = $planner->plan($wanted, $options['method'], $options['latitude'], $options['longitude']);
        $user = Auth::user();

        return view('checkout', [
            'plan' => $plan,
            'options' => $options,
            'names' => $cart->items()->with('medication')->get()->mapWithKeys(fn ($item) => [$item->product_key => $item->medication?->name ?? $item->product_key]),
            'walletBalance' => (float) $user->wallet_balance,
            'hasPin' => (bool) $user->transaction_pin,
            'deliveryReady' => $options['method'] !== 'delivery' || ($options['address'] && $options['phone']),
        ]);
    }

    /** Saves how the customer wants the order, then shows the plan again with the new numbers. */
    public function saveOptions(Request $request)
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['pickup', 'delivery'])],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $located = isset($data['latitude'], $data['longitude']);

        session(['checkout' => [
            'method' => $data['method'],
            'address' => trim((string) ($data['address'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'note' => trim((string) ($data['note'] ?? '')),
            'latitude' => $located ? (float) $data['latitude'] : null,
            'longitude' => $located ? (float) $data['longitude'] : null,
        ]]);

        // The delivery address becomes the customer's saved address, so the next order opens with it.
        if ($data['method'] === 'delivery' && filled($data['address'] ?? null)) {
            $user = Auth::user();
            $user->forceFill([
                'address' => trim($data['address']),
                'address_latitude' => $located ? (float) $data['latitude'] : null,
                'address_longitude' => $located ? (float) $data['longitude'] : null,
            ])->save();
        }

        return redirect()->route('checkout');
    }

    public function place(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['wallet', 'pay_on_collection'])],
            'signature' => ['required', 'string'],
            'pin' => ['nullable', 'string', 'max:10'],
        ]);

        $options = $this->options();

        if ($options['method'] === 'delivery' && (! $options['address'] || ! $options['phone'])) {
            return redirect()->route('checkout')->with('error', 'Add the delivery address and a phone number the rider can call.');
        }

        $cart = Cart::current(false);

        if (! $cart) {
            return redirect()->route('cart');
        }

        try {
            $order = $orders->place(Auth::user(), $cart, $options + $data);
        } catch (OrderException $problem) {
            return redirect()->route('checkout')->with('error', $problem->getMessage());
        }

        session()->forget('checkout');

        return redirect()->route('user.orders.show', $order)->with('success', "Order {$order->order_number} placed. The pharmacy has been notified.");
    }

    private function options(): array
    {
        $user = Auth::user();

        return array_merge([
            'method' => 'pickup',
            'address' => (string) ($user->address ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'note' => '',
            // The customer's saved address comes with its place on the map.
            'latitude' => $user->address_latitude !== null ? (float) $user->address_latitude : null,
            'longitude' => $user->address_longitude !== null ? (float) $user->address_longitude : null,
        ], session('checkout', []));
    }
}
