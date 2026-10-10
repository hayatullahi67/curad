<?php

namespace App\Models;

use App\Support\StaffRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * A thread of messages between one customer and one branch. It has two
 * sides: the customer, and the pharmacy (the owner and the branch's staff
 * who handle online orders all answer as the pharmacy).
 */
class Conversation extends Model
{
    protected $fillable = [
        'reservation_id',
        'order_id',
        'user_id',
        'pharmacy_id',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** May this person use Messages on the pharmacy side at all? The owner, and staff who handle online orders. */
    public static function pharmacySideAllows(User $user): bool
    {
        return $user->role === 'vendor' || ($user->role === 'sales_person' && StaffRoles::canManageOrders($user->staff_role));
    }

    public function isCustomer(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    /**
     * The owner for any of their branches; staff only for the branch they
     * work in. One account can be on both sides: an owner who, shopping as a
     * customer, ordered from their own branch.
     */
    public function isPharmacySide(User $user): bool
    {
        return self::pharmacySideAllows($user) && (bool) $this->pharmacy?->isOpenTo($user);
    }

    /** Marks as read everything the other side wrote. $side is who is reading: 'customer' or 'pharmacy'. */
    public function markReadBy(string $side): void
    {
        $this->messages()->where('is_read', false)->where('from_pharmacy', $side === 'customer')->update(['is_read' => true]);
    }
}
