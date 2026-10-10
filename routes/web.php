<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\Vendor\DashboardController as VendorDashboardController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Vendor\OnlineOrderController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Vendor\SalesStaffController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

// The public website: anyone can look around without signing in.
Route::get('/', [MarketplaceController::class, 'home'])->name('home');
Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace');
Route::get('/marketplace/{medication}', [MarketplaceController::class, 'show'])->whereNumber('medication')->name('marketplace.show');

// The cart is open to visitors too; signing in is only asked for at checkout.
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware(['auth'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout/options', [CheckoutController::class, 'saveOptions'])->name('checkout.options');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
});

// cPanel has no terminal, so cache clearing and migrations are web routes.
// Both change the live site, so they only answer to the secret key set as
// CURA_MAINTENANCE_KEY in .env (e.g. /migrate?key=...). With no key set, or
// the wrong one, they look like pages that do not exist.
$requireMaintenanceKey = function (\Illuminate\Http\Request $request) {
    $key = (string) config('cura.maintenance_key');

    abort_unless($key !== '' && hash_equals($key, (string) $request->query('key')), 404);
};

// Cache Clearing Route for cPanel
Route::get('/clear-cache', function (\Illuminate\Http\Request $request) use ($requireMaintenanceKey) {
    $requireMaintenanceKey($request);

    Artisan::call('optimize:clear');

    return "All caches cleared successfully!";
});

// Migration Route for cPanel. Add &file=<migration file name> to run just
// that one migration — needed on databases that were set up from an SQL
// import, where the older migrations are not recorded as already run.
Route::get('/migrate', function (\Illuminate\Http\Request $request) use ($requireMaintenanceKey) {
    $requireMaintenanceKey($request);

    $options = ['--force' => true];

    if ($file = $request->query('file')) {
        $file = basename((string) $file, '.php') . '.php';

        if (! file_exists(database_path('migrations/' . $file))) {
            return 'Migration file not found: ' . e($file);
        }

        $options['--path'] = 'database/migrations/' . $file;
    }

    try {
        Artisan::call('migrate', $options);
        return "Migrations ran successfully!<br><pre>" . e(Artisan::output()) . "</pre>";
    } catch (\Exception $e) {
        return "Error running migrations: " . e($e->getMessage());
    }
});

// Uploaded files (product photos) live in storage/app/public. Normally the
// web server reaches them through the public/storage symlink, but shared
// hosts and Windows often cannot create that link. When it is missing the
// request lands here instead, so photos work either way.
Route::get('/storage/{path}', function (string $path) {
    $disk = \Illuminate\Support\Facades\Storage::disk('public');

    abort_if(str_contains($path, '..'), 404);
    abort_unless($disk->exists($path), 404);

    return $disk->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
})->where('path', '.*')->name('storage.public');

// Auth Routes. CuraD signs people in itself (it no longer goes through Valu).
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');

// Forgotten password: a link is emailed, and opening it lets the person
// choose a new password.
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');


// The old search, results, map, pharmacy directory, about and pricing pages
// are gone: the marketplace is the one place to look for products. Their
// addresses and route names still work, and a search carries its words along.
$toMarketplace = fn (\Illuminate\Http\Request $request) => redirect()->route('marketplace', array_filter(['q' => $request->query('query')]));
Route::get('/search', $toMarketplace)->name('search');
Route::get('/results', $toMarketplace)->name('results');
Route::get('/pharmacies', $toMarketplace)->name('pharmacies');
Route::get('/about', fn () => redirect()->route('home'))->name('about');
Route::get('/pricing', fn () => redirect()->route('home'))->name('pricing');

// Staff sign in on the same page as everyone else. The old staff addresses
// still work for anyone who bookmarked them.
Route::redirect('/sales-staff/login', '/login')->name('sales.staff.login');
Route::redirect('/sales-staff/forgot-password', '/forgot-password')->name('sales.staff.forgot-password');

Route::middleware(['auth'])->group(function () {
    Route::get('/sales-staff/dashboard', [SalesStaffController::class, 'dashboard'])->name('sales.staff.dashboard');
    Route::get('/sales-staff/my-sales', [SalesStaffController::class, 'mySales'])->name('sales.staff.my-sales');
    Route::get('/sales-staff/pos', [SalesStaffController::class, 'pos'])->name('sales.staff.pos');
    Route::post('/sales-staff/pos', [SalesStaffController::class, 'processSale'])->name('sales.staff.pos.sale');
    Route::post('/sales-staff/logout', [SalesStaffController::class, 'logout'])->name('sales.staff.logout');
    Route::post('/sales-staff/return-to-admin', [SalesStaffController::class, 'returnToAdmin'])->name('sales.staff.return-to-admin');
});

