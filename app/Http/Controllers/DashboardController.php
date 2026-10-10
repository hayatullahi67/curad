<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The customer's side of the dashboard: the overview, notifications and what they want to be told about. */
class DashboardController extends Controller
{
    private const OPEN = ['completed', 'partially_fulfilled', 'cancelled'];

    /** What a customer can choose to be notified about, with the words shown beside each switch. */
    public const PREFERENCES = [
        'system' => ['Order updates', 'When a pharmacy confirms your order, when it is ready, and when it is on its way.', 'fa-box'],
        'stock' => ['Back in stock', 'When a product on your watchlist is available again.', 'fa-bookmark'],
        'finance' => ['Payments and refunds', 'When money leaves or returns to your wallet.', 'fa-wallet'],
        'security' => ['Account and security', 'When your password or sign-in details change.', 'fa-shield-halved'],
    ];

    public function index(MarketplaceController $marketplace)
    {
        $user = Auth::user();
        $orders = fn () => Order::where('user_id', $user->id);

        return view('user-dashboard.dashboard', [
            'user' => $user,
            'activeOrders' => $orders()->whereNotIn('status', self::OPEN)->with(['items', 'fulfillments.pharmacy'])->latest('id')->limit(3)->get(),
            'counts' => [
                'active' => $orders()->whereNotIn('status', self::OPEN)->count(),
                'done' => $orders()->whereIn('status', ['completed', 'partially_fulfilled'])->count(),
                'watchlist' => Watchlist::where('user_id', $user->id)->count(),
                'saved' => $user->savedPharmacies()->count(),
                'notifications' => Notification::audience('customer')->where('user_id', $user->id)->whereNull('read_at')->count(),
                'messages' => Message::whereIn('conversation_id', Conversation::where('user_id', $user->id)->select('id'))
                    ->where('from_pharmacy', true)->where('is_read', false)->count(),
            ],
            'notifications' => Notification::audience('customer')->where('user_id', $user->id)->latest()->limit(4)->get(),
            'fresh' => $marketplace->fresh(4),
            'hasLocation' => (bool) session('shopper_location'),
        ]);
    }

    public function notifications(Request $request)
    {
        $unreadOnly = $request->query('show') === 'unread';
        $mine = fn () => Notification::audience('customer')->where('user_id', Auth::id());

        return view('user-dashboard.notifications', [
            'notifications' => $mine()->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))->latest()->paginate(20)->withQueryString(),
            'unreadOnly' => $unreadOnly,
            'unreadCount' => $mine()->whereNull('read_at')->count(),
            'totalCount' => $mine()->count(),
        ]);
    }

    public function markNotificationsRead()
    {
        Notification::audience('customer')->where('user_id', Auth::id())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function deleteNotification($id)
    {
        Notification::audience('customer')->where('user_id', Auth::id())->where('id', $id)->delete();

        return back()->with('success', 'Notification removed.');
    }

    public function showPreferences()
    {
        $saved = (array) (Auth::user()->notification_settings ?? []);

        return view('user-dashboard.preferences', [
            'options' => self::PREFERENCES,
            // Everything is on until the customer switches it off.
            'settings' => collect(self::PREFERENCES)->map(fn ($option, $key) => (bool) ($saved[$key] ?? true))->all(),
        ]);
    }

    public function savePreferences(Request $request)
    {
        $user = Auth::user();
        $user->notification_settings = collect(self::PREFERENCES)->map(fn ($option, $key) => $request->has($key))->all();
        $user->save();

        return back()->with('success', 'Your notification choices were saved.');
    }
}
