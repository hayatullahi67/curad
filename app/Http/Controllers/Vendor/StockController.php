<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\StockReceipt;
use App\Models\StockReceiptItem;
use App\Models\Supplier;
use App\Services\Inventory\StockLedger;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Receiving deliveries into stock, and the history of every stock change.
 */
class StockController extends Controller
{
    protected function pharmacy(): Pharmacy
    {
        $pharmacy = Pharmacy::current();
        abort_unless($pharmacy, 403, 'Please set up your facility profile first.');

        return $pharmacy;
    }

    public function receiveForm(Request $request)
    {
        $pharmacy = $this->pharmacy();

        $products = Medication::where('pharmacy_id', $pharmacy->id)
            ->orderBy('name')
            ->get(['id', 'name', 'dosage', 'category', 'sku', 'barcode', 'stock_level', 'low_stock_threshold', 'cost_price', 'price', 'batch_number', 'expiry_date', 'is_listed', 'reserved_stock', 'expired_stock']);

        $suppliers = Supplier::where('pharmacy_id', $pharmacy->id)->orderBy('name')->get(['id', 'name', 'phone']);

        // What each supplier brought last time, for the "Repeat last
        // delivery" shortcut. Keyed by lower-cased supplier name because
        // that is what the page has to match against as the user types.
        $lastDeliveries = StockReceipt::where('pharmacy_id', $pharmacy->id)
            ->whereNotNull('supplier_id')
            ->with(['supplier', 'items'])
            ->latest()
            ->take(100)
            ->get()
            ->filter(fn ($receipt) => $receipt->supplier)
            ->unique('supplier_id')
            ->mapWithKeys(fn ($receipt) => [
                mb_strtolower($receipt->supplier->name) => [
                    'supplier' => $receipt->supplier->name,
                    'date' => $receipt->received_at->format('M j, Y'),
                    'items' => $receipt->items->map(fn ($item) => [
                        'medication_id' => $item->medication_id,
                        'quantity' => $item->quantity,
                        'unit_cost' => $item->unit_cost,
                    ])->values(),
                ],
            ]);

        return view('vendor-dashboard.stock-receive', compact('pharmacy', 'products', 'suppliers', 'lastDeliveries') + $this->history($request, $pharmacy) + $this->batchesAndTransfers($pharmacy));
    }