// User Dashboard Routes
Route::middleware(['auth'])->prefix('user')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('user.dashboard');

    // The marketplace inside the dashboard: the same catalogue as the public one, in the customer's own frame.
    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('user.marketplace');
    Route::get('/marketplace/{medication}', [MarketplaceController::class, 'show'])->whereNumber('medication')->name('user.marketplace.show');
    Route::get('/map', fn (\Illuminate\Http\Request $request) => redirect()->route('marketplace', array_filter(['q' => $request->query('query')])))->name('map');
    
    Route::get('/orders', [OrderController::class, 'index'])->name('user.orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('user.orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('user.orders.cancel');
    Route::post('/orders/{order}/received', [OrderController::class, 'received'])->name('user.orders.received');

    // Reservations were replaced by carts and orders. The old addresses
    // and route names still work so older pages and bookmarks do not break.
    Route::get('/reservation', fn () => redirect()->route('user.orders'))->name('user.reservation');
    Route::get('/checkout', [CartController::class, 'addFromLink'])->name('user.checkout');
    Route::post('/reservation', [CartController::class, 'addFromLink'])->name('user.reservation.store');
    Route::delete('/reservation/{reservation}', fn () => redirect()->route('user.orders'))->name('user.reservation.destroy');
    
    Route::get('/pharmacies', [App\Http\Controllers\PharmacyController::class, 'index'])->name('user.pharmacies');
    Route::post('/pharmacies/{pharmacy}/toggle', [App\Http\Controllers\PharmacyController::class, 'toggleSave'])->name('user.pharmacies.toggle');
    
    Route::get('/history', [HistoryController::class, 'index'])->name('user.history');
    Route::get('/notifications', [DashboardController::class, 'notifications'])->name('user.notifications');
    Route::post('/notifications/mark-read', [DashboardController::class, 'markNotificationsRead'])->name('user.notifications.mark-read');
    Route::delete('/notifications/{id}', [DashboardController::class, 'deleteNotification'])->name('user.notifications.delete');
    Route::get('/notifications/preferences', [DashboardController::class, 'showPreferences'])->name('user.notifications.preferences');
    Route::post('/notifications/preferences', [DashboardController::class, 'savePreferences'])->name('user.notifications.preferences.save');
    Route::get('/watchlist', [SearchController::class, 'watchlist'])->name('user.watchlist');
    Route::post('/watchlist/toggle', [SearchController::class, 'toggleWatchlist'])->name('user.watchlist.toggle');
    Route::delete('/watchlist/{id}', [SearchController::class, 'removeFromWatchlist'])->name('user.watchlist.destroy');
    Route::get('/help', function () { return view('user-dashboard.help'); })->name('user.help');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('user.profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('user.profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('user.profile.password');
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('user.profile.pin');
    Route::post('/become-pharmacy', [AuthController::class, 'becomePharmacy'])->name('user.become.pharmacy');
    
    Route::get('/chat', [\App\Http\Controllers\User\ChatController::class, 'index'])->name('user.chat');
    Route::post('/chat/create', [\App\Http\Controllers\User\ChatController::class, 'create'])->name('user.chat.create');
    Route::get('/chat/{conversation}', [\App\Http\Controllers\User\ChatController::class, 'show'])->name('user.chat.show');
    Route::post('/chat/{conversation}', [\App\Http\Controllers\User\ChatController::class, 'store'])->name('user.chat.store');
});

