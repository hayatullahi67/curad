<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\OrderDelivery;
use App\Models\OrderFulfillment;
use App\Models\Pharmacy;
use App\Services\Marketplace\OrderException;
use App\Services\Marketplace\OrderService;
use App\Support\BranchScope;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Logistics: orders that go out by rider. Pickup orders never appear here;
 * they are handed over from the Online Orders page. The owner sees every
 * branch together and can narrow the board to one; staff see their branch.
 */
class DispatchController extends Controller
{
    public function index(Request $request)
    {
        $pharmacy = $this->pharmacy();

        if (! $pharmacy) {
            return redirect()->route('vendor.dashboard')->with('error', 'Pharmacy record not found.');
        }

        $scope = BranchScope::for(Auth::user(), $request);

        $deliveries = fn () => OrderFulfillment::whereIn('pharmacy_id', $scope->ids())
            ->whereHas('order', fn ($order) => $order->where('fulfillment_method', 'delivery'))
            ->with(['order.user', 'order.delivery.stops.pharmacy', 'items', 'pharmacy']);

        // Deliveries packed and waiting for a rider in each branch, for the numbers on the branch filter.
        $waitingByBranch = OrderFulfillment::whereIn('pharmacy_id', $scope->branches->pluck('id'))
            ->whereHas('order', fn ($order) => $order->where('fulfillment_method', 'delivery'))
            ->where('status', 'ready')
            ->selectRaw('pharmacy_id, COUNT(*) as total')->groupBy('pharmacy_id')->pluck('total', 'pharmacy_id');

        $delivered = $deliveries()->where('status', 'completed')->whereDate('completed_at', today())->latest('completed_at')->get();

        // Riders these branches used most recently, offered as one-tap choices when handing over.
        $recentRiders = OrderDelivery::whereNotNull('rider_name')
            ->whereHas('order.fulfillments', fn ($fulfillment) => $fulfillment->whereIn('pharmacy_id', $scope->branches->pluck('id')))
            ->latest('dispatched_at')->limit(40)->get(['rider_name', 'rider_phone'])
            ->unique(fn (OrderDelivery $delivery) => preg_replace('/\D/', '', (string) $delivery->rider_phone))
            ->take(4)->values();

        return view('vendor-dashboard.dispatch', [
            'pharmacy' => $pharmacy,
            'scope' => $scope,
            'waitingByBranch' => $waitingByBranch,
            'recentRiders' => $recentRiders,
            'preparing' => $deliveries()->whereIn('status', ['awaiting_confirmation', 'confirmed'])->count(),
            'waiting' => $deliveries()->where('status', 'ready')->latest('ready_at')->latest('id')->get(),
            'onTheWay' => $deliveries()->where('status', 'dispatched')->latest('dispatched_at')->latest('id')->get(),
            'delivered' => $delivered,
        ]);
    }

    public function handover(Request $request, OrderFulfillment $fulfillment, OrderService $service)
    {
        $this->own($fulfillment);

        $data = $request->validate([
            'rider_name' => ['required', 'string', 'max:120'],
            'rider_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s\-()]{7,}$/'],
        ], [
            'rider_name.required' => 'Enter the name of the rider.',
            'rider_phone.required' => 'Enter a phone number for the rider.',
            'rider_phone.regex' => 'Enter a valid phone number for the rider.',
        ]);

        try {
            $service->handToRider($fulfillment, Auth::user(), trim($data['rider_name']), trim($data['rider_phone']));
        } catch (OrderException $problem) {
            return back()->with('error', $problem->getMessage());
        }

        return back()->with('success', 'Handed to the rider. Stock has been updated.');
    }

    /** Owner, or staff whose role manages orders. */
    private function pharmacy(): ?Pharmacy
    {
        $user = Auth::user();

        if ($user->role === 'sales_person') {
            abort_unless(StaffRoles::canManageOrders($user->staff_role), 403);
        } else {
            abort_unless($user->role === 'vendor', 403);
        }

        return Pharmacy::resolveForUser($user);
    }

    private function own(OrderFulfillment $fulfillment): void
    {
        $pharmacy = $this->pharmacy();

        // Another business's order, or for staff another branch's, looks like it does not exist.
        abort_unless($pharmacy && $fulfillment->pharmacy?->isOpenTo(Auth::user()), 404);
    }
}
