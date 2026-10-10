<?php

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Models\Medication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a product's stock split into batches (PRD CUR-PROD-005).
 *
 * The rest of the system still just changes `medications.stock_level` (a
 * sale lowers it, a delivery raises it). After every such change the ledger
 * is called to make the batches agree: units leave from the batch that
 * expires first, expired units are never the ones sold, and units arriving
 * go into the batch they came with.
 *
 * From the batches it then writes back what the product row shows: total
 * on the shelf, how many of those are expired, and the next batch to sell.
 */
class StockLedger
{
    /** Batch details of the delivery being saved right now, if any. */
    private static ?array $incoming = null;

    /** A manual write-off removes expired units first; a sale never touches them first. */
    private static bool $expiredFirst = false;

    /** True while the product row being reconciled has only just been created. */
    private static bool $newRow = false;

    /** Says which batch the next increase belongs to (a delivery, a new product, a transfer arriving). */
    public static function arriving(?string $batchNumber, $expiryDate, callable $action): mixed
    {
        static::$incoming = ['batch_number' => $batchNumber ?: null, 'expiry_date' => $expiryDate ? Carbon::parse($expiryDate)->toDateString() : null];

        try {
            return $action();
        } finally {
            static::$incoming = null;
        }
    }

    /** Runs a stock count or write-off: what is removed comes out of expired batches first. */
    public static function writingOff(callable $action): mixed
    {
        static::$expiredFirst = true;

        try {
            return $action();
        } finally {
            static::$expiredFirst = false;
        }
    }

    /**
     * Called whenever a product row is saved. Brings the batches in line
     * with the new total, or applies an edit of the batch details.
     */
    public static function reconcile(Medication $medication, bool $isNew = false): void
    {
        static::$newRow = $isNew;

        $target = max(0, (int) $medication->stock_level);
        $held = (int) InventoryBatch::where('medication_id', $medication->id)->sum('quantity');

        if ($target > $held) {
            static::add($medication, $target - $held);
        } elseif ($target < $held) {
            static::take($medication, $held - $target);
        } elseif (! $isNew && ($medication->wasChanged('expiry_date') || $medication->wasChanged('batch_number'))) {
            // Same quantity, different batch details: someone corrected the
            // batch number or expiry date of the batch now on sale.
            static::current($medication)?->update([
                'batch_number' => $medication->batch_number ?: null,
                'expiry_date' => $medication->expiry_date ?: null,
            ]);
        } else {
            return;
        }

        static::summarise($medication);
    }

    /** Puts units into a batch; returns the batch used. */
    public static function add(Medication $medication, int $quantity, ?string $batchNumber = null, $expiryDate = null): ?InventoryBatch
    {
        if ($quantity <= 0) {
            return null;
        }

        if (func_num_args() <= 2) {
            if (static::$incoming) {
                ['batch_number' => $batchNumber, 'expiry_date' => $expiryDate] = static::$incoming;
            } elseif (static::$newRow || $medication->wasChanged('expiry_date') || $medication->wasChanged('batch_number')) {
                [$batchNumber, $expiryDate] = [$medication->batch_number, $medication->expiry_date];
            } else {
                // Units coming back (a void, a return, a count correction)
                // rejoin the batch most recently on the shelf.
                $latest = InventoryBatch::where('medication_id', $medication->id)->orderByDesc('id')->first();

                if ($latest) {
                    $latest->increment('quantity', $quantity);

                    return $latest;
                }

                [$batchNumber, $expiryDate] = [$medication->batch_number, $medication->expiry_date];
            }
        }

        $expiryDate = $expiryDate ? Carbon::parse($expiryDate)->toDateString() : null;
        $batchNumber = $batchNumber ?: null;

        $batch = InventoryBatch::where('medication_id', $medication->id)
            ->where(fn ($query) => $batchNumber === null ? $query->whereNull('batch_number') : $query->where('batch_number', $batchNumber))
            ->where(fn ($query) => $expiryDate === null ? $query->whereNull('expiry_date') : $query->whereDate('expiry_date', $expiryDate))
            ->first();

        if ($batch) {
            $batch->increment('quantity', $quantity);

            return $batch;
        }

        return InventoryBatch::create([
            'pharmacy_id' => $medication->pharmacy_id,
            'medication_id' => $medication->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'quantity' => $quantity,
            'received_at' => today(),
        ]);
    }

    /**
     * Takes units out, oldest expiry first. Returns what came from where,
     * so a transfer can rebuild the same batches at the other branch.
     *
     * @return array<int, array{batch_number: ?string, expiry_date: ?string, quantity: int}>
     */
    public static function take(Medication $medication, int $quantity): array
    {
        $taken = [];
        $today = today()->toDateString();

        $batches = InventoryBatch::where('medication_id', $medication->id)->where('quantity', '>', 0)->lockForUpdate()->get()
            ->sortBy(function (InventoryBatch $batch) use ($today) {
                $expiry = $batch->expiry_date?->toDateString();
                $expired = $expiry !== null && $expiry < $today;

                // Good stock leaves soonest-to-expire first, undated stock
                // after it. Expired stock goes last, unless this is a write-off.
                return [$expired === static::$expiredFirst ? 0 : 1, $expiry ?? '9999-12-31', $batch->id];
            });

        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }

            $part = min($quantity, $batch->quantity);
            $batch->decrement('quantity', $part);
            $quantity -= $part;
            $taken[] = ['batch_number' => $batch->batch_number, 'expiry_date' => $batch->expiry_date?->toDateString(), 'quantity' => $part];
        }

        return $taken;
    }

    /** The batch that sells next: not expired, soonest expiry. */
    public static function current(Medication $medication): ?InventoryBatch
    {
        $batches = InventoryBatch::where('medication_id', $medication->id)->where('quantity', '>', 0)->get();
        $good = $batches->reject->isExpired();

        return ($good->isNotEmpty() ? $good : $batches)
            ->sortBy(fn (InventoryBatch $batch) => [$batch->expiry_date?->toDateString() ?? '9999-12-31', $batch->id])
            ->first();
    }

    /** Writes the batch picture back onto the product row. */
    public static function summarise(Medication $medication): void
    {
        $batches = InventoryBatch::where('medication_id', $medication->id)->where('quantity', '>', 0)->get();
        $current = static::current($medication);

        $summary = [
            'stock_level' => (int) $batches->sum('quantity'),
            'expired_stock' => (int) $batches->filter->isExpired()->sum('quantity'),
        ];

        // With nothing left the last known batch details stay, for reference.
        if ($current) {
            $summary['batch_number'] = $current->batch_number;
            $summary['expiry_date'] = $current->expiry_date?->toDateString();
        }

        DB::table('medications')->where('id', $medication->id)->update($summary);

        // Empty batches of a product are only noise once a newer one exists.
        InventoryBatch::where('medication_id', $medication->id)->where('quantity', 0)
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $medication->setRawAttributes(array_merge($medication->getAttributes(), $summary), true);
    }

    /**
     * Batches expire as the days pass, without anyone saving anything. Once
     * a day the products holding a batch that has now expired are refreshed.
     */
    public static function refreshExpired(): void
    {
        if (! Cache::add('stock-ledger:expiry-check:' . today()->toDateString(), true, now()->addDay())) {
            return;
        }

        $ids = InventoryBatch::where('quantity', '>', 0)->whereDate('expiry_date', '<', today())->distinct()->pluck('medication_id')
            ->merge(Medication::where('expired_stock', '>', 0)->pluck('id'))
            ->unique();

        Medication::whereIn('id', $ids)->get()->each(fn (Medication $medication) => static::summarise($medication));
    }
}
