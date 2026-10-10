<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRefund extends Model
{
    protected $fillable = ['pos_sale_id', 'pharmacy_id', 'refund_no', 'user_id', 'shift_id', 'authorized_by', 'amount', 'method', 'reason'];

    protected $casts = ['amount' => 'float'];

    public function sale() { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }
    public function items() { return $this->hasMany(PosRefundItem::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function authorizedBy() { return $this->belongsTo(User::class, 'authorized_by'); }
}
