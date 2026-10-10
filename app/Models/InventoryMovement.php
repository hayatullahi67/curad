<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    /** Every reason stock can change, with the wording shown in Stock History. */
    public const TYPES = [
        'opening_stock' => 'Opening stock',
        'received' => 'Stock received',
        'sale' => 'POS sale',
        'void' => 'Sale voided',
        'return' => 'Customer return',
        'adjustment' => 'Manual adjustment',
        'import' => 'CSV import',
        'reservation' => 'Online order pickup',
        'online_order' => 'Online order',
        'transfer_out' => 'Sent to another branch',
        'transfer_in' => 'Received from another branch',
    ];

    protected $fillable = ['pharmacy_id', 'medication_id', 'user_id', 'type', 'quantity_change', 'quantity_after', 'reason', 'source_type', 'source_id'];

    public function product() { return $this->belongsTo(Medication::class, 'medication_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function source() { return $this->morphTo(); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucwords(str_replace('_', ' ', (string) $this->type));
    }
}
