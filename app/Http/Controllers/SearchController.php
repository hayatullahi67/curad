<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The customer's watchlist: products they want to keep an eye on. */
class SearchController extends Controller
{
    public function watchlist()
    {
        $items = Watchlist::with(['medication.pharmacy'])->where('user_id', Auth::id())->latest()->get();

        // What each product looks like on the marketplace right now, across every pharmacy that sells it.
        $items->each(function (Watchlist $item) {
            $medication = $item->medication;

            if (! $medication) {
                $item->live = null;

                return;
            }

            $offers = Medication::marketplace()
                ->whereHas('pharmacy', fn ($branch) => $branch->active())
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($medication->name))])
                ->get();
            $inStock = $offers->filter(fn (Medication $offer) => $offer->availableStock() > 0);

            $item->live = (object) [
                'product_id' => $offers->first()?->id,
                'pharmacies' => $inStock->pluck('pharmacy_id')->unique()->count(),
                'price' => $inStock->min('price') ?? $offers->min('price'),
                'in_stock' => $inStock->isNotEmpty(),
            ];
        });

        return view('user-dashboard.watchlist', ['items' => $items]);
    }

    /** Adds a product to the watchlist, or takes it off if it is already there. */
    public function toggleWatchlist(Request $request)
    {
        $data = $request->validate([
            'medication_id' => ['required', 'exists:medications,id'],
            'pharmacy_id' => ['nullable', 'exists:pharmacies,id'],
            'notify_on_restock' => ['nullable', 'boolean'],
        ]);

        $existing = Watchlist::where('user_id', Auth::id())->where('medication_id', $data['medication_id'])->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Removed from your watchlist.');
        }

        Watchlist::create([
            'user_id' => Auth::id(),
            'medication_id' => $data['medication_id'],
            'pharmacy_id' => $data['pharmacy_id'] ?? null,
            'notify_on_restock' => (bool) ($data['notify_on_restock'] ?? true),
        ]);

        return back()->with('success', 'Added to your watchlist.');
    }

    public function removeFromWatchlist($id)
    {
        Watchlist::where('user_id', Auth::id())->where('id', $id)->delete();

        return back()->with('success', 'Removed from your watchlist.');
    }
}
