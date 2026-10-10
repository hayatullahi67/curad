<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\PosPayment;
use App\Models\PosRefund;
use App\Models\PosRefundItem;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\User;
use App\Support\Audit;
use App\Support\ManagerApproval;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Sales & Receipts: every completed POS sale, with reprint, void and refund.
 *
 * Shared by the owner and sales staff. The owner and supervisors (see
 * StaffRoles::canApproveVoids) see every sale in the facility; other staff
 * only see the sales they rang up themselves.
 */
class SalesController extends Controller
{
    /**
     * @return array{0: User, 1: Pharmacy, 2: bool} user, their facility, and
     *                                              whether they see all of its sales
     */
    protected function context(): array
    {
        $user = Auth::user();

        abort_unless(in_array($user->role, ['vendor', 'sales_person'], true), 403);

        if ($user->role === 'sales_person') {
            abort_unless(StaffRoles::canSell($user->staff_role) || StaffRoles::can($user->staff_role, 'all_sales'), 403, StaffRoles::label($user->staff_role) . ' accounts do not have access to sales.');
        }

        $pharmacy = Pharmacy::resolveForUser($user);
        abort_unless($pharmacy, 403, 'No facility is linked to this account yet.');

        // Supervisors and roles such as Finance see every sale in the branch;
        // only supervisors may void or refund without someone approving.
        return [$user, $pharmacy, $this->isSupervisor($user) || StaffRoles::can($user->staff_role, 'all_sales')];
    }

    protected function isSupervisor(User $user): bool
    {
        return ManagerApproval::isSupervisor($user);
    }

    /**
     * Sale must belong to this facility — and to this staff member, unless
     * they are a supervisor.
     */
    protected function authorizeSale(PosSale $sale, User $user, Pharmacy $pharmacy, bool $seesAll): void
    {
        abort_unless((int) $sale->pharmacy_id === (int) $pharmacy->id, 404);
        abort_unless($seesAll || (int) $sale->staff_id === (int) $user->id, 403, 'You can only open sales you processed yourself.');
    }

