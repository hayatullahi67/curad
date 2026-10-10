<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use App\Models\Wallet;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name', // Mapped from 'name'
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'address',
        'cura_role', // Mapped from 'role'
        'role',
        'wallet_balance',
        'password_hash', // Mapped from 'password'
        'pin_hash', // Mapped from 'transaction_pin'
        'notification_settings',
        'created_by_user_id',
        'assigned_pharmacy_id',
        'staff_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Map 'name' to 'full_name' for compatibility with existing CURA code
     */
    public function getNameAttribute()
    {
        return $this->full_name;
    }

    public function setNameAttribute($value)
    {
        $this->attributes['full_name'] = $value;
    }

    /**
     * Map 'password' to 'password_hash'
     */
    public function getPasswordAttribute()
    {
        return $this->password_hash;
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = $value;
    }

    /**
     * Map 'role' to 'cura_role'
     */
    public function getRoleAttribute()
    {
        return $this->cura_role;
    }

    public function setRoleAttribute($value)
    {
        $this->attributes['cura_role'] = $value;
    }

    /**
     * Map 'transaction_pin' to 'pin_hash'
     */
    public function getTransactionPinAttribute()
    {
        return $this->pin_hash;
    }

    public function setTransactionPinAttribute($value)
    {
        $this->attributes['pin_hash'] = $value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'notification_settings' => 'array',
        ];
    }

    /**
     * Get the pharmacies saved by the user.
     */
    public function savedPharmacies()
    {
        return $this->belongsToMany(Pharmacy::class, 'saved_pharmacies');
    }

    /**
     * Get the user's watchlist items.
     */
    public function watchlist()
    {
        return $this->hasMany(Watchlist::class);
    }

    /**
     * Get the wallet associated with the user.
     */
    public function wallet()
    {
        return $this->hasOne('App\Models\Wallet');
    }

    /**
     * Get the consolidated wallet balance (Sync with Valu logic).
     * Source of truth: wallets.balance -> users.wallet_balance
     */
    public function getWalletBalanceAttribute($value)
    {
        $walletTableBalance = $this->wallet ? (float)$this->wallet->balance : null;
        $userTableBalance = (float)($this->attributes['wallet_balance'] ?? 0);

        // Logic from user/index.php:
        // Prefer wallet_table_balance if it exists, otherwise use user_table_balance.
        // If wallet_table_balance is 0 but user_table_balance is positive, prefer the positive one.
        $rawBalance = $walletTableBalance ?? $userTableBalance;

        if ($rawBalance <= 0 && $userTableBalance > 0) {
            $rawBalance = $userTableBalance;
        }

        return $rawBalance;
    }

    /**
     * Deduct balance from the user's wallet across both tables to stay in sync.
     */
    public function deductBalance($amount)
    {
        $amount = (float)$amount;

        // 1. Update users table
        DB::table('users')->where('id', $this->id)->decrement('wallet_balance', $amount);

        // 2. Update wallets table if it exists
        if (DB::table('wallets')->where('user_id', $this->id)->exists()) {
            DB::table('wallets')->where('user_id', $this->id)->decrement('balance', $amount);
        }

        $this->refresh();
    }

    /**
     * Add balance to the user's wallet across both tables to stay in sync.
     */
    public function addBalance($amount)
    {
        $amount = (float)$amount;

        // 1. Update users table
        DB::table('users')->where('id', $this->id)->increment('wallet_balance', $amount);

        // 2. Update wallets table if it exists
        if (DB::table('wallets')->where('user_id', $this->id)->exists()) {
            DB::table('wallets')->where('user_id', $this->id)->increment('balance', $amount);
        }

        $this->refresh();
    }

    /**
     * Get the reservations made by the user.
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