// Vendor Dashboard Routes
// Vendor-only management pages — the 'vendor.only' middleware rejects any
// authenticated user whose role isn't 'vendor' (staff, patients) with a 403,
// rather than letting the controller run and render with whatever sidebar
// their real role happens to map to.
Route::middleware(['auth', 'vendor.only'])->prefix('vendor')->group(function () {
    Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('vendor.dashboard');

    Route::get('/inventory', [\App\Http\Controllers\Vendor\InventoryController::class, 'index'])->name('vendor.inventory');
    Route::post('/inventory', [\App\Http\Controllers\Vendor\InventoryController::class, 'store'])->name('vendor.inventory.store');
    Route::put('/inventory/{id}', [\App\Http\Controllers\Vendor\InventoryController::class, 'update'])->name('vendor.inventory.update');
    Route::post('/inventory/{id}/adjust', [\App\Http\Controllers\Vendor\InventoryController::class, 'adjustStock'])->name('vendor.inventory.adjust');
    Route::post('/inventory/import/preview', [\App\Http\Controllers\Vendor\InventoryImportController::class, 'preview'])->name('vendor.inventory.import.preview');
    Route::post('/inventory/import/confirm', [\App\Http\Controllers\Vendor\InventoryImportController::class, 'confirm'])->name('vendor.inventory.import.confirm');
    Route::get('/inventory/receive', [\App\Http\Controllers\Vendor\StockController::class, 'receiveForm'])->name('vendor.stock.receive');
    Route::post('/inventory/receive', [\App\Http\Controllers\Vendor\StockController::class, 'receiveStore'])->name('vendor.stock.receive.store');
    Route::post('/inventory/receive/new-product', [\App\Http\Controllers\Vendor\StockController::class, 'quickProduct'])->name('vendor.stock.quick-product');
    // Stock History used to be a page of its own; it is now a tab of
    // Receive Stock. Old bookmarks land on that tab with their filters.
    Route::get('/inventory/history', fn (\Illuminate\Http\Request $request) => redirect()->route('vendor.stock.receive', ['tab' => 'history'] + $request->query()));
    Route::delete('/inventory/{id}', [\App\Http\Controllers\Vendor\InventoryController::class, 'destroy'])->name('vendor.inventory.destroy');

    // Stock sent between branches. It appears as a tab on the Stock page.
    Route::post('/transfers', [\App\Http\Controllers\Vendor\TransferController::class, 'store'])->name('vendor.transfers.store');
    Route::post('/transfers/{transfer}/receive', [\App\Http\Controllers\Vendor\TransferController::class, 'receive'])->name('vendor.transfers.receive');
    Route::post('/transfers/{transfer}/cancel', [\App\Http\Controllers\Vendor\TransferController::class, 'cancel'])->name('vendor.transfers.cancel');

    // The organization and its branches (owner only).
    Route::get('/branches', [\App\Http\Controllers\Vendor\BranchController::class, 'index'])->name('vendor.branches');
    Route::post('/branches', [\App\Http\Controllers\Vendor\BranchController::class, 'store'])->name('vendor.branches.store');
    Route::get('/branches/{branch}', [\App\Http\Controllers\Vendor\BranchController::class, 'show'])->name('vendor.branches.show');
    Route::put('/branches/{branch}', [\App\Http\Controllers\Vendor\BranchController::class, 'update'])->name('vendor.branches.update');
    Route::post('/branches/{branch}/toggle', [\App\Http\Controllers\Vendor\BranchController::class, 'toggle'])->name('vendor.branches.toggle');
    Route::post('/branches/{branch}/switch', [\App\Http\Controllers\Vendor\BranchController::class, 'switch'])->name('vendor.branches.switch');

    Route::get('/pos', [SalesStaffController::class, 'pos'])->name('vendor.pos');
    Route::post('/pos', [SalesStaffController::class, 'processSale'])->name('vendor.pos.sale');

    // Old reservation buttons: online orders are handled on the Online Orders page now.
    foreach (['status' => '/reservations/{reservation}/status', 'quick' => '/reservations/{reservation}/quick', 'fulfill' => '/reservations/{reservation}/fulfill', 'bulk' => '/reservations/bulk'] as $legacyName => $legacyPath) {
        Route::post($legacyPath, fn () => redirect()->route('vendor.orders'))->name('vendor.reservations.' . $legacyName);
    }

    Route::get('/chat', [\App\Http\Controllers\Vendor\ChatController::class, 'index'])->name('vendor.chat');
    Route::get('/chat/{conversation}', [\App\Http\Controllers\Vendor\ChatController::class, 'show'])->name('vendor.chat.show');
    Route::post('/chat/{conversation}', [\App\Http\Controllers\Vendor\ChatController::class, 'store'])->name('vendor.chat.store');

    Route::get('/notifications', [\App\Http\Controllers\Vendor\NotificationController::class, 'index'])->name('vendor.notifications');
    Route::post('/notifications/read', [\App\Http\Controllers\Vendor\NotificationController::class, 'markAsRead'])->name('vendor.notifications.read');
    Route::post('/notifications/{id}/toggle', [\App\Http\Controllers\Vendor\NotificationController::class, 'toggle'])->name('vendor.notifications.toggle');
    Route::delete('/notifications', [\App\Http\Controllers\Vendor\NotificationController::class, 'clearRead'])->name('vendor.notifications.clear');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Vendor\NotificationController::class, 'destroy'])->name('vendor.notifications.destroy');

    Route::get('/broadcasts', [\App\Http\Controllers\Vendor\BroadcastController::class, 'index'])->name('vendor.broadcast');
    Route::post('/broadcasts', [\App\Http\Controllers\Vendor\BroadcastController::class, 'store'])->name('vendor.broadcast.store');

    Route::get('/reports', [\App\Http\Controllers\Vendor\ReportController::class, 'index'])->name('vendor.reports');
    Route::get('/activity', [\App\Http\Controllers\Vendor\AuditLogController::class, 'index'])->name('vendor.activity');
    Route::get('/sales-staff', [SalesStaffController::class, 'index'])->name('vendor.sales-staff');
    Route::post('/sales-staff', [SalesStaffController::class, 'store'])->name('vendor.sales-staff.store');
    Route::get('/sales-staff/{id}/history', [SalesStaffController::class, 'history'])->name('vendor.sales-staff.history');
    Route::delete('/sales-staff/{id}', [SalesStaffController::class, 'destroy'])->name('vendor.sales-staff.destroy');
    Route::post('/sales-staff/{id}/reactivate', [SalesStaffController::class, 'reactivate'])->name('vendor.sales-staff.reactivate');
    Route::post('/sales-staff/{id}/assign', [SalesStaffController::class, 'assign'])->name('vendor.sales-staff.assign');
    Route::post('/sales-staff/{id}/login-as', [SalesStaffController::class, 'loginAs'])->name('vendor.sales-staff.login-as');
    Route::post('/sales-staff/{id}/reset-password', [SalesStaffController::class, 'resetPassword'])->name('vendor.sales-staff.reset-password');

    // Settings: the owner's organization and branches, and everyone's own account.
    // Staff reach it too (see EnsureVendorRole) but only see their own account.
    Route::get('/settings', [\App\Http\Controllers\Vendor\SettingsController::class, 'index'])->name('vendor.profile');
    Route::patch('/settings/account', [\App\Http\Controllers\Vendor\SettingsController::class, 'updateAccount'])->name('vendor.profile.update');
    Route::put('/settings/password', [\App\Http\Controllers\Vendor\SettingsController::class, 'updatePassword'])->name('vendor.profile.password');
    Route::patch('/settings/organization', [\App\Http\Controllers\Vendor\SettingsController::class, 'updateOrganization'])->name('vendor.organization.update');
    Route::redirect('/profile', '/vendor/settings');
    Route::post('/become-patient', [AuthController::class, 'becomePatient'])->name('vendor.become.patient');
});

