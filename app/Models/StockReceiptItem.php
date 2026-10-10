<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReceiptItem extends Model
{
    protected $fillable = ['stock_receipt_id', 'medication_id', 'product_name', 'quantity', 'unit_cost', 'batch_number', 'expiry_date'];

    protected $casts = ['unit_cost' => 'float', 'expiry_date' => 'date'];

    public function receipt() { return $this->belongsTo(StockReceipt::class, 'stock_receipt_id'); }
    public function product() { return $this->belongsTo(Medication::class, 'medication_id'); }
}
