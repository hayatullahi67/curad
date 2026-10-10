<?php

namespace App\Http\Middleware;

use App\Models\Pharmacy;
use App\Support\StaffRoles;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the management pages. The owner may open all of them. A staff
 * member may open only the ones their role covers (an Inventory Officer the
 * stock pages, a Finance Officer the reports), and always for their own
 * branch only, because every page works on Pharmacy::current().
 */
class EnsureVendorRole
{
    /** Which staff ability opens which pages, by route name. */
    private const STAFF_PAGES = [
        'inventory' => ['vendor.inventory', 'vendor.inventory.*', 'vendor.stock.*', 'vendor.transfers.*'],
        'reports' => ['vendor.reports'],
        'activity' => ['vendor.activity'],
        'can_manage_orders' => ['vendor.chat', 'vendor.chat.*'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (optional($user)->role === 'vendor') {
            return $next($request);
        }

        if (optional($user)->role === 'sales_person') {
            // Every staff member may open Settings, where they see only their own account.
            if ($request->routeIs('vendor.profile', 'vendor.profile.*')) {
                return $next($request);
            }

            foreach (self::STAFF_PAGES as $ability => $routes) {
                if ($request->routeIs(...$routes) && StaffRoles::can($user->staff_role, $ability)) {
                    return $next($request);
                }
            }

            // A staff account also lands here via stale browser history: the
            // owner was on an owner-only page, used "Login as Staff", and the
            // Back button re-requests that URL as staff. A hard 403 would be a
            // dead end, so they go to their own dashboard instead.
            return redirect()->route('sales.staff.dashboard')
                ->with('error', 'Your role does not have access to that page.');
        }

        // An owner who pressed "Switch to shopping" is stored as a customer
        // until they switch back. Opening a pharmacy page again (an old tab,
        // a bookmark, the Back button) is them coming back, so the account
        // returns to pharmacy mode instead of hitting a dead end.
        if ($user && Pharmacy::where('user_id', $user->id)->exists()) {
            $user->role = 'vendor';
            $user->save();

            return $next($request);
        }

        // A customer with no pharmacy has nothing to see here.
        if ($request->expectsJson()) {
            abort(403, 'This page is for pharmacy accounts only.');
        }

        return redirect()->route('user.dashboard')
            ->with('error', 'That page is for pharmacy accounts. Use "Switch to Pharmacy" to set one up.');
    }
}
