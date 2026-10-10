<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Marketplace\OrderException;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The customer's own orders: the list, one order with its tracking, and what they can do to it. */
class OrderController extends Controller
{
    private const FILTERS = [
        'active' => 'In progress',
        'done' => 'Completed',
        'cancelled' => 'Cancelled',
        'all' => 'All orders',
    ];

    public function index(Request $request, OrderService $service)
    {
        $service->expireUnanswered();

        $filter = array_key_exists((string) $request->query('show'), self::FILTERS) ? $request->query('show') : 'active';
        $mine = fn () => Order::where('user_id', Auth::id());

        $counts = [
            'active' => $mine()->whereNotIn('status', ['completed', 'partially_fulfilled', 'cancelled'])->count(),
            'done' => $mine()->whereIn('status', ['completed', 'partially_fulfilled'])->count(),
            'cancelled' => $mine()->where('status', 'cancelled')->count(),
            'all' => $mine()->count(),
        ];

        // Nothing in progress: show everything rather than an empty page.
        if (! $request->has('show') && $counts['active'] === 0 && $counts['all'] > 0) {
            $filter = 'all';
        }

        $orders = $mine()
            ->when($filter === 'active', fn ($query) => $query->whereNotIn('status', ['completed', 'partially_fulfilled', 'cancelled']))
            ->when($filter === 'done', fn ($query) => $query->whereIn('status', ['completed', 'partially_fulfilled']))
            ->when($filter === 'cancelled', fn ($query) => $query->where('status', 'cancelled'))
            ->with(['items', 'fulfillments.pharmacy', 'delivery'])
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('user-dashboard.orders', [
            'orders' => $orders,
            'filters' => self::FILTERS,
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function show(Order $order, OrderService $service)
    {
        $this->own($order);
        $order->load(['fulfillments.pharmacy', 'fulfillments.items', 'delivery.stops.pharmacy', 'payments']);

        return view('user-dashboard.order-show', [
            'order' => $order,
            'currentTotal' => $service->currentTotal($order),
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $service)
    {
        $this->own($order);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:250']]);

        try {
            $service->cancel($order, trim((string) ($data['reason'] ?? '')) ?: 'Cancelled by customer');
        } catch (OrderException $problem) {
            return back()->with('error', $problem->getMessage());
        }

        return back()->with('success', 'Your order has been cancelled.' . ($order->payment_method === 'wallet' ? ' The money is back in your wallet.' : ''));
    }

    /** The customer confirms the rider has handed over the delivery. */
    public function received(Order $order, OrderService $service)
    {
        $this->own($order);

        try {
            $service->deliver($order);
        } catch (OrderException $problem) {
            return back()->with('error', $problem->getMessage());
        }

        return back()->with('success', 'Thank you. Your order is marked as delivered.');
    }

    private function own(Order $order): void
    {
        abort_unless($order->user_id === Auth::id(), 404);
    }
}
