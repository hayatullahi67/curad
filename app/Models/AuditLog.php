<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    /**
     * Action prefixes grouped the way the Activity Log page filters them.
     */
    public const GROUPS = [
        'account' => 'Sign-ins & accounts',
        'sale' => 'Sales, voids & refunds',
        'stock' => 'Stock changes',
        'product' => 'Products',
        'staff' => 'Staff accounts',
        'order' => 'Online orders',
        'branch' => 'Branches',
        'organization' => 'Organization',
    ];

    protected $fillable = ['pharmacy_id', 'user_id', 'action', 'description', 'subject_type', 'subject_id', 'meta', 'ip_address'];

    protected $casts = ['meta' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function subject() { return $this->morphTo(); }

    public function group(): string
    {
        return explode('.', $this->action)[0];
    }
}
