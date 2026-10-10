<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A shopping cart. A visitor's cart is found by a token kept in their
 * session; once they sign in it becomes (or is merged into) their own cart.
 */
class Cart extends Model
{
    protected $fillable = ['user_id', 'token'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /** How a product is recognised across pharmacies: its name, tidied up. */
    public static function productKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * The cart of whoever is making this request. With $create = false
     * nothing is written when they have no cart yet.
     */
    public static function current(bool $create = true): ?self
    {
        $token = session('cart_token');
        $guestCart = $token ? static::where('token', $token)->whereNull('user_id')->first() : null;

        if (! Auth::check()) {
            if ($guestCart || ! $create) {
                return $guestCart;
            }

            $token = Str::random(40);
            session(['cart_token' => $token]);

            return static::create(['token' => $token]);
        }

        $cart = static::where('user_id', Auth::id())->first();

        if (! $cart && ($create || $guestCart)) {
            $cart = static::create(['user_id' => Auth::id()]);
        }

        // What was added before signing in comes along.
        if ($cart && $guestCart) {
            foreach ($guestCart->items as $item) {
                $cart->add($item->product_key, $item->medication_id, $item->quantity);
            }

            $guestCart->delete();
            session()->forget('cart_token');
        }

        return $cart;
    }

    public function add(string $productKey, int $medicationId, int $quantity): CartItem
    {
        $max = (int) config('curad.marketplace.max_quantity');
        $item = $this->items()->firstOrNew(['product_key' => $productKey]);

        $item->medication_id = $medicationId;
        $item->quantity = max(1, min($max, (int) $item->quantity + $quantity));
        $item->save();

        return $item;
    }

    /** Number of units in the cart, for the badge in the menu. */
    public static function count(): int
    {
        $cart = static::current(false);

        return $cart ? (int) $cart->items()->sum('quantity') : 0;
    }
}
