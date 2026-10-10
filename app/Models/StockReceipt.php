<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One delivery of goods into the pharmacy — the record behind every
 * "received" stock movement.
 */
class StockReceipt extends Model
{
    protected $fillable = ['pharmacy_id', 'supplier_id', 'user_id', 'reference_no', 'invoice_no', 'received_at', 'total_cost', 'note'];

    protected $casts = ['received_at' => 'date', 'total_cost' => 'float'];

    public function items() { return $this->hasMany(StockReceiptItem::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function user() { return $this->belongsTo(User::class); }
}
