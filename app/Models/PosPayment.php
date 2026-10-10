<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosPayment extends Model
{
    /** Payment methods the till accepts, keyed as the POS screen submits them. */
    public const METHODS = [
        'cash' => 'Cash',
        'pos_card' => 'POS Card',
        'valu_wallet' => 'Valu Wallet',
        'bank_transfer' => 'Bank Transfer',
    ];

    protected $fillable = ['pos_sale_id', 'method', 'amount', 'amount_tendered', 'change_given', 'status', 'reference'];

    protected $casts = [
        'amount' => 'float',
        'amount_tendered' => 'float',
        'change_given' => 'float',
    ];

    public function sale() { return $this->belongsTo(PosSale::class, 'pos_sale_id'); }

    public static function methodLabel(?string $method): string
    {
        return self::METHODS[$method] ?? ucwords(str_replace('_', ' ', (string) $method));
    }
}
