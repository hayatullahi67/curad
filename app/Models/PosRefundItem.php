<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRefundItem extends Model
{
    protected $fillable = ['pos_refund_id', 'pos_sale_item_id', 'medication_id', 'product_name', 'quantity', 'amount', 'restocked'];

    protected $casts = ['amount' => 'float', 'restocked' => 'boolean'];

    public function refund() { return $this->belongsTo(PosRefund::class, 'pos_refund_id'); }
}
