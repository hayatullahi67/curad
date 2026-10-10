<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Units of one product in one branch that arrived together and expire together. */
class InventoryBatch extends Model
{
    protected $fillable = ['pharmacy_id', 'medication_id', 'batch_number', 'expiry_date', 'quantity', 'received_at'];

    protected $casts = ['expiry_date' => 'date', 'received_at' => 'date', 'quantity' => 'integer'];

    public function medication() { return $this->belongsTo(Medication::class); }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }

    /** expired | soon (within 90 days) | ok | none (no expiry date recorded) */
    public function expiryState(): string
    {
        if ($this->expiry_date === null) {
            return 'none';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        return $this->expiry_date->lte(today()->addDays(90)) ? 'soon' : 'ok';
    }
}
