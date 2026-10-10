<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person a pharmacy has sold to at the till, known by their phone number.
 */
class Customer extends Model
{
    protected $fillable = ['pharmacy_id', 'name', 'phone'];

    public function sales() { return $this->hasMany(PosSale::class); }

    /**
     * Phone numbers are typed in many shapes ("0803 123 4567",
     * "0803-123-4567"). Keep only the digits and a leading +, so the same
     * person is found again however the number is typed.
     */
    public static function normalizePhone(?string $phone): string
    {
        $phone = trim((string) $phone);

        return (str_starts_with($phone, '+') ? '+' : '') . preg_replace('/\D/', '', $phone);
    }

    public function displayName(): string
    {
        return $this->name ?: $this->phone;
    }
}
