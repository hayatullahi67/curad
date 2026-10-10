<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    protected $fillable = [
        'pharmacy_id',
        'title',
        'message',
        'type',
        'target_audience',
        'recipients_count'
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
