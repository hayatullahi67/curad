<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['pharmacy_id', 'name', 'phone'];

    public function stockReceipts() { return $this->hasMany(StockReceipt::class); }
}
