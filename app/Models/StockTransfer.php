<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stock sent from one branch to another. It leaves the sender at once and arrives when the receiver confirms. */
class StockTransfer extends Model
{
    public const STATUSES = ['in_transit' => 'On the way', 'received' => 'Received', 'cancelled' => 'Cancelled'];

    protected $fillable = ['organization_id', 'reference', 'from_pharmacy_id', 'to_pharmacy_id', 'status', 'note', 'sent_by', 'closed_by', 'sent_at', 'closed_at'];

    protected $casts = ['sent_at' => 'datetime', 'closed_at' => 'datetime'];

    public function items() { return $this->hasMany(StockTransferItem::class); }
    public function from() { return $this->belongsTo(Pharmacy::class, 'from_pharmacy_id'); }
    public function to() { return $this->belongsTo(Pharmacy::class, 'to_pharmacy_id'); }
    public function sender() { return $this->belongsTo(User::class, 'sent_by'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
