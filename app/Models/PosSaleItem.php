<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSaleItem extends Model
{
    protected $fillable = ['pos_sale_id', 'medication_id', 'product_name', 'sku', 'batch_number', 'quantity', 'returned_quantity', 'unit_price', 'unit_cost', 'discount_amount', 'line_total'];

    protected $casts = [
        'unit_price' => 'float',
        'unit_cost' => 'float',
        'discount_amount' => 'float',
        'line_total' => 'float',
    ];

    public function sale() { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function product() { return $this->belongsTo(Medication::class, 'medication_id'); }

    public function returnableQuantity(): int
    {
        return max(0, $this->quantity - $this->returned_quantity);
    }

    /**
     * What the customer actually paid per unit once this line's share of
     * the sale discount is taken off — the amount a refund gives back.
     */
    public function netUnitPrice(): float
    {
        return $this->quantity > 0 ? ($this->line_total - $this->discount_amount) / $this->quantity : 0.0;
    }
}
