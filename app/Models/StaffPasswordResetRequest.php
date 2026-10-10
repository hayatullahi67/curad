<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPasswordResetRequest extends Model
{
    protected $fillable = [
        'staff_id',
        'pharmacy_id',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
