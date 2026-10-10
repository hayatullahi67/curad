<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Moves stock between two branches of one organization (PRD CUR-INV-006).
 * The units leave the sending branch straight away and are added to the
 * receiving branch when someone there confirms they arrived, so stock on
 * the road is never counted as sellable in either shop.
 */
class TransferService
{
    /**
     * @param  array<int, int>  $lines  product row id in the sending branch => quantity
     */
    public function send(Pharmacy $from, Pharmacy $to, array $lines, User $by, ?string $note = null): StockTransfer
    {
        if ($from->id === $to->id || $from->organization_id !== $to->organization_id) {
            throw new \DomainException('Choose another branch of your own organization to send the stock to.');
        }

        $lines = array_filter(array_map('intval', $lines), fn ($quantity) => $quantity > 0);

        if (! $lines) {
            throw new \DomainException('Add at least one product with a quantity.');
        }

        return DB::transaction(function () use ($from, $to, $lines, $by, $note) {
            $transfer = StockTransfer::create([
                'organization_id' => $from->organization_id,
                'reference' => $this->reference(),
                'from_pharmacy_id' => $from->id,
                'to_pharmacy_id' => $to->id,
                'status' => 'in_transit',
                'note' => $note ? Str::limit(trim($note), 250, '') : null,
                'sent_by' => $by->id,
                'sent_at' => now(),
            ]);

            foreach ($lines as $medicationId => $quantity) {
                $medication = Medication::where('pharmacy_id', $from->id)->whereKey($medicationId)->lockForUpdate()->first();

                if (! $medication) {
                    throw new \DomainException('One of the products is no longer in this branch. Refresh the page and try again.');
                }

                // Only good, unpromised stock can be sent: not expired, not held for an online order.
                if ($medication->availableStock() < $quantity) {
                    throw new \DomainException("Only {$medication->availableStock()} of {$medication->name} can be sent right now.");
                }

                $batches = StockLedger::take($medication, $quantity);
                StockLedger::summarise($medication);

                $transfer->items()->create([
                    'product_id' => $medication->product_id,
                    'from_medication_id' => $medication->id,
                    'product_name' => $medication->name,
                    'quantity' => $quantity,
                    'batches' => $batches,
                ]);

                $this->movement($medication, $by, 'transfer_out', -$quantity, "Sent to {$to->name} — {$transfer->reference}", $transfer);
            }

            $units = array_sum($lines);
            Audit::log('stock.transfer_sent', "Sent {$units} unit(s) of " . count($lines) . " product(s) to {$to->name} — {$transfer->reference}", $from->id, $transfer);

            return $transfer;
        });
    }

    /** The receiving branch confirms the stock arrived; it joins their shelf with its batch details. */
    public function receive(StockTransfer $transfer, User $by): void
    {
        DB::transaction(function () use ($transfer, $by) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->first();
            $this->mustBeOnTheWay($transfer);

            foreach ($transfer->items as $item) {
                $source = Medication::find($item->from_medication_id);
                $target = $this->shelfRow($transfer->to_pharmacy_id, $item->product_id, $source, $item->product_name);

                foreach ($item->batches ?: [['batch_number' => null, 'expiry_date' => null, 'quantity' => $item->quantity]] as $batch) {
                    StockLedger::add($target, (int) $batch['quantity'], $batch['batch_number'] ?? null, $batch['expiry_date'] ?? null);
                }

                StockLedger::summarise($target);
                $item->update(['to_medication_id' => $target->id]);

                $this->movement($target, $by, 'transfer_in', $item->quantity, "Received from {$transfer->from->name} — {$transfer->reference}", $transfer);
            }

            $transfer->update(['status' => 'received', 'closed_by' => $by->id, 'closed_at' => now()]);

            Audit::log('stock.transfer_received', "Received {$transfer->items->sum('quantity')} unit(s) from {$transfer->from->name} — {$transfer->reference}", $transfer->to_pharmacy_id, $transfer);
        });
    }

    /** The stock never left, or came back: it returns to the sending branch's shelf. */
    public function cancel(StockTransfer $transfer, User $by): void
    {
        DB::transaction(function () use ($transfer, $by) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->first();
            $this->mustBeOnTheWay($transfer);

            foreach ($transfer->items as $item) {
                $source = Medication::find($item->from_medication_id);

                if (! $source) {
                    continue;
                }

                foreach ($item->batches ?: [] as $batch) {
                    StockLedger::add($source, (int) $batch['quantity'], $batch['batch_number'] ?? null, $batch['expiry_date'] ?? null);
                }

                StockLedger::summarise($source);
                $this->movement($source, $by, 'transfer_in', $item->quantity, "Transfer {$transfer->reference} to {$transfer->to->name} cancelled", $transfer);
            }

            $transfer->update(['status' => 'cancelled', 'closed_by' => $by->id, 'closed_at' => now()]);

            Audit::log('stock.transfer_cancelled', "Cancelled transfer {$transfer->reference} to {$transfer->to->name}; stock returned", $transfer->from_pharmacy_id, $transfer);
        });
    }

    private function mustBeOnTheWay(StockTransfer $transfer): void
    {
        if ($transfer->status !== 'in_transit') {
            throw new \DomainException('This transfer has already been ' . ($transfer->status === 'received' ? 'received' : 'cancelled') . '.');
        }
    }

    /**
     * The receiving branch's own row for this product. A branch that has
     * never stocked it gets one, copied from the sender, with no stock yet.
     */
    private function shelfRow(int $branchId, ?int $productId, ?Medication $source, string $name): Medication
    {
        $existing = $productId ? Medication::where('pharmacy_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first() : null;

        if ($existing) {
            return $existing;
        }

        $row = new Medication();
        $row->forceFill([
            'pharmacy_id' => $branchId,
            'product_id' => $productId,
            'name' => $source->name ?? $name,
            'category' => $source->category ?? null,
            'dosage' => $source->dosage ?? null,
            'description' => $source->description ?? null,
            'photo' => $source?->getRawOriginal('photo'),
            // A barcode is printed on the pack, so it is the same everywhere;
            // the SKU is each branch's own code and is generated fresh.
            'barcode' => $source && ! Medication::where('pharmacy_id', $branchId)->where('barcode', $source->barcode)->exists() ? $source->barcode : null,
            'price' => $source->price ?? 0,
            'cost_price' => $source->cost_price ?? null,
            'low_stock_threshold' => $source->low_stock_threshold ?? 10,
            'is_listed' => (bool) ($source->is_listed ?? false),
            'is_marketplace_available' => (bool) ($source->is_marketplace_available ?? false),
            'stock_level' => 0,
        ])->save();

        return $row;
    }

    private function movement(Medication $medication, User $by, string $type, int $change, string $reason, StockTransfer $transfer): void
    {
        InventoryMovement::create([
            'pharmacy_id' => $medication->pharmacy_id,
            'medication_id' => $medication->id,
            'user_id' => $by->id,
            'type' => $type,
            'quantity_change' => $change,
            'quantity_after' => (int) $medication->stock_level,
            'reason' => Str::limit($reason, 250, ''),
            'source_type' => StockTransfer::class,
            'source_id' => $transfer->id,
        ]);
    }

    private function reference(): string
    {
        $prefix = 'TRF-' . now()->format('ymd') . '-';
        $last = StockTransfer::where('reference', 'like', $prefix . '%')->orderByDesc('id')->value('reference');

        return $prefix . str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }
}
