<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Medication;
use App\Services\Marketplace\FulfillmentPlanner;
use Illuminate\Http\Request;

/**
 * The shopping cart. Open to everyone: a visitor can fill a cart first and
 * is only asked to sign in when they check out.
 */
class CartController extends Controller
{
    public function index(FulfillmentPlanner $planner)
    {
        $cart = Cart::current(false);
        $items = $cart ? $cart->items()->with('medication')->orderBy('id')->get() : collect();

        // Planned as a pickup with no location, just to show the price today
        // and whether each product can still be supplied.
        $plan = $planner->plan($items->pluck('quantity', 'product_key'), 'pickup');

        $prices = [];
        foreach ($plan['groups'] as $group) {
            foreach ($group['lines'] as $line) {
                $prices[$line['product_key']] = $line;
            }
        }

        return view('cart', [
            'items' => $items,
            'plan' => $plan,
            'prices' => $prices,
            'maxQuantity' => (int) config('curad.marketplace.max_quantity'),
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'medication_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:' . config('curad.marketplace.max_quantity')],
        ]);

        // Only products a pharmacy has put on the marketplace can be ordered.
        $medication = Medication::marketplace()->whereHas('pharmacy', fn ($branch) => $branch->active())->findOrFail($data['medication_id']);

        Cart::current()->add(Cart::productKey($medication->name), $medication->id, (int) ($data['quantity'] ?? 1));

        if ($request->boolean('buy_now')) {
            return redirect()->route('checkout');
        }

        return redirect()->route('cart')->with('success', "{$medication->name} is in your cart.");
    }

    /** Old "reserve this" links land here: the product goes in the cart. */
    public function addFromLink(Request $request)
    {
        $medication = Medication::marketplace()->whereHas('pharmacy')->find((int) $request->input('medication_id'));

        if (! $medication) {
            return redirect()->route('marketplace')->with('error', 'That product is not available any more.');
        }

        Cart::current()->add(Cart::productKey($medication->name), $medication->id, 1);

        return redirect()->route('cart')->with('success', "{$medication->name} is in your cart.");
    }

    public function update(Request $request, CartItem $item)
    {
        $this->own($item);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:' . config('curad.marketplace.max_quantity')],
        ]);

        $item->update(['quantity' => $data['quantity']]);

        return redirect()->route('cart');
    }

    public function remove(CartItem $item)
    {
        $this->own($item);
        $item->delete();

        return redirect()->route('cart')->with('success', 'Removed from your cart.');
    }

    /** A cart line can only be changed by whoever owns that cart. */
    private function own(CartItem $item): void
    {
        abort_unless($item->cart_id === Cart::current(false)?->id, 404);
    }
}