    /**
     * "Add new stock" on the Stock page: record a product the pharmacy has
     * never stocked before, with the facts known when it arrives (what it
     * is, its codes, what it cost). It starts at zero units — the delivery
     * being recorded puts the first units in — and is not for sale until it
     * is listed in Inventory with a selling price.
     */
    public function quickProduct(Request $request)
    {
        $pharmacy = $this->pharmacy();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('medications', 'sku')->where('pharmacy_id', $pharmacy->id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('medications', 'barcode')->where('pharmacy_id', $pharmacy->id)],
            'low_stock_threshold' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Enter the product name.',
            'category.required' => 'Choose or type a category.',
            'sku.unique' => 'Another product already uses this SKU. Leave it blank and one will be made for you.',
            'barcode.unique' => 'Another product in your stock already has this barcode.',
        ]);

        $name = trim($validated['name']);
        $dosage = trim((string) ($validated['dosage'] ?? '')) ?: null;

        $duplicate = Medication::where('pharmacy_id', $pharmacy->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($dosage, fn ($q) => $q->whereRaw('LOWER(dosage) = ?', [mb_strtolower($dosage)]))
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => "'{$name}' is already in your stock. Pick it from the list instead."], 422);
        }

        $medication = Medication::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => $name,
            'category' => trim($validated['category']),
            'dosage' => $dosage,
            'cost_price' => $validated['cost_price'] ?? null,
            'sku' => $validated['sku'] ?? null, // blank is filled in by the model
            'barcode' => $validated['barcode'] ?? null,
            'stock_level' => 0,
            'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
            // No selling price yet: that is set when it is listed in Inventory.
            'price' => 0,
            'is_listed' => false,
            'is_marketplace_available' => false,
        ]);

        Audit::log('product.created', "Added {$medication->name} to stock (SKU {$medication->sku})", $pharmacy->id, $medication);

        return response()->json(['product' => $this->pickerProduct($medication)]);
    }

    /**
     * A product in the shape the Stock page's picker works with.
     */
    public static function pickerProduct(Medication $medication): array
    {
        return [
            'id' => $medication->id,
            'name' => $medication->name,
            'dosage' => $medication->dosage,
            'category' => $medication->category ?: 'Uncategorised',
            'sku' => $medication->sku,
            'barcode' => $medication->barcode,
            'stock' => (int) $medication->stock_level,
            'threshold' => (int) $medication->low_stock_threshold,
            'cost' => $medication->cost_price,
            'listed' => (bool) $medication->is_listed,
        ];
    }
    public function receiveStore(Request $request)
    {
        $pharmacy = $this->pharmacy();

        $validated = $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'supplier_phone' => 'nullable|string|max:30',
            'invoice_no' => 'nullable|string|max:100',
            'received_at' => 'required|date|before_or_equal:today',
            'note' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.medication_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:100',
            'items.*.expiry_date' => 'nullable|date',
        ], [
            'items.required' => 'Add at least one product to the delivery.',
            'items.*.quantity.min' => 'Every product needs a quantity of at least 1.',
            'received_at.before_or_equal' => 'The delivery date cannot be in the future.',
        ]);

        $user = Auth::user();

        try {
            $receipt = DB::transaction(function () use ($validated, $pharmacy, $user) {
                $supplier = null;
                if (trim((string) ($validated['supplier_name'] ?? '')) !== '') {
                    $supplier = Supplier::firstOrCreate(
                        ['pharmacy_id' => $pharmacy->id, 'name' => trim($validated['supplier_name'])],
                        ['phone' => $validated['supplier_phone'] ?? null]
                    );
                }

                $receipt = StockReceipt::create([
                    'pharmacy_id' => $pharmacy->id,
                    'supplier_id' => $supplier?->id,
                    'user_id' => $user->id,
                    'reference_no' => $this->nextReference($pharmacy->id),
                    'invoice_no' => $validated['invoice_no'] ?? null,
                    'received_at' => $validated['received_at'],
                    'note' => $validated['note'] ?? null,
                ]);

                $totalCost = 0.0;

                foreach ($validated['items'] as $line) {
                    $medication = Medication::where('pharmacy_id', $pharmacy->id)
                        ->whereKey($line['medication_id'])
                        ->lockForUpdate()
                        ->first();

                    if (! $medication) {
                        throw new \DomainException('One of the products is no longer in your inventory. Refresh the page and try again.');
                    }

                    $quantity = (int) $line['quantity'];
                    $unitCost = isset($line['unit_cost']) && $line['unit_cost'] !== '' ? (float) $line['unit_cost'] : null;

                    StockReceiptItem::create([
                        'stock_receipt_id' => $receipt->id,
                        'medication_id' => $medication->id,
                        'product_name' => $medication->name,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'batch_number' => $line['batch_number'] ?? null,
                        'expiry_date' => $line['expiry_date'] ?? null,
                    ]);

                    // The newest delivery describes what is now on the
                    // shelf, so its cost, batch and expiry replace the old
                    // ones — but only where the form actually gave a value.
                    $medication->stock_level += $quantity;
                    if ($unitCost !== null) $medication->cost_price = $unitCost;
                    if (! empty($line['batch_number'])) $medication->batch_number = $line['batch_number'];
                    if (! empty($line['expiry_date'])) $medication->expiry_date = $line['expiry_date'];
                    StockLedger::arriving($line['batch_number'] ?? null, $line['expiry_date'] ?? null, fn () => $medication->save());

                    InventoryMovement::create([
                        'pharmacy_id' => $pharmacy->id,
                        'medication_id' => $medication->id,
                        'user_id' => $user->id,
                        'type' => 'received',
                        'quantity_change' => $quantity,
                        'quantity_after' => $medication->stock_level,
                        'reason' => 'Delivery ' . $receipt->reference_no . ($supplier ? " from {$supplier->name}" : ''),
                        'source_type' => StockReceipt::class,
                        'source_id' => $receipt->id,
                    ]);

                    $totalCost += ($unitCost ?? 0) * $quantity;
                }

                $receipt->update(['total_cost' => round($totalCost, 2)]);

                return $receipt->load('items', 'supplier');
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $units = $receipt->items->sum('quantity');

        Audit::log(
            'stock.received',
            "Received {$units} unit(s) across {$receipt->items->count()} product(s)" . ($receipt->supplier ? " from {$receipt->supplier->name}" : '') . " — {$receipt->reference_no}",
            $pharmacy->id,
            $receipt
        );

        return redirect()->route('vendor.stock.receive')
            ->with('success', "Delivery {$receipt->reference_no} saved. {$units} unit(s) were added to stock.");
    }

    /**
     * Data for the Batches and Transfers tabs: every batch on the shelf with
     * its expiry date, and stock moving between this branch and the others.
     */
    protected function batchesAndTransfers(Pharmacy $pharmacy): array
    {
        $batches = \App\Models\InventoryBatch::where('pharmacy_id', $pharmacy->id)->where('quantity', '>', 0)
            ->with('medication:id,name,dosage,sku,cost_price')
            ->get()
            // Expired first, then soonest to expire; undated batches last.
            ->sortBy(fn ($batch) => [$batch->expiry_date?->toDateString() ?? '9999-12-31', $batch->medication?->name])
            ->values();

        $transfers = \App\Models\StockTransfer::where(fn ($query) => $query->where('from_pharmacy_id', $pharmacy->id)->orWhere('to_pharmacy_id', $pharmacy->id))
            ->with(['items', 'from:id,name', 'to:id,name', 'sender:id,full_name', 'closer:id,full_name'])
            ->orderByRaw("status = 'in_transit' DESC")
            ->latest('id')
            ->limit(40)
            ->get();

        return [
            'batches' => $batches,
            'batchCounts' => [
                'expired' => $batches->filter(fn ($batch) => $batch->expiryState() === 'expired')->sum('quantity'),
                'soon' => $batches->filter(fn ($batch) => $batch->expiryState() === 'soon')->sum('quantity'),
                'all' => $batches->count(),
            ],
            'transfers' => $transfers,
            'incomingTransfers' => $transfers->where('status', 'in_transit')->where('to_pharmacy_id', $pharmacy->id)->count(),
            'otherBranches' => Pharmacy::where('organization_id', $pharmacy->organization_id)->where('id', '!=', $pharmacy->id)->orderByDesc('is_main')->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function nextReference(int $pharmacyId): string
    {
        $prefix = 'GRN-' . $pharmacyId . '-' . now()->format('ymd') . '-';
        $last = StockReceipt::where('reference_no', 'like', $prefix . '%')->orderByDesc('id')->value('reference_no');

        return $prefix . str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Data for the Stock History tab of the Receive Stock page: every
     * movement in and out, newest first, narrowed by the tab's filters.
     */
    protected function history(Request $request, Pharmacy $pharmacy): array
    {
        $filters = [
            'product' => $request->query('product'),
            'type' => $request->query('type'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];

        $query = InventoryMovement::where('pharmacy_id', $pharmacy->id);

        if ($filters['product']) {
            $query->where('medication_id', (int) $filters['product']);
        }
        if (array_key_exists((string) $filters['type'], InventoryMovement::TYPES)) {
            $query->where('type', $filters['type']);
        }
        if ($filters['from'] && strtotime($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] && strtotime($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $totals = (clone $query)->selectRaw(
            'COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) as units_in,
             COALESCE(SUM(CASE WHEN quantity_change < 0 THEN -quantity_change ELSE 0 END), 0) as units_out,
             COUNT(*) as movement_count'
        )->first();

        // Page links must reopen this tab, not the delivery form.
        $movements = $query->with(['product', 'user'])->latest()->latest('id')->paginate(25)
            ->appends(array_filter($filters, fn ($value) => $value !== null && $value !== '') + ['tab' => 'history']);

        return [
            'movements' => $movements,
            'historyTotals' => $totals,
            'historyFilters' => $filters,
            'historyHasFilters' => collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty(),
        ];
    }
}
