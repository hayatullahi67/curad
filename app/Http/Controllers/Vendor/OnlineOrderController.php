<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\OrderFulfillment;
use App\Models\Pharmacy;
use App\Services\Marketplace\OrderException;
use App\Services\Marketplace\OrderService;
use App\Support\BranchScope;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Online orders, seen from the pharmacy side. A branch only ever sees its
 * own share of an order (its fulfillment), never what another business
 * supplies. The owner sees every branch of their organization together and
 * can narrow the page to one; staff see only the branch they work in.
 */
class OnlineOrderController extends Controller
{
    private const TABS = [
        'open' => 'Needs action',
        'awaiting_confirmation' => 'New',
        'ready' => 'Accepted',
        'dispatched' => 'With rider',
        'completed' => 'Completed',
        'closed' => 'Cancelled',
        'all' => 'All',
    ];

    public function index(Request $request, OrderService $service)
    {
        [$pharmacy, $canManage] = $this->context();

        if (! $pharmacy) {
            return redirect()->route('vendor.dashboard')->with('error', 'Please set up your pharmacy profile first.');
        }

        $service->expireUnanswered();

        $tab = array_key_exists((string) $request->query('status'), self::TABS) ? $request->query('status') : 'open';
        $search = trim((string) $request->query('search'));
        $scope = BranchScope::for(Auth::user(), $request);
        $mine = fn () => OrderFulfillment::whereIn('pharmacy_id', $scope->ids());

        // Orders needing action in each branch, for the numbers on the branch filter.
        $waiting = OrderFulfillment::whereIn('pharmacy_id', $scope->branches->pluck('id'))
            ->whereIn('status', ['awaiting_confirmation', 'confirmed', 'ready'])
            ->selectRaw('pharmacy_id, COUNT(*) as total')->groupBy('pharmacy_id')->pluck('total', 'pharmacy_id');

        // Counted before any filter, so the numbers stay put when the tab changes.
        $byStatus = $mine()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = [
            'open' => (int) $byStatus->only(['awaiting_confirmation', 'confirmed', 'ready'])->sum(),
            'awaiting_confirmation' => (int) ($byStatus['awaiting_confirmation'] ?? 0),
            // "Accepted" also holds the few older orders still marked as being prepared.
            'ready' => (int) $byStatus->only(['confirmed', 'ready'])->sum(),
            'dispatched' => (int) ($byStatus['dispatched'] ?? 0),
            'completed' => (int) ($byStatus['completed'] ?? 0),
            'closed' => (int) $byStatus->only(['rejected', 'cancelled'])->sum(),
            'all' => (int) $byStatus->sum(),
        ];

        $stats = [
            'new' => $counts['awaiting_confirmation'],
            'accepted' => $counts['ready'],
            'with_rider' => $counts['dispatched'],
            'completed_today' => $mine()->where('status', 'completed')->whereDate('completed_at', today())->count(),
            'sales_today' => (float) $mine()->where('status', 'completed')->whereDate('completed_at', today())->sum('subtotal'),
        ];

        $fulfillments = $mine()
            ->when($tab === 'open', fn ($query) => $query->whereIn('status', ['awaiting_confirmation', 'confirmed', 'ready']))
            ->when($tab === 'closed', fn ($query) => $query->whereIn('status', ['rejected', 'cancelled']))
            ->when($tab === 'ready', fn ($query) => $query->whereIn('status', ['confirmed', 'ready']))
            ->when(! in_array($tab, ['open', 'closed', 'all', 'ready'], true), fn ($query) => $query->where('status', $tab))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('order', fn ($order) => $order->where('order_number', 'like', $like)
                            ->orWhere('contact_phone', 'like', $like)
                            ->orWhereHas('user', fn ($user) => $user->where('full_name', 'like', $like)))
                        ->orWhereHas('items', fn ($items) => $items->where('product_name', 'like', $like));
                });
            })
            ->with(['order.user', 'order.delivery', 'items', 'pharmacy'])
            // The newest order is always at the top.
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('vendor-dashboard.orders', [
            'pharmacy' => $pharmacy,
            'scope' => $scope,
            'waiting' => $waiting,
            'fulfillments' => $fulfillments,
            'tabs' => self::TABS,
            'tab' => $tab,
            'counts' => $counts,
            'stats' => $stats,
            'search' => $search,
            'canManage' => $canManage,
            'confirmationHours' => (int) config('curad.marketplace.confirmation_hours'),
        ]);
    }

    /**
     * Accepting is one press. A delivery order then opens Logistics, where
     * the next and last step for the pharmacy is handing it to the rider. A
     * pickup order stays here, waiting for the customer and their code.
     */
    public function accept(Request $request, OrderFulfillment $fulfillment, OrderService $service)
    {
        $response = $this->act($fulfillment, fn () => $service->accept($fulfillment, Auth::user()), 'Order accepted. The customer has been told to come and collect it.');

        if (session()->has('error') || ! $fulfillment->order->isDelivery()) {
            return $response;
        }

        return redirect()->route('vendor.dispatch', array_filter(['branch' => $request->input('branch')]))
            ->with('success', "Order {$fulfillment->order->order_number} accepted. Hand it to the rider when they arrive.");
    }

    /** Opens the conversation with the customer of an order, so the pharmacy can write to them. */
    public function message(OrderFulfillment $fulfillment)
    {
        $user = Auth::user();
        abort_unless($fulfillment->pharmacy?->isOpenTo($user), 404);
        abort_unless(Conversation::pharmacySideAllows($user), 403);

        $conversation = Conversation::firstOrCreate(
            ['user_id' => $fulfillment->order->user_id, 'pharmacy_id' => $fulfillment->pharmacy_id],
            ['order_id' => $fulfillment->order_id],
        );

        // The thread is about whichever order it was opened from last.
        if ((int) $conversation->order_id !== (int) $fulfillment->order_id) {
            $conversation->update(['order_id' => $fulfillment->order_id]);
        }

        return redirect()->route('vendor.chat.show', $conversation);
    }

    public function ready(OrderFulfillment $fulfillment, OrderService $service)
    {
        return $this->act($fulfillment, fn () => $service->markReady($fulfillment, Auth::user()), $fulfillment->order->isDelivery()
            ? 'Marked as ready. Hand it to the rider from Logistics.'
            : 'Marked as ready. The customer has been told to come and collect it.');
    }

    public function reject(Request $request, OrderFulfillment $fulfillment, OrderService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:250']], ['reason.required' => 'Say why the order cannot be supplied.']);

        return $this->act($fulfillment, fn () => $service->reject($fulfillment, Auth::user(), trim($data['reason'])), 'Order rejected. The stock is free again and the customer has been told.');
    }

    public function collect(Request $request, OrderFulfillment $fulfillment, OrderService $service)
    {
        $data = $request->validate(['pickup_code' => ['required', 'string', 'max:12']], ['pickup_code.required' => 'Enter the pickup code the customer shows you.']);

        return $this->act($fulfillment, fn () => $service->collect($fulfillment, Auth::user(), $data['pickup_code']), 'Order handed over. Stock has been updated.');
    }

    private function act(OrderFulfillment $fulfillment, callable $action, string $done)
    {
        [$pharmacy, $canManage] = $this->context();

        // Another business's order, or for staff another branch's, looks like it does not exist.
        abort_unless($pharmacy && $fulfillment->pharmacy?->isOpenTo(Auth::user()), 404);
        abort_unless($canManage, 403, 'You do not have permission to manage online orders.');

        try {
            $action();
        } catch (OrderException $problem) {
            return back()->with('error', $problem->getMessage());
        }

        return back()->with('success', $done);
    }

    /**
     * The pharmacy this person works for, and whether they may act on its
     * orders (the owner, or staff whose role manages orders). Staff who can
     * only view get the page without buttons.
     *
     * @return array{0: ?Pharmacy, 1: bool}
     */
    private function context(): array
    {
        $user = Auth::user();

        if ($user->role === 'sales_person') {
            $canManage = StaffRoles::canManageOrders($user->staff_role);
            abort_unless($canManage || StaffRoles::canViewReservations($user->staff_role), 403);

            return [Pharmacy::resolveForUser($user), $canManage];
        }

        abort_unless($user->role === 'vendor', 403);

        return [Pharmacy::resolveForUser($user), true];
    }
}
