<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Pharmacies a customer has saved to find again quickly. */
class PharmacyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $query = Auth::user()->savedPharmacies()->with('organization');

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';

            $query->where(function ($inner) use ($like) {
                $inner->where('pharmacies.name', 'like', $like)
                    ->orWhere('pharmacies.city', 'like', $like)
                    ->orWhere('pharmacies.address', 'like', $like);
            });
        }

        return view('user-dashboard.pharmacies', [
            'pharmacies' => $query->orderBy('pharmacies.name')->paginate(12)->withQueryString(),
            'search' => $search,
            'savedCount' => Auth::user()->savedPharmacies()->count(),
        ]);
    }

    public function toggleSave(Pharmacy $pharmacy)
    {
        $user = Auth::user();

        if ($user->savedPharmacies()->where('pharmacy_id', $pharmacy->id)->exists()) {
            $user->savedPharmacies()->detach($pharmacy->id);

            return back()->with('success', "{$pharmacy->name} was removed from your saved pharmacies.");
        }

        $user->savedPharmacies()->attach($pharmacy->id);

        return back()->with('success', "{$pharmacy->name} was saved.");
    }
}