    public function index(Request $request)
    {
        [$user, $pharmacy, $seesAll] = $this->context();

        $filters = [
            'q' => trim((string) $request->query('q')),
            'status' => $request->query('status'),
            'staff' => $request->query('staff'),
            'method' => $request->query('method'),
            'customer' => $request->query('customer'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $query = PosSale::where('pharmacy_id', $pharmacy->id);

        if (! $seesAll) {
            $query->where('staff_id', $user->id);
        } elseif ($filters['staff']) {
            $query->where('staff_id', (int) $filters['staff']);
        }

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        // One customer's purchase history.
        $customer = $filters['customer'] ? Customer::where('pharmacy_id', $pharmacy->id)->find((int) $filters['customer']) : null;
        if ($filters['customer']) {
            $query->where('customer_id', (int) $filters['customer']);
        }

        if (array_key_exists((string) $filters['method'], PosPayment::METHODS)) {
            $query->whereHas('payments', fn ($q) => $q->where('method', $filters['method']));
        }

        if ($filters['from'] && strtotime($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] && strtotime($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        // How many sales each status tab holds under the other filters.
        $statusCounts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        // The number cards: worked out before the status tab is applied, so
        // switching tabs only changes the list, never the totals.
        $summary = (clone $query)->selectRaw(
            "COUNT(*) as sale_count,
             COALESCE(SUM(CASE WHEN status != 'voided' THEN total_amount - refunded_amount ELSE 0 END), 0) as net_total,
             COALESCE(SUM(CASE WHEN status != 'voided' THEN refunded_amount ELSE 0 END), 0) as refunded_total,
             COALESCE(SUM(CASE WHEN status = 'voided' THEN 1 ELSE 0 END), 0) as voided_count"
        )->first();

        if (array_key_exists((string) $filters['status'], PosSale::STATUS_LABELS)) {
            $query->where('status', $filters['status']);
        }

        $sales = $query->with(['staff', 'payments'])->withCount('items')->latest()->latest('id')->paginate(20)->withQueryString();

        $staffOptions = $seesAll
            ? User::whereIn('id', PosSale::where('pharmacy_id', $pharmacy->id)->distinct()->pluck('staff_id'))->orderBy('full_name')->get(['id', 'full_name'])
            : collect();

        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();

        return view('vendor-dashboard.sales', compact('sales', 'summary', 'filters', 'staffOptions', 'seesAll', 'pharmacy', 'user', 'hasFilters', 'customer', 'statusCounts'));
    }

    public function show(PosSale $sale)
    {
        [$user, $pharmacy, $seesAll] = $this->context();
        $this->authorizeSale($sale, $user, $pharmacy, $seesAll);

        $sale->load(['items', 'payments', 'staff', 'customer', 'discountAuthorizedBy', 'voidedBy', 'voidAuthorizedBy', 'refunds.items', 'refunds.user', 'refunds.authorizedBy']);

        // Staff who cannot void/refund on their own need a supervisor to
        // type their login into the form.
        $needsApproval = ! $this->isSupervisor($user);

        return view('vendor-dashboard.sale-show', compact('sale', 'pharmacy', 'user', 'needsApproval'));
    }

    /**
     * Cancel a whole sale made by mistake today: every item goes back on
     * the shelf and the sale stops counting towards takings.
     */
    public function void(Request $request, PosSale $sale)
    {
        [$user, $pharmacy, $seesAll] = $this->context();
        $this->authorizeSale($sale, $user, $pharmacy, $seesAll);

        $request->validate([
            'reason' => 'required|string|min:3|max:255',
        ], [
            'reason.required' => 'Please say why this sale is being voided.',
        ]);

        $approver = $this->resolveApprover($request, $user, $pharmacy);

        try {
            DB::transaction(function () use ($sale, $user, $approver, $pharmacy, $request) {
                $sale = PosSale::whereKey($sale->id)->lockForUpdate()->first();

                if (! $sale->canBeVoided()) {
                    throw new \DomainException(
                        $sale->isVoided()
                            ? 'This sale has already been voided.'
                            : 'Only a sale made today, with no refund on it, can be voided. Use Refund instead.'
                    );
                }

                foreach ($sale->items as $item) {
                    $this->returnToShelf($pharmacy, $user, $item->medication_id, $item->returnableQuantity(), 'void', "Void of receipt {$sale->receipt_no}", $sale);
                }

                $sale->update([
                    'status' => PosSale::STATUS_VOIDED,
                    'voided_at' => now(),
                    'voided_by' => $user->id,
                    'void_authorized_by' => $approver->id,
                    'void_reason' => $request->reason,
                ]);
                $sale->payments()->update(['status' => 'voided']);
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::log(
            'sale.voided',
            "Voided sale {$sale->receipt_no} (₦" . number_format($sale->total_amount, 2) . ") — {$request->reason}",
            $pharmacy->id,
            $sale,
            ['approved_by' => $approver->full_name]
        );

        return redirect()->route('vendor.sales.show', $sale)
            ->with('success', "Sale {$sale->receipt_no} was voided. The items are back in stock.");
    }

    /**
     * Give money back for some or all items of a sale. Each returned item
     * can go back on the shelf or be written off (damaged, opened, expired).
     */
    public function refund(Request $request, PosSale $sale)
    {
        [$user, $pharmacy, $seesAll] = $this->context();
        $this->authorizeSale($sale, $user, $pharmacy, $seesAll);

        $request->validate([
            'reason' => 'required|string|min:3|max:255',
            'method' => ['required', Rule::in(array_keys(PosPayment::METHODS))],
            'quantities' => 'required|array',
            'quantities.*' => 'nullable|integer|min:0',
            'restock' => 'nullable|array',
        ], [
            'reason.required' => 'Please say why this refund is being made.',
        ]);

        $requested = collect($request->input('quantities', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0);

        if ($requested->isEmpty()) {
            return back()->with('error', 'Choose at least one item and how many are being returned.');
        }

        $approver = $this->resolveApprover($request, $user, $pharmacy);
        $restock = collect($request->input('restock', []))->keys()->map(fn ($id) => (int) $id);

        try {
            $refund = DB::transaction(function () use ($sale, $user, $approver, $pharmacy, $request, $requested, $restock) {
                $sale = PosSale::whereKey($sale->id)->lockForUpdate()->first();

                if (! $sale->canBeRefunded()) {
                    throw new \DomainException('This sale cannot be refunded — it is either voided or already fully refunded.');
                }

                $items = $sale->items->keyBy('id');
                $lines = [];
                $amount = 0.0;

                foreach ($requested as $itemId => $quantity) {
                    /** @var PosSaleItem|null $item */
                    $item = $items->get((int) $itemId);

                    if (! $item) {
                        throw new \DomainException('One of the selected items does not belong to this sale.');
                    }
                    if ($quantity > $item->returnableQuantity()) {
                        throw new \DomainException("Only {$item->returnableQuantity()} of '{$item->product_name}' can still be returned.");
                    }

                    $lineAmount = round($item->netUnitPrice() * $quantity, 2);
                    $amount += $lineAmount;
                    $lines[] = [$item, $quantity, $lineAmount];
                }

                // Never hand back more than the customer paid: rounding
                // each line separately can otherwise drift by a kobo.
                $amount = min(round($amount, 2), round($sale->total_amount - $sale->refunded_amount, 2));

                $refund = PosRefund::create([
                    'pos_sale_id' => $sale->id,
                    'pharmacy_id' => $pharmacy->id,
                    'refund_no' => 'RF-' . $sale->receipt_no . '-' . ($sale->refunds()->count() + 1),
                    'user_id' => $user->id,
                    // Cash handed back comes out of the drawer of whoever gives it.
                    'authorized_by' => $approver->id,
                    'amount' => $amount,
                    'method' => $request->input('method'),
                    'reason' => $request->reason,
                ]);

                foreach ($lines as [$item, $quantity, $lineAmount]) {
                    $backOnShelf = $restock->contains($item->id)
                        && $this->returnToShelf($pharmacy, $user, $item->medication_id, $quantity, 'return', "Return on receipt {$sale->receipt_no}", $refund);

                    PosRefundItem::create([
                        'pos_refund_id' => $refund->id,
                        'pos_sale_item_id' => $item->id,
                        'medication_id' => $item->medication_id,
                        'product_name' => $item->product_name,
                        'quantity' => $quantity,
                        'amount' => $lineAmount,
                        'restocked' => $backOnShelf,
                    ]);

                    $item->increment('returned_quantity', $quantity);
                }

                $everythingReturned = $sale->items()->whereColumn('returned_quantity', '<', 'quantity')->doesntExist();

                $sale->update([
                    'refunded_amount' => round($sale->refunded_amount + $amount, 2),
                    'status' => $everythingReturned ? PosSale::STATUS_REFUNDED : PosSale::STATUS_PARTIALLY_REFUNDED,
                ]);

                return $refund;
            });
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        Audit::log(
            'sale.refunded',
            "Refunded ₦" . number_format($refund->amount, 2) . " on sale {$sale->receipt_no} — {$request->reason}",
            $pharmacy->id,
            $sale,
            ['refund_no' => $refund->refund_no, 'approved_by' => $approver->full_name]
        );

        return redirect()->route('vendor.sales.show', $sale)
            ->with('success', 'Refund recorded. Give the customer ₦' . number_format($refund->amount, 2) . ' by ' . PosPayment::methodLabel($refund->method) . '.');
    }

    /**
     * Put returned units back into stock and write the stock movement.
     * Returns false when the product has since been deleted, in which case
     * there is nothing to add the stock to.
     */
    protected function returnToShelf(Pharmacy $pharmacy, User $user, int $medicationId, int $quantity, string $type, string $reason, $source): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $medication = Medication::where('pharmacy_id', $pharmacy->id)->whereKey($medicationId)->lockForUpdate()->first();

        if (! $medication) {
            return false;
        }

        $medication->increment('stock_level', $quantity);

        InventoryMovement::create([
            'pharmacy_id' => $pharmacy->id,
            'medication_id' => $medication->id,
            'user_id' => $user->id,
            'type' => $type,
            'quantity_change' => $quantity,
            'quantity_after' => $medication->stock_level,
            'reason' => $reason,
            'source_type' => $source::class,
            'source_id' => $source->id,
        ]);

        return true;
    }

    /**
     * Who is signing this void/refund off: the user themselves if they are
     * a supervisor, otherwise the supervisor whose login was typed in.
     */
    protected function resolveApprover(Request $request, User $user, Pharmacy $pharmacy): User
    {
        return ManagerApproval::resolve($request, $user, $pharmacy);
    }
}
