<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Support\BranchDashboard;
use Illuminate\Support\Facades\Auth;

/**
 * The dashboard. The owner and every staff role land on the same page; what
 * it shows is decided by BranchDashboard from what the person may do.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $branch = Pharmacy::current();

        // An owner who has not registered their pharmacy yet does that first.
        if (! $branch) {
            return $user->role === 'vendor'
                ? redirect()->route('vendor.onboarding')
                : abort(403, 'No branch is linked to this account yet. Ask the pharmacy owner to assign you to one.');
        }

        return view('vendor-dashboard.dashboard', BranchDashboard::for($user, $branch));
    }
}
