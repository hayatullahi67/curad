<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use App\Models\Broadcast;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BroadcastController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (!$pharmacy) {
            return redirect()->route('vendor.dashboard')->with('error', 'Pharmacy record not found.');
        }

        $broadcasts = Broadcast::where('pharmacy_id', $pharmacy->id)->latest()->get();
        $followerCount = $pharmacy->followers()->count();

        return view('vendor-dashboard.broadcast', compact('broadcasts', 'followerCount', 'pharmacy'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:general,stock,emergency'
        ]);

        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (!$pharmacy) {
            return back()->with('error', 'Pharmacy record not found.');
        }

        // 1. Determine Target Users (Followers for now)
        $followers = $pharmacy->followers()->get();
        $recipientsCount = $followers->count();

        // 2. Create Broadcast Record (even if no followers, for audit trail)
        $broadcast = Broadcast::create([
            'pharmacy_id' => $pharmacy->id,
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type,
            'target_audience' => 'followers',
            'recipients_count' => $recipientsCount
        ]);

        // 3. Dispatch Notifications to followers (only if they exist)
        if ($recipientsCount > 0) {
            foreach ($followers as $follower) {
                Notification::create([
                    'user_id' => $follower->id,
                    'type' => $request->type,
                    'title' => "Broadcast from {$pharmacy->name}: {$request->title}",
                    'message' => $request->message
                ]);
            }
            return back()->with('success', "Broadcast successfully dispatched to $recipientsCount recipients!");
        }

        return back()->with('success', "Broadcast created! Note: You currently have no followers to receive this message.");
    }
}
