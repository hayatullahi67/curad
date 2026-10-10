<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $table = 'transactions';

    // Disable timestamps as we'll use Valu's database defaults or manual set
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'type',
        'category',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'recipient',
        'status',
        'reference',
        'related_id',
        'related_type',
        'metadata',
        'created_at',
        'updated_at'
    ];
}

