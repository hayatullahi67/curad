<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medication extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'name',
        'sku',
        'barcode',
        'description',
        'price',
        'cost_price',
        'stock_level',
        'reserved_stock',
        'batch_number',
        'dosage',
        'category',
        'photo',
        'low_stock_threshold',
        'expiry_date',
        'is_marketplace_available',
        'is_listed',
        'listed_at',
    ];

    protected $casts = [
        'is_marketplace_available' => 'boolean',
        'is_listed' => 'boolean',
        'listed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Every product gets an SKU: the pharmacy's own short code for it.
        // Typing one is optional; a blank is filled in here.
        static::creating(function (Medication $medication) {
            if (blank($medication->sku)) {
                $medication->sku = static::generateSku((int) $medication->pharmacy_id, (string) $medication->name);
            }
        });

        // Stamp the moment a product goes up for sale, however that happens
        // (added in Inventory, listed from stock, or brought in by an import).
        static::saving(function (Medication $medication) {
            if ($medication->is_listed !== false && $medication->listed_at === null) {
                $medication->listed_at = now();
            }

            $medication->linkToCatalogue();
        });

        // What is shared across branches (name, category, photo...) is
        // edited in one branch and follows in the others.
        static::saved(function (Medication $medication) {
            $shared = array_intersect_key($medication->getAttributes(), array_flip(Product::SHARED));

            if (! $medication->product_id || ! $medication->wasChanged(array_keys($shared))) {
                return;
            }

            $product = Product::find($medication->product_id);
            $key = Product::matchKey($medication->name, $medication->dosage);
            $twin = $product ? Product::where('organization_id', $product->organization_id)->where('match_key', $key)->where('id', '!=', $product->id)->first() : null;

            if ($twin) {
                // Renamed into a product the catalogue already has: this
                // branch's stock simply moves under that entry.
                static::whereKey($medication->id)->toBase()->update(['product_id' => $twin->id]);
                $medication->product_id = $twin->id;
                $medication->syncOriginalAttribute('product_id');

                return;
            }

            $product?->update($shared + ['match_key' => $key]);
            static::where('product_id', $medication->product_id)->where('id', '!=', $medication->id)->toBase()->update($shared);
        });

        // Stock is kept in batches; any change to the total is applied to
        // them here, whichever screen made the change.
        static::created(fn (Medication $medication) => \App\Services\Inventory\StockLedger::reconcile($medication, true));
        static::updated(fn (Medication $medication) => \App\Services\Inventory\StockLedger::reconcile($medication));
    }

    /**
     * Ties this branch's stock row to the organization's catalogue: the
     * same product in another branch shares one catalogue entry.
     */
    protected function linkToCatalogue(): void
    {
        if ($this->product_id || ! $this->pharmacy_id) {
            return;
        }

        $organizationId = Pharmacy::whereKey($this->pharmacy_id)->value('organization_id');

        if (! $organizationId) {
            return;
        }

        $product = Product::firstOrCreate(
            ['organization_id' => $organizationId, 'match_key' => Product::matchKey($this->name, $this->dosage)],
            array_intersect_key($this->getAttributes(), array_flip(Product::SHARED)) + ['name' => $this->name]
        );

        $this->product_id = $product->id;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batches()
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /**
     * Next free SKU for a pharmacy: the first letters of the name plus a
     * running number, e.g. "PAR-0007". Readable at the counter, and unique
     * within the pharmacy so it can be searched and scanned.
     */
    public static function generateSku(int $pharmacyId, string $name): string
    {
        $letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        $prefix = str_pad($letters, 3, 'X') . '-';
        $number = static::where('pharmacy_id', $pharmacyId)->count() + 1;

        do {
            $sku = $prefix . str_pad((string) $number++, 4, '0', STR_PAD_LEFT);
        } while (static::where('pharmacy_id', $pharmacyId)->where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Products that have been listed in Inventory, i.e. have a selling
     * price and can be sold at the till. Stock that has only been received
     * so far is not listed yet.
     */
    public function scopeListed($query)
    {
        return $query->where('is_listed', true);
    }

    /**
     * Products the pharmacy has chosen to show to online customers. The
     * till ignores the marketplace flag — every listed product can be sold
     * in store.
     */
    public function scopeMarketplace($query)
    {
        return $query->where('is_listed', true)->where('is_marketplace_available', true);
    }

    /**
     * Units free to sell: what is on the shelf, minus units in a batch that
     * has expired, minus what is already promised to online orders.
     */
    public function availableStock(): int
    {
        return max(0, (int) $this->stock_level - (int) ($this->expired_stock ?? 0) - (int) ($this->reserved_stock ?? 0));
    }

    /** Web address of the product photo, or null when it has none. */
    public function getPhotoUrlAttribute(): ?string
    {
        $photo = $this->attributes['photo'] ?? null;

        if (blank($photo)) {
            return null;
        }

        return preg_match('#^https?://#i', $photo) ? $photo : asset('storage/' . ltrim($photo, '/'));
    }

    /** Font Awesome icon that stands for a product category on the website. */
    public static function categoryIcon(?string $category): string
    {
        $category = strtolower((string) $category);

        return match (true) {
            str_contains($category, 'prescription') => 'fa-file-prescription',
            str_contains($category, 'vitamin'), str_contains($category, 'supplement') => 'fa-leaf',
            str_contains($category, 'device') => 'fa-stethoscope',
            str_contains($category, 'baby'), str_contains($category, 'child') => 'fa-baby',
            str_contains($category, 'personal'), str_contains($category, 'hygiene') => 'fa-pump-soap',
            str_contains($category, 'beverage'), str_contains($category, 'retail'), str_contains($category, 'grocer') => 'fa-basket-shopping',
            str_contains($category, 'otc'), str_contains($category, 'medic') => 'fa-pills',
            default => 'fa-capsules',
        };
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
