<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Medication;
use App\Models\InventoryMovement;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\Pharmacy;
use App\Models\StaffPasswordResetRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\ManagerApproval;
use App\Support\StaffRoles;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesStaffController extends Controller
{
    public function index()
    {
        $vendor = Auth::user();
        $pharmacy = Pharmacy::current();

        $staffMembers = $this->staffQuery($vendor, $pharmacy)
            ->latest()
            ->get();

        $salesSummary = PosSale::whereIn('staff_id', $staffMembers->pluck('id'))
            ->where('status', '!=', PosSale::STATUS_VOIDED)
            ->selectRaw('staff_id, COUNT(*) as sale_count, SUM(total_amount - refunded_amount) as total_amount')
            ->groupBy('staff_id')
            ->get()
            ->keyBy('staff_id');

        $pendingResets = StaffPasswordResetRequest::pending()
            ->whereIn('staff_id', $staffMembers->pluck('id'))
            ->get()
            ->keyBy('staff_id');

        $roleOptions = StaffRoles::ROLES;
        $branches = Pharmacy::branchesOf($vendor)->get(['id', 'name', 'is_main']);

        return view('vendor-dashboard.sales-staff', compact('staffMembers', 'pharmacy', 'salesSummary', 'pendingResets', 'roleOptions', 'branches'));
    }

    /**
     * Base query for staff belonging to this vendor, matching how they're
     * assigned in store(): either created directly by the vendor, or linked
     * to the vendor's pharmacy.
     */
    protected function staffQuery(User $vendor, ?Pharmacy $pharmacy)
    {
        return User::where('cura_role', 'sales_person')
            ->where(function ($query) use ($vendor, $pharmacy) {
                // Everyone the owner created, in whichever branch they work.
                $query->where('created_by_user_id', $vendor->id)
                    ->orWhereIn('assigned_pharmacy_id', Pharmacy::branchesOf($vendor)->pluck('id'));
            });
    }

    /**
     * Fetch a staff member, aborting if they don't belong to this vendor.
     */
    protected function findOwnedStaff(int $id): User
    {
        $vendor = Auth::user();
        $pharmacy = Pharmacy::current();

        $staff = $this->staffQuery($vendor, $pharmacy)->find($id);

        abort_if(! $staff, 404);

        return $staff;
    }

    /**
     * Resolve which pharmacy a logged-in sales-person belongs to.
     */
    protected function resolveStaffPharmacyId(User $user): ?int
    {
        if ($user->assigned_pharmacy_id) {
            return $user->assigned_pharmacy_id;
        }

        return Pharmacy::where('user_id', $user->created_by_user_id)->value('id');
    }

    protected function requireSalesPerson(User $user): void
    {
        abort_if($user->role !== 'sales_person', 403);
    }

    protected function requireCanSell(User $user): void
    {
        abort_unless(StaffRoles::canSell($user->staff_role), 403, StaffRoles::label($user->staff_role) . ' accounts do not have POS access.');
    }

    /** Moves a staff member to another branch, or changes their role. */
    public function assign(Request $request, int $id)
    {
        $vendor = Auth::user();
        $staff = $this->findOwnedStaff($id);
        $roleOptions = StaffRoles::ROLES;

        $data = $request->validate([
            'staff_role' => ['required', 'string', Rule::in(array_keys($roleOptions))],
            'branch_id' => ['required', 'integer'],
        ]);

        $branch = Pharmacy::branchesOf($vendor)->whereKey($data['branch_id'])->first();
        abort_unless($branch, 404);

        $before = StaffRoles::label($staff->staff_role);
        DB::table('users')->where('id', $staff->id)->update(['staff_role' => $data['staff_role'], 'assigned_pharmacy_id' => $branch->id, 'updated_at' => now()]);

        Audit::log('staff.assigned', "{$staff->full_name} is now {$roleOptions[$data['staff_role']]['label']} at {$branch->name} (was {$before})", $branch->id, $staff);

        return back()->with('success', "{$staff->full_name} is now {$roleOptions[$data['staff_role']]['label']} at {$branch->name}.");
    }

    public function store(Request $request)
    {
        $vendor = Auth::user();
        $pharmacy = Pharmacy::current();
        $roleOptions = StaffRoles::ROLES;

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'phone' => 'nullable|string|max:20',
            'staff_role' => ['required', 'string', Rule::in(array_keys($roleOptions))],
            'branch_id' => ['nullable', 'integer'],
        ]);

        // The branch this person will work in: one of the owner's own.
        $pharmacy = Pharmacy::branchesOf($vendor)->whereKey((int) $request->input('branch_id'))->first() ?? $pharmacy;

        $nameParts = preg_split('/\s+/', trim($request->full_name), 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? $nameParts[0];

        // Inserted via the query builder rather than User::create(): the shared
        // Valu users table has a legacy 'password' column (NOT NULL, no default,
        // unused by CURA auth — see User::getAuthPassword()) that is distinct
        // from 'password_hash', but Eloquent's password mutator intercepts the
        // 'password' key and redirects it to password_hash, so it can never be
        // mass-assigned directly.
        $staffId = DB::table('users')->insertGetId([
            'full_name' => $request->full_name,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $request->email,
            'phone' => $request->phone ?: null, // phone numbers are unique, so a missing one is left empty rather than ''
            'address' => '',
            'password_hash' => bcrypt($request->password),
            'password' => '',
            'cura_role' => 'sales_person',
            'staff_role' => $request->staff_role,
            'created_by_user_id' => $vendor->id,
            'assigned_pharmacy_id' => $pharmacy?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $staff = User::find($staffId);

        Audit::log('staff.created', "Created {$roleOptions[$request->staff_role]['label']} login for {$staff->full_name}", $pharmacy?->id, $staff);

        return back()->with(
            'success',
            "{$roleOptions[$request->staff_role]['label']} account created. Email: {$staff->email} Password: {$request->password}"
        );
    }

    public function dashboard()
    {
        $user = Auth::user();
        $this->requireSalesPerson($user);

        $branch = Pharmacy::resolveForUser($user);
        abort_unless($branch, 403, 'No branch is linked to this account yet. Ask the pharmacy owner to assign you to one.');

        // The same dashboard the owner sees, cut down to what this role may do.
        return view('vendor-dashboard.dashboard', \App\Support\BranchDashboard::for($user, $branch));
    }

    /**
     * "My Sales" now lives on the shared Sales & Receipts page, which
     * already limits a staff member to their own sales.
     */
    public function mySales()
    {
        return redirect()->route('vendor.sales');
    }

    public function pos()
    {
        $user = Auth::user();
        $pharmacy = null;
        $pharmacyId = null;

        if ($user->role === 'vendor') {
            $pharmacy = Pharmacy::current();
            $pharmacyId = $pharmacy?->id;
        } elseif ($user->role === 'sales_person') {
            if (! StaffRoles::canSell($user->staff_role)) {
                return redirect()->route('sales.staff.dashboard')
                    ->with('error', StaffRoles::label($user->staff_role) . ' accounts do not have POS access.');
            }
            $pharmacyId = $this->resolveStaffPharmacyId($user);
            $pharmacy = Pharmacy::find($pharmacyId);
        } else {
            abort(403, 'Unauthorized access to POS terminal.');
        }

        // Only what the till needs, so cost prices never reach a cashier's
        // browser. Out-of-stock products are sent too (shown greyed out), so
        // staff can tell "we are out" from "we do not sell that".
        $medications = $pharmacyId
            ? Medication::where('pharmacy_id', $pharmacyId)
                ->listed() // received stock is not for sale until it is listed with a price
                ->orderByRaw('stock_level > 0 DESC')
                ->orderBy('name', 'asc')
                ->get()
                ->map(fn (Medication $medication) => [
                    'id' => $medication->id,
                    'name' => $medication->name,
                    'dosage' => $medication->dosage,
                    'category' => $medication->category,
                    'price' => (float) $medication->price,
                    'stock_level' => $medication->availableStock(),
                    'sku' => $medication->sku,
                    'barcode' => $medication->barcode,
                    'batch_number' => $medication->batch_number,
                    'expiry_date' => $medication->expiry_date ? Carbon::parse($medication->expiry_date)->toDateString() : null,
                    'description' => $medication->description,
                    'photo_url' => $medication->photo ? Storage::disk('public')->url($medication->photo) : null,
                ])
                ->values()
            : collect();

        $categories = $pharmacyId
            ? Medication::where('pharmacy_id', $pharmacyId)
                ->listed()
                ->select('category')
                ->distinct()
                ->pluck('category')
                ->filter()
                ->values()
            : collect();

        // The owner and staff reach this screen through different URLs (the
        // owner's sits behind the vendor-only middleware), so the till must
        // post the sale back to the one that matches who is logged in.
        $saleRoute = $user->role === 'vendor' ? route('vendor.pos.sale') : route('sales.staff.pos.sale');

        $posConfig = [
            'saleUrl' => $saleRoute,
            'customersUrl' => route('vendor.pos.customers'),
            'heldUrl' => route('vendor.pos.held'),
            // Largest discount (% of the sale) this user may give alone; null = no limit.
            'discountLimit' => $user->role === 'vendor' ? null : StaffRoles::maxDiscountPercent($user->staff_role),
            // Keeps one till's unfinished cart apart from another user's on a shared computer.
            'cartKey' => 'cura_pos_cart_' . $pharmacyId . '_' . $user->id,
            'today' => now()->toDateString(),
            'recentUrl' => route('vendor.pos.recent'),
            'reprintUrl' => route('vendor.pos.reprint', ['sale' => '__SALE__']),
        ];

        return view('vendor-dashboard.sales-staff-pos', compact('medications', 'pharmacy', 'categories', 'user', 'saleRoute', 'posConfig'));
    }

    public function processSale(Request $request)
    {
        $user = Auth::user();
        $wantsJson = $request->wantsJson() || $request->ajax();

        $fail = function (string $message, int $status = 422) use ($wantsJson) {
            return $wantsJson
                ? response()->json(['success' => false, 'message' => $message], $status)
                : back()->with('error', $message);
        };

        if ($user->role === 'vendor') {
            $pharmacy = Pharmacy::current();
        } elseif ($user->role === 'sales_person') {
            $this->requireCanSell($user);
            $pharmacy = Pharmacy::find($this->resolveStaffPharmacyId($user));
        } else {
            return $wantsJson ? $fail('Unauthorized', 403) : abort(403);
        }

        if (! $pharmacy) {
            return $fail('No active pharmacy associated with this terminal.');
        }

        // The till sends a cart; the old single-product form sends one item.
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (! is_array($items) || empty($items)) {
            $items = $request->filled('medication_id')
                ? [['id' => $request->medication_id, 'quantity' => $request->input('quantity', 1)]]
                : [];
        }

        // The same product scanned twice arrives as two lines; add them up
        // so the stock check sees the full quantity being sold.
        $quantities = [];
        foreach ($items as $item) {
            $medicationId = (int) ($item['id'] ?? $item['medication_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? $item['qty'] ?? 1);

            if ($medicationId <= 0 || $quantity <= 0) {
                return $fail('One of the items in the cart is invalid. Remove it and add it again.');
            }

            $quantities[$medicationId] = ($quantities[$medicationId] ?? 0) + $quantity;
        }

        if (empty($quantities)) {
            return $fail('Cart is empty.');
        }

        $request->validate([
            'payment_method' => ['nullable', Rule::in(array_keys(PosPayment::METHODS))],
            'discount_amount' => 'nullable|numeric|min:0',
            'amount_tendered' => 'nullable|numeric|min:0',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'payment_reference' => 'nullable|string|max:100',
            'discount_reason' => 'nullable|string|max:255',
            // A split payment: up to four lines, e.g. part cash, part transfer.
            'payments' => 'nullable|array|max:4',
            'payments.*.method' => ['required_with:payments', Rule::in(array_keys(PosPayment::METHODS))],
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:100',
        ], [
            'payments.max' => 'A sale can be split across at most four payments.',
            'payments.*.amount.min' => 'Every payment line needs an amount.',
            'payments.*.amount.required_with' => 'Every payment line needs an amount.',
        ]);

        $customerName = trim((string) $request->input('customer_name')) ?: 'Walk-in Customer';
        $customerPhone = trim((string) $request->input('customer_phone'));
        $discountAmount = round((float) $request->input('discount_amount', 0), 2);

        // One payment, or several for a split payment. A single cash
        // payment may leave the amount out (or send 0): exact cash is assumed.
        $payments = $request->input('payments');
        if (! is_array($payments) || empty($payments)) {
            $method = $request->input('payment_method') ?: 'cash';
            $tendered = round((float) $request->input('amount_tendered', 0), 2);
            $payments = [[
                'method' => $method,
                'amount' => $method === 'cash' && $tendered > 0 ? $tendered : null,
                'reference' => $request->input('payment_reference'),
            ]];
        }
        $payments = array_map(fn ($line) => [
            'method' => $line['method'],
            'amount' => isset($line['amount']) && $line['amount'] !== null ? round((float) $line['amount'], 2) : null,
            'reference' => trim((string) ($line['reference'] ?? '')) ?: null,
        ], array_values($payments));

        $discount = [
            'amount' => $discountAmount,
            'reason' => trim((string) $request->input('discount_reason')) ?: null,
            // Largest share of the sale this user may discount alone; null = no limit.
            'limit' => $user->role === 'vendor' ? null : StaffRoles::maxDiscountPercent($user->staff_role),
            // Asked for only if the discount turns out to be over the limit,
            // so a normal sale never needs a manager's login.
            'approve' => fn () => ManagerApproval::resolve($request, $user, $pharmacy),
        ];

        $customer = $this->resolveCustomer($pharmacy, $request->input('customer_id'), $customerName, $customerPhone);
        if ($customer && $customerName === 'Walk-in Customer') {
            $customerName = $customer->displayName();
        }

        try {
            $sale = null;

            // Two tills can be handed the same next receipt number; the
            // unique index rejects the second and we simply take the next one.
            for ($attempt = 1; $sale === null; $attempt++) {
                try {
                    $sale = DB::transaction(fn () => $this->recordSale(
                        $pharmacy, $user, $quantities, $customer, $customerName, $customerPhone,
                        $payments, $discount
                    ));
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= 5) {
                        throw $e;
                    }
                }
            }
        } catch (\DomainException $e) {
            return $fail($e->getMessage());
        } catch (ValidationException $e) {
            // A missing or wrong manager approval: say exactly that.
            return $fail(collect($e->errors())->flatten()->first() ?: $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $fail('The sale could not be saved, so nothing was charged and stock was not changed. Please try again.', 500);
        }

        if ($sale->discount_amount > 0) {
            Audit::log(
                'sale.discounted',
                'Discount of ₦' . number_format($sale->discount_amount, 2) . ' (' . round($sale->discount_amount / max(0.01, $sale->subtotal) * 100, 1) . "%) on sale {$sale->receipt_no}"
                    . ($sale->discount_reason ? " — {$sale->discount_reason}" : ''),
                $pharmacy->id,
                $sale,
                $sale->discount_authorized_by ? ['approved_by' => $sale->discountAuthorizedBy->full_name ?? null] : []
            );
        }

        Audit::log(
            'sale.completed',
            "Sale {$sale->receipt_no} completed — ₦" . number_format($sale->total_amount, 2) . ' (' . $sale->paymentLabel() . ')',
            $pharmacy->id,
            $sale
        );

        if (! $wantsJson) {
            return back()->with('success', "Sale completed successfully. Receipt #{$sale->receipt_no} generated.");
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale completed successfully.',
            'receipt' => $sale->setRelation('staff', $user)->receiptData($pharmacy),
        ]);
    }

    /**
     * Turns the payment lines into what is stored: one row per card,
     * transfer or Valu payment, and one cash row that gives any change.
     * Only cash can be more than what is owed.
     *
     * @param  array<int, array{method: string, amount: ?float, reference: ?string}>  $payments
     * @return array<int, array{method: string, amount: float, amount_tendered: float, change_given: float, reference: ?string}>
     *
     * @throws \DomainException with a message safe to show at the till
     */
    protected function settlePayments(array $payments, float $total): array
    {
        // A single payment with no amount pays exactly what is owed.
        if (count($payments) === 1 && $payments[0]['amount'] === null) {
            $payments[0]['amount'] = $total;
        }

        $money = fn (float $amount) => '₦' . number_format($amount, 2);
        $cashGiven = 0.0;
        $otherTotal = 0.0;
        $rows = [];

        foreach ($payments as $line) {
            if ($line['amount'] === null || $line['amount'] <= 0) {
                throw new \DomainException('Every payment line needs an amount.');
            }
            if ($line['method'] === 'cash') {
                $cashGiven += $line['amount'];
                continue;
            }
            $otherTotal += $line['amount'];
            $rows[] = [
                'method' => $line['method'],
                'amount' => $line['amount'],
                'amount_tendered' => $line['amount'],
                'change_given' => 0,
                'reference' => $line['reference'],
            ];
        }

        $cashGiven = round($cashGiven, 2);
        $otherTotal = round($otherTotal, 2);

        if ($otherTotal > $total) {
            throw new \DomainException('Card, transfer and Valu payments add up to ' . $money($otherTotal) . ', more than the total of ' . $money($total) . '. Only cash can be more, because change is given in cash.');
        }

        if ($cashGiven <= 0) {
            if ($otherTotal < $total) {
                throw new \DomainException($money(round($total - $otherTotal, 2)) . ' is still to be paid.');
            }

            return $rows;
        }

        $cashOwed = round($total - $otherTotal, 2);
        if ($cashOwed <= 0) {
            throw new \DomainException('The other payments already cover the total. Remove the cash line.');
        }
        if ($cashGiven < $cashOwed) {
            throw new \DomainException(count($payments) === 1
                ? 'Cash received (' . $money($cashGiven) . ') is less than the total payable (' . $money($total) . ').'
                : 'Cash received (' . $money($cashGiven) . ') is less than the ' . $money($cashOwed) . ' left to pay in cash.');
        }

        // Cash first on the receipt, as it is the line that gives change.
        array_unshift($rows, [
            'method' => 'cash',
            'amount' => $cashOwed,
            'amount_tendered' => $cashGiven,
            'change_given' => round($cashGiven - $cashOwed, 2),
            'reference' => null,
        ]);

        return $rows;
    }

    /**
     * The customer record a sale belongs to, if any. One picked at the till
     * is used as is; otherwise a phone number is enough to find a returning
     * customer or start a record for a new one. A sale with no phone stays
     * a walk-in sale with no customer record.
     */
    protected function resolveCustomer(Pharmacy $pharmacy, $customerId, string $name, string $phone): ?Customer
    {
        if ($customerId) {
            $picked = Customer::where('pharmacy_id', $pharmacy->id)->find((int) $customerId);
            if ($picked) {
                return $picked;
            }
        }

        $phone = Customer::normalizePhone($phone);
        if (strlen($phone) < 7) {
            return null;
        }

        $name = $name !== 'Walk-in Customer' ? $name : null;
        $customer = Customer::firstOrCreate(['pharmacy_id' => $pharmacy->id, 'phone' => $phone], ['name' => $name]);

        // First time we learn the name of someone known only by phone.
        if (! $customer->name && $name) {
            $customer->update(['name' => $name]);
        }

        return $customer;
    }

    /**
     * Writes one complete sale — the sale, its lines, the payment and a
     * stock movement per product — and takes the stock off the shelf. Runs
     * inside a transaction, so a failure at any step leaves nothing behind.
     *
     * @param  array<int, int>  $quantities  medication id => quantity sold
     *
     * @throws \DomainException with a message safe to show at the till
     */
    protected function recordSale(
        Pharmacy $pharmacy,
        User $user,
        array $quantities,
        ?Customer $customer,
        string $customerName,
        string $customerPhone,
        array $payments,
        array $discount
    ): PosSale {
        $discountAmount = $discount['amount'];

        // Lock the rows so two tills cannot both sell the last unit.
        $medications = Medication::where('pharmacy_id', $pharmacy->id)
            ->whereIn('id', array_keys($quantities))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        foreach ($quantities as $medicationId => $quantity) {
            $medication = $medications->get($medicationId);

            if (! $medication || ! $medication->is_listed) {
                throw new \DomainException('A product in the cart is no longer in your inventory. Refresh the page and try again.');
            }

            // Units promised to online orders are not free to sell at the till.
            if ($medication->availableStock() < $quantity) {
                throw new \DomainException("Not enough stock for '{$medication->name}'. Available: {$medication->availableStock()}, in cart: {$quantity}." . ($medication->reserved_stock > 0 ? " ({$medication->reserved_stock} held for online orders.)" : ''));
            }

            if ($medication->expiry_date && Carbon::parse($medication->expiry_date)->endOfDay()->isPast()) {
                throw new \DomainException("'{$medication->name}' expired on " . Carbon::parse($medication->expiry_date)->format('M j, Y') . ' and cannot be sold. Remove it from the sale.');
            }

            $subtotal += $medication->price * $quantity;
        }
        $subtotal = round($subtotal, 2);

        if ($discountAmount > $subtotal) {
            throw new \DomainException('The discount cannot be more than the sale subtotal (₦' . number_format($subtotal, 2) . ').');
        }

        // A discount above the seller's own limit needs a supervisor.
        $discountApprover = null;
        if ($discountAmount > 0 && $discount['limit'] !== null && $discountAmount > round($subtotal * $discount['limit'] / 100, 2)) {
            $discountApprover = $discount['approve']();

            if (! $discount['reason']) {
                throw new \DomainException('Give a reason for a discount this large.');
            }
        }

        $total = round($subtotal - $discountAmount, 2);

        // Checked before anything is written, so a payment problem leaves no trace.
        $paymentRows = $this->settlePayments($payments, $total);

        $sale = PosSale::create([
            'pharmacy_id' => $pharmacy->id,
            'staff_id' => $user->id,
            'customer_id' => $customer?->id,
            'receipt_no' => PosSale::nextReceiptNo($pharmacy->id),
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'discount_reason' => $discountAmount > 0 ? $discount['reason'] : null,
            'discount_authorized_by' => $discountApprover?->id,
            'total_amount' => $total,
            'status' => PosSale::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        // Share the sale discount across the lines by value. The last line
        // takes whatever is left so the shares always add up exactly.
        $discountLeft = $discountAmount;
        $lastMedicationId = array_key_last($quantities);

        foreach ($quantities as $medicationId => $quantity) {
            $medication = $medications->get($medicationId);
            $lineTotal = round($medication->price * $quantity, 2);
            $lineDiscount = $medicationId === $lastMedicationId
                ? $discountLeft
                : ($subtotal > 0 ? round($lineTotal / $subtotal * $discountAmount, 2) : 0);
            $discountLeft = round($discountLeft - $lineDiscount, 2);

            $medication->decrement('stock_level', $quantity);

            PosSaleItem::create([
                'pos_sale_id' => $sale->id,
                'medication_id' => $medication->id,
                'product_name' => $medication->name,
                'sku' => $medication->sku,
                'batch_number' => $medication->batch_number,
                'quantity' => $quantity,
                'unit_price' => $medication->price,
                'unit_cost' => $medication->cost_price,
                'discount_amount' => $lineDiscount,
                'line_total' => $lineTotal,
            ]);

            InventoryMovement::create([
                'pharmacy_id' => $pharmacy->id,
                'medication_id' => $medication->id,
                'user_id' => $user->id,
                'type' => 'sale',
                'quantity_change' => -$quantity,
                'quantity_after' => $medication->stock_level,
                'reason' => "POS receipt {$sale->receipt_no}",
                'source_type' => PosSale::class,
                'source_id' => $sale->id,
            ]);
        }

        foreach ($paymentRows as $row) {
            PosPayment::create($row + ['pos_sale_id' => $sale->id, 'status' => 'paid']);
        }

        return $sale->load('items', 'payments');
    }

    /**
     * A staff member's sales now open on the shared Sales & Receipts page,
     * pre-filtered to them.
     */
    public function history(int $id)
    {
        $staff = $this->findOwnedStaff($id);

        return redirect()->route('vendor.sales', ['staff' => $staff->id]);
    }

    /**
     * Deactivate a staff account — blocks POS login but keeps the account
     * and its sales history intact.
     */
    public function destroy(int $id)
    {
        $staff = $this->findOwnedStaff($id);

        DB::table('users')->where('id', $staff->id)->update(['is_active' => 0]);

        Audit::log('staff.deactivated', "Deactivated {$staff->full_name}'s staff login", $this->resolveStaffPharmacyId($staff), $staff);

        return back()->with('success', "{$staff->full_name}'s account has been deactivated.");
    }

    /**
     * Reactivate a previously deactivated staff account.
     */
    public function reactivate(int $id)
    {
        $staff = $this->findOwnedStaff($id);

        DB::table('users')->where('id', $staff->id)->update(['is_active' => 1]);

        Audit::log('staff.reactivated', "Reactivated {$staff->full_name}'s staff login", $this->resolveStaffPharmacyId($staff), $staff);

        return back()->with('success', "{$staff->full_name}'s account has been reactivated.");
    }

    /**
     * Vendor impersonates a staff account without needing their password.
     * The vendor's own id is stashed in the session so returnToAdmin() can
     * switch back.
     */
    public function loginAs(int $id, Request $request)
    {
        $vendor = Auth::user();
        $staff = $this->findOwnedStaff($id);

        if (! $staff->is_active) {
            return back()->with('error', "{$staff->full_name}'s account is deactivated. Reactivate it first.");
        }

        // Logged before switching user, so the entry is in the owner's name.
        Audit::log('staff.login_as', "Logged in as staff member {$staff->full_name}", $this->resolveStaffPharmacyId($staff), $staff);

        Audit::quietly(fn () => Auth::login($staff));
        $request->session()->regenerate();
        session(['impersonator_id' => $vendor->id]);

        return redirect()->route('sales.staff.dashboard')->with('success', "Logged in as {$staff->full_name}.");
    }

    /**
     * Vendor sets a new password for a staff account — either ad-hoc, or to
     * fulfill a pending forgot-password request.
     */
    public function resetPassword(int $id, Request $request)
    {
        $request->validate([
            'new_password' => 'required|min:6',
        ]);

        $staff = $this->findOwnedStaff($id);

        DB::table('users')->where('id', $staff->id)->update([
            'password_hash' => bcrypt($request->new_password),
        ]);

        Audit::log('staff.password_reset', "Reset the password for {$staff->full_name}", $this->resolveStaffPharmacyId($staff), $staff);

        StaffPasswordResetRequest::pending()
            ->where('staff_id', $staff->id)
            ->update([
                'status' => 'fulfilled',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);

        return back()->with(
            'success',
            "Password reset for {$staff->full_name}. New password: {$request->new_password}"
        );
    }

    /**
     * Switch back from an impersonated staff session to the vendor account.
     */
    public function returnToAdmin(Request $request)
    {
        $vendorId = session('impersonator_id');

        if (! $vendorId) {
            abort(403);
        }

        session()->forget('impersonator_id');
        Audit::quietly(fn () => Auth::loginUsingId($vendorId));
        $request->session()->regenerate();

        return redirect()->route('vendor.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