// Routes reachable by any authenticated user working toward or within a
// vendor facility, not just confirmed vendors: onboarding is how a patient
// account becomes a vendor, and the reservations list also serves eligible
// staff read-only (see ReservationController::index / StaffRoles::canViewReservations).
Route::middleware(['auth'])->prefix('vendor')->group(function () {
    // Online orders: this pharmacy's share of each marketplace order. Open to
    // the owner and to staff whose role views or manages orders
    // (OnlineOrderController::context() checks).
    Route::get('/orders', [OnlineOrderController::class, 'index'])->name('vendor.orders');
    Route::post('/orders/{fulfillment}/accept', [OnlineOrderController::class, 'accept'])->name('vendor.orders.accept');
    Route::post('/orders/{fulfillment}/ready', [OnlineOrderController::class, 'ready'])->name('vendor.orders.ready');
    Route::post('/orders/{fulfillment}/reject', [OnlineOrderController::class, 'reject'])->name('vendor.orders.reject');
    Route::post('/orders/{fulfillment}/collect', [OnlineOrderController::class, 'collect'])->name('vendor.orders.collect');
    Route::post('/orders/{fulfillment}/message', [OnlineOrderController::class, 'message'])->name('vendor.orders.message');
    Route::get('/reservations', fn () => redirect()->route('vendor.orders'))->name('vendor.reservations');

    // Sales & Receipts is shared by the owner and sales staff — staff see
    // their own sales, supervisors and the owner see all of them.
    // SalesController::context() enforces that.
    Route::get('/sales', [\App\Http\Controllers\Vendor\SalesController::class, 'index'])->name('vendor.sales');
    Route::get('/sales/{sale}', [\App\Http\Controllers\Vendor\SalesController::class, 'show'])->name('vendor.sales.show');
    Route::post('/sales/{sale}/void', [\App\Http\Controllers\Vendor\SalesController::class, 'void'])->name('vendor.sales.void');
    Route::post('/sales/{sale}/refund', [\App\Http\Controllers\Vendor\SalesController::class, 'refund'])->name('vendor.sales.refund');

    // What the POS screen asks for while a sale is being built. Open to the
    // owner and to staff who may sell — PosSupportController::till() checks.
    Route::get('/pos-support/customers', [\App\Http\Controllers\Vendor\PosSupportController::class, 'customers'])->name('vendor.pos.customers');
    Route::get('/pos-support/held', [\App\Http\Controllers\Vendor\PosSupportController::class, 'held'])->name('vendor.pos.held');
    Route::post('/pos-support/held', [\App\Http\Controllers\Vendor\PosSupportController::class, 'hold'])->name('vendor.pos.held.store');
    Route::delete('/pos-support/held/{held}', [\App\Http\Controllers\Vendor\PosSupportController::class, 'release'])->name('vendor.pos.held.release');
    Route::get('/pos-support/recent', [\App\Http\Controllers\Vendor\PosSupportController::class, 'recent'])->name('vendor.pos.recent');
    Route::post('/pos-support/sales/{sale}/reprint', [\App\Http\Controllers\Vendor\PosSupportController::class, 'reprint'])->name('vendor.pos.reprint');


    // Dispatch is reachable by the owner plus staff with can_manage_orders
    // (see StaffRoles) — DispatchController::index() enforces that itself,
    // which is why this isn't behind the vendor.only middleware.
    Route::get('/dispatch', [\App\Http\Controllers\Vendor\DispatchController::class, 'index'])->name('vendor.dispatch');

    // Set from the dashboard by the owner or the branch's manager, which is
    // why it is not behind vendor.only; BranchController checks who may.
    Route::post('/branches/{branch}/location', [\App\Http\Controllers\Vendor\BranchController::class, 'setLocation'])->name('vendor.branches.location');
    Route::post('/dispatch/{fulfillment}/handover', [\App\Http\Controllers\Vendor\DispatchController::class, 'handover'])->name('vendor.dispatch.handover');

    // Onboarding (Direct Closure to bypass autoloading issues on cPanel)
    Route::get('/onboarding', function() {
        $user = auth()->user();
        if ($user && $user->role !== 'vendor') {
            $user->role = 'vendor';
            $user->save();
        }

        if (\App\Models\Pharmacy::where('user_id', auth()->id())->exists()) {
            return redirect()->route('vendor.dashboard');
        }
        return view('vendor-dashboard.onboarding');
    })->name('vendor.onboarding');

    Route::post('/onboarding', function(\Illuminate\Http\Request $request) {
        $user = auth()->user();
        if ($user && $user->role !== 'vendor') {
            $user->role = 'vendor';
            $user->save();
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ], [
        ]);

        $pharmacy = \App\Models\Pharmacy::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'type' => 'pharmacy',
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'phone' => $request->phone,
            'email' => $request->email,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        // The contact details belong to the business as a whole.
        $pharmacy->organization?->update([
            'phone' => $request->phone,
            'email' => $request->email,
        ]);

        \App\Support\Audit::account('account.pharmacy_registered', "{$pharmacy->name} was registered on CuraD", $user);

        return redirect()->route('vendor.dashboard')->with('success', 'Profile created successfully!');
    })->name('vendor.onboarding.store');
});

// Address lookups for the pharmacy and branch forms (map position <-> street address).
Route::get('/location/lookup', [\App\Http\Controllers\LocationController::class, 'lookup'])->middleware('throttle:40,1')->name('location.lookup');

// Address suggestions while typing. One person may ask 60 times a minute.
Route::get('/location/suggest', [\App\Http\Controllers\LocationController::class, 'suggest'])->middleware('throttle:60,1')->name('location.suggest');

// API Routes for Real-time Chat
Route::middleware('auth')->prefix('api')->group(function () {
    Route::get('/chat/{conversation}/messages', [\App\Http\Controllers\Api\ChatApiController::class, 'getMessages'])->name('api.chat.messages');
    Route::get('/chat/unread-count', [\App\Http\Controllers\Api\ChatApiController::class, 'getUnreadCount'])->name('api.chat.unread');
    Route::post('/chat/{conversation}/mark-read', [\App\Http\Controllers\Api\ChatApiController::class, 'markAsRead'])->name('api.chat.mark-read');
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\ChatApiController::class, 'getUnreadNotificationsCount'])->name('api.notifications.unread');
});
