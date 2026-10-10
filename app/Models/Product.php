<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One product in an organization's catalogue. Each branch that stocks it has
 * its own Medication row pointing here, with that branch's stock and price.
 */
class Product extends Model
{
    /** What is the same in every branch; changing it in one changes it in all. */
    public const SHARED = ['name', 'category', 'dosage', 'barcode', 'description', 'photo', 'is_rx'];

    protected $fillable = ['organization_id', 'name', 'match_key', 'category', 'dosage', 'barcode', 'description', 'photo', 'is_rx'];

    protected $casts = ['is_rx' => 'boolean'];

    public function organization() { return $this->belongsTo(Organization::class); }
    public function stock() { return $this->hasMany(Medication::class); }

    public static function matchKey(?string $name, ?string $dosage): string
    {
        return mb_strtolower(trim((string) $name)) . '|' . mb_strtolower(trim((string) $dosage));
    }
}
