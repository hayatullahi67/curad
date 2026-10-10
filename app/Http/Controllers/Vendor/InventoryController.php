<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\Medication;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (!$pharmacy) {
            return redirect()->route('vendor.dashboard')->with('error', 'Please set up your pharmacy profile first.');
        }

        // One set of product records serves both screens: everything is
        // "stock", and Inventory Listing shows the part of it that has been
        // listed for sale. The rest is offered in the Add Item dropdown.
        $stock = Medication::where('pharmacy_id', $pharmacy->id)->latest()->get();
        // Newest listing first, so what was just put up for sale is on top
        // (the id breaks ties between products listed in the same second).
        $medications = $stock->where('is_listed', true)->sortByDesc(fn ($item) => [($item->listed_at ?? $item->created_at)?->getTimestamp() ?? 0, $item->id])->values();

        return view('vendor-dashboard.inventory', compact('medications', 'stock', 'pharmacy'));
    }

    /**
     * Rules for the product form. SKU and barcode must be unique within the
     * pharmacy, otherwise a scan at the till could match two products.
     */
    protected function productRules(int $pharmacyId, ?int $ignoreId = null, bool $photoRequired = true): array
    {
        $uniqueCode = fn (string $column) => Rule::unique('medications', $column)->where('pharmacy_id', $pharmacyId)->ignore($ignoreId);

        return [
            'name' => 'required|string|max:255',
            'sku' => ['nullable', 'string', 'max:50', $uniqueCode('sku')],
            'barcode' => ['nullable', 'string', 'max:100', $uniqueCode('barcode')],
            'category' => 'required|string',
            'dosage' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_level' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'expiry_date' => 'nullable|date',
            'batch_number' => 'nullable|string|max:100',
            'is_marketplace_available' => 'boolean',
            'description' => 'nullable|string',
            // A product cannot go on sale without a photo. One that already
            // has a photo keeps it unless a new one is uploaded.
            'photo' => [$photoRequired ? 'required' : 'nullable', 'image', 'max:2048'],
        ];
    }

    protected function productMessages(): array
    {
        return [
            'photo.required' => 'Add a photo of the product. Every product needs one.',
            'photo.image' => 'The photo must be an image file (JPG, PNG or WebP).',
            'photo.max' => 'The photo is too large. Use one that is 2MB or smaller.',
            'photo.uploaded' => 'The photo could not be uploaded. Use one that is 2MB or smaller.',
            'sku.unique' => 'Another product already uses this SKU.',
            'barcode.unique' => 'Another product already has this barcode.',
        ];
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (!$pharmacy) {
            return back()->with('error', 'Pharmacy record not found.');
        }

        $request->validate($this->productRules($pharmacy->id), $this->productMessages());

        $data = [
            'pharmacy_id' => $pharmacy->id,
            'name' => $request->name,
            'sku' => $request->input('sku'),
            'barcode' => $request->input('barcode'),
            'category' => $request->category,
            'dosage' => $request->dosage,
            'price' => $request->price,
            'cost_price' => $request->input('cost_price'),
            'stock_level' => $request->stock_level,
            'batch_number' => $request->input('batch_number'),
            'low_stock_threshold' => $request->low_stock_threshold ?? 10,
            'expiry_date' => $request->expiry_date,
            'is_marketplace_available' => $request->boolean('is_marketplace_available', true),
            'description' => $request->description,
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('medications', 'public');
        }

        $medication = Medication::create($data);
        if ($medication->stock_level > 0) {
            InventoryMovement::create([
                'pharmacy_id' => $pharmacy->id, 'medication_id' => $medication->id,
                'user_id' => Auth::id(), 'type' => 'opening_stock',
                'quantity_change' => $medication->stock_level, 'quantity_after' => $medication->stock_level,
                'reason' => 'Opening stock when product was created',
            ]);
        }

        Audit::log('product.created', "Added product {$medication->name} at ₦" . number_format($medication->price, 2), $pharmacy->id, $medication);

        return redirect()->back()->with('success', "{$medication->name} was added to your inventory.");
    }

    public function update(Request $request, $id)
    {
        $pharmacy = Pharmacy::current();
        if (!$pharmacy) abort(403);

        $medication = Medication::where('pharmacy_id', $pharmacy->id)->where('id', $id)->firstOrFail();

        $request->validate($this->productRules($pharmacy->id, $medication->id, empty($medication->photo)), $this->productMessages());

        $wasListed = (bool) $medication->is_listed;
        
        $data = [
            'name' => $request->name,
            'sku' => $request->input('sku', $medication->sku),
            'barcode' => $request->input('barcode', $medication->barcode),
            'category' => $request->category,
            'dosage' => $request->dosage,
            'price' => $request->price,
            'cost_price' => $request->input('cost_price', $medication->cost_price),
            'stock_level' => $request->stock_level,
            'batch_number' => $request->input('batch_number', $medication->batch_number),
            'low_stock_threshold' => $request->low_stock_threshold ?? 10,
            'expiry_date' => $request->expiry_date,
            'is_marketplace_available' => $request->has('is_marketplace_available')
                ? $request->boolean('is_marketplace_available')
                : $medication->is_marketplace_available,
            'description' => $request->description,
            // Saving this form with a selling price is what lists a product
            // that so far only existed as received stock.
            'is_listed' => true,
        ];

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('medications', 'public');
        }

        $previousStock = (int) $medication->stock_level;
        $previousPrice = (float) $medication->price;
        $medication->update($data);

        if (! $wasListed) {
            Audit::log('product.listed', "Listed {$medication->name} in inventory at ₦" . number_format($medication->price, 2), $pharmacy->id, $medication);
        } else {
            Audit::log(
                'product.updated',
                "Edited product {$medication->name}" . ($previousPrice !== (float) $medication->price
                    ? ' — price ₦' . number_format($previousPrice, 2) . ' → ₦' . number_format($medication->price, 2)
                    : ''),
                $pharmacy->id,
                $medication
            );
        }

        if ($previousStock !== (int) $medication->stock_level) {
            InventoryMovement::create([
                'pharmacy_id' => $pharmacy->id, 'medication_id' => $medication->id,
                'user_id' => Auth::id(), 'type' => 'adjustment',
                'quantity_change' => $medication->stock_level - $previousStock,
                'quantity_after' => $medication->stock_level, 'reason' => 'Stock corrected while editing product details',
            ]);

            Audit::log(
                'stock.adjusted',
                "Changed {$medication->name} stock from {$previousStock} to {$medication->stock_level} while editing the product",
                $pharmacy->id,
                $medication
            );
        }

        if ($medication->stock_level <= $medication->low_stock_threshold) {
            Notification::create([
                'user_id' => $medication->pharmacy?->user_id ?? Auth::id(),
                'audience' => 'pharmacy',
                'type' => 'stock',
                'title' => 'Low Stock Alert',
                'message' => "Stock for {$medication->name} is at {$medication->stock_level} (Threshold: {$medication->low_stock_threshold}). Please restock.",
            ]);
        }

        return redirect()->back()->with('success', $wasListed ? "{$medication->name} was saved." : "{$medication->name} is now listed for sale at ₦" . number_format($medication->price, 2) . '.');
    }

    public function adjustStock(Request $request, $id)
    {
        $request->validate([
            'adjustment' => 'required|integer|min:1',
            'type' => 'required|in:add,subtract',
            'reason' => 'nullable|string|max:255',
        ]);

        $pharmacy = Pharmacy::current();
        if (!$pharmacy) abort(403);

        $medication = Medication::where('pharmacy_id', $pharmacy->id)->where('id', $id)->firstOrFail();
        $stockBefore = (int) $medication->stock_level;

        if ($request->type === 'add') {
            $medication->increment('stock_level', $request->adjustment);
            $message = "Stock increased by {$request->adjustment} units.";
        } else {
            $currentStock = $medication->stock_level;
            $newStock = max(0, $currentStock - $request->adjustment);
            \App\Services\Inventory\StockLedger::writingOff(fn () => $medication->update(['stock_level' => $newStock]));
            $message = "Stock decreased by {$request->adjustment} units.";

            if ($newStock <= $medication->low_stock_threshold) {
                Notification::create([
                    'user_id' => $medication->pharmacy?->user_id ?? Auth::id(),
                    'audience' => 'pharmacy',
                    'type' => 'stock',
                    'title' => 'Critical Stock Level',
                    'message' => "Inventory for {$medication->name} dropped to {$newStock}. Please restock immediately.",
                ]);
            }
        }

        $medication->refresh();
        // Removing more than is on the shelf stops at zero, so record what
        // actually changed rather than what was typed.
        $change = (int) $medication->stock_level - $stockBefore;
        $reason = $request->reason ?: 'Manual stock adjustment';

        InventoryMovement::create([
            'pharmacy_id' => $pharmacy->id, 'medication_id' => $medication->id,
            'user_id' => Auth::id(), 'type' => 'adjustment',
            'quantity_change' => $change,
            'quantity_after' => $medication->stock_level, 'reason' => $reason,
        ]);

        Audit::log(
            'stock.adjusted',
            "Adjusted {$medication->name} stock by " . ($change >= 0 ? '+' : '') . "{$change} (now {$medication->stock_level}) — {$reason}",
            $pharmacy->id,
            $medication
        );

        return back()->with('success', $message);
    }

    public function destroy($id)
    {
        $pharmacy = Pharmacy::current();
        if (!$pharmacy) abort(403);

        $medication = Medication::where('pharmacy_id', $pharmacy->id)->where('id', $id)->firstOrFail();
        $medication->delete();

        Audit::log('product.deleted', "Deleted product {$medication->name} ({$medication->stock_level} unit(s) were in stock)", $pharmacy->id);

        return redirect()->back()->with('success', "{$medication->name} was deleted.");
    }
}
