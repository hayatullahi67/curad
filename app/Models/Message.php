<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'from_pharmacy',
        'message',
        'is_read'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        // True when the pharmacy side wrote it, false when the customer did.
        'from_pharmacy' => 'boolean',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
