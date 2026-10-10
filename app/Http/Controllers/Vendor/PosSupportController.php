<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\PosHeldSale;
use App\Models\PosSale;
use App\Models\User;
use App\Support\Audit;
use App\Support\ManagerApproval;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What the POS screen asks the server for while a sale is being built:
 * looking up a returning customer, and parking / resuming held sales.
 * Shared by the owner and by staff who are allowed to sell.
 */
class PosSupportController extends Controller
{
    /**
     * @return array{0: User, 1: Pharmacy}
     */
    protected function till(): array
    {
        $user = Auth::user();

        abort_unless(in_array($user->role, ['vendor', 'sales_person'], true), 403);
        abort_if($user->role === 'sales_person' && ! StaffRoles::canSell($user->staff_role), 403);

        $pharmacy = Pharmacy::resolveForUser($user);
        abort_unless($pharmacy, 403, 'No facility is linked to this account yet.');

        return [$user, $pharmacy];
    }

    /**
     * Customers matching a name or phone number, with enough history to
     * recognise them ("4 purchases, last on Oct 1").
     */
    public function customers(Request $request)
    {
        [, $pharmacy] = $this->till();

        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['customers' => []]);
        }

        $digits = Customer::normalizePhone($term);

        $customers = Customer::where('pharmacy_id', $pharmacy->id)
            ->where(function ($query) use ($term, $digits) {
                $query->where('name', 'like', "%{$term}%");
                if (strlen($digits) >= 2) {
                    $query->orWhere('phone', 'like', "%{$digits}%");
                }
            })
            ->orderBy('name')
            ->limit(8)
            ->get();

        $history = PosSale::whereIn('customer_id', $customers->pluck('id'))
            ->where('status', '!=', PosSale::STATUS_VOIDED)
            ->selectRaw('customer_id, COUNT(*) as purchases, MAX(created_at) as last_purchase')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        return response()->json([
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'purchases' => (int) ($history[$customer->id]->purchases ?? 0),
                'last_purchase' => isset($history[$customer->id]) ? \Carbon\Carbon::parse($history[$customer->id]->last_purchase)->format('M j, Y') : null,
            ]),
        ]);
    }

    /**
     * Every sale currently parked at this facility, from any till.
     */
    public function held()
    {
        [, $pharmacy] = $this->till();

        return response()->json([
            'held' => PosHeldSale::where('pharmacy_id', $pharmacy->id)->with('user')->latest()->get()->map(fn (PosHeldSale $sale) => $this->present($sale)),
        ]);
    }

    public function hold(Request $request)
    {
        [$user, $pharmacy] = $this->till();

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $sale = PosHeldSale::create([
            'pharmacy_id' => $pharmacy->id,
            'user_id' => $user->id,
            'customer_id' => $validated['customer_id'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'customer_phone' => $validated['customer_phone'] ?? null,
            // Only what is needed to show and rebuild the cart. Prices are
            // looked up again when the sale is resumed.
            'items' => array_map(fn ($item) => [
                'id' => (int) $item['id'],
                'name' => $item['name'],
                'price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
            ], $validated['items']),
            'discount_amount' => $validated['discount_amount'] ?? 0,
        ]);

        return response()->json(['held' => $this->present($sale->load('user'))]);
    }

    /**
     * Removes a held sale: it was either resumed into a till or discarded.
     */
    public function release(int $held)
    {
        [, $pharmacy] = $this->till();

        PosHeldSale::where('pharmacy_id', $pharmacy->id)->whereKey($held)->delete();

        return response()->json(['released' => true]);
    }

    /**
     * This seller's sales from today, newest first, for the "Recent" list at
     * the till. Everyone sees only their own here; supervisors can see all
     * sales on the Sales & Receipts page.
     */
    public function recent()
    {
        [$user, $pharmacy] = $this->till();

        $sales = PosSale::where('pharmacy_id', $pharmacy->id)
            ->where('staff_id', $user->id)
            ->whereDate('created_at', now()->toDateString())
            ->with('payments')
            ->withCount('items')
            ->latest('id')
            ->limit(30)
            ->get();

        return response()->json([
            'sales' => $sales->map(fn (PosSale $sale) => [
                'id' => $sale->id,
                'receipt_no' => $sale->receipt_no,
                'time' => $sale->created_at->format('g:i A'),
                'customer' => $sale->customer_name ?: 'Walk-in Customer',
                'items' => $sale->items_count,
                'total' => $sale->total_amount,
                'payment' => $sale->paymentLabel(),
                'status' => $sale->status,
                'status_label' => $sale->statusLabel(),
                'url' => route('vendor.sales.show', $sale),
            ]),
        ]);
    }

    /**
     * A copy of an earlier receipt. Every reprint is logged, so repeated
     * reprints of the same receipt stand out to the owner.
     */
    public function reprint(PosSale $sale)
    {
        [$user, $pharmacy] = $this->till();

        abort_unless((int) $sale->pharmacy_id === (int) $pharmacy->id, 404);
        abort_unless((int) $sale->staff_id === (int) $user->id || ManagerApproval::isSupervisor($user), 403, 'You can only reprint receipts for sales you made.');

        Audit::log('sale.reprinted', "Reprinted receipt {$sale->receipt_no}", $pharmacy->id, $sale);

        return response()->json(['receipt' => $sale->receiptData($pharmacy)]);
    }

    /** A held sale as the POS page shows it. */
    protected function present(PosHeldSale $sale): array
    {
        return [
            'id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'customer' => $sale->customer_name ?: 'Walk-in Customer',
            'phone' => $sale->customer_phone ?: '',
            'items' => $sale->items,
            'discount' => $sale->discount_amount,
            'saved_by' => $sale->user->full_name ?? 'Unknown',
            'saved_at' => $sale->created_at->isToday() ? $sale->created_at->format('g:i A') : $sale->created_at->format('M j, g:i A'),
        ];
    }
}
