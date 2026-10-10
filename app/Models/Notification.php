<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'audience',
        'type',
        'title',
        'message',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime'
    ];

    /**
     * Notifications written for one side: 'customer' (order updates for a
     * shopper) or 'pharmacy' (new orders, cancellations, stock warnings for
     * an owner). One account can be both, and each dashboard shows its own.
     */
    public function scopeAudience($query, string $audience)
    {
        return $query->where('audience', $audience);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
