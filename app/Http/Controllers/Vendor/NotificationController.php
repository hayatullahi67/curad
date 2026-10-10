<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The pharmacy owner's notification inbox: new orders, cancellations, deliveries, stock warnings. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $show = $request->query('show') === 'unread' ? 'unread' : 'all';
        $mine = fn () => Notification::audience('pharmacy')->where('user_id', Auth::id());

        return view('vendor-dashboard.notifications', [
            'notifications' => $mine()
                ->when($show === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->latest()
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'show' => $show,
            'counts' => ['all' => $mine()->count(), 'unread' => $mine()->whereNull('read_at')->count()],
        ]);
    }

    /** Marks everything as read. */
    public function markAsRead()
    {
        Notification::audience('pharmacy')->where('user_id', Auth::id())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /** Marks one as read, or back to unread. */
    public function toggle($id)
    {
        $notification = Notification::audience('pharmacy')->where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['read_at' => $notification->read_at ? null : now()]);

        return back();
    }

    public function destroy($id)
    {
        Notification::audience('pharmacy')->where('user_id', Auth::id())->where('id', $id)->delete();

        return back()->with('success', 'Notification deleted.');
    }

    /** Deletes every notification that has already been read. */
    public function clearRead()
    {
        $deleted = Notification::audience('pharmacy')->where('user_id', Auth::id())->whereNotNull('read_at')->delete();

        return back()->with('success', $deleted ? "{$deleted} read " . ($deleted === 1 ? 'notification' : 'notifications') . ' deleted.' : 'There was nothing to clear.');
    }
}
