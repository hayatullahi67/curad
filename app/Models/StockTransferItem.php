<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'product_id', 'from_medication_id', 'to_medication_id', 'product_name', 'quantity', 'batches'];

    protected $casts = ['batches' => 'array', 'quantity' => 'integer'];

    public function transfer() { return $this->belongsTo(StockTransfer::class, 'stock_transfer_id'); }
}
