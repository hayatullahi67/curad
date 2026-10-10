<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Support\BranchScope;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** What the message pages ask for while they are open: new messages and unread counts. */
class ChatApiController extends Controller
{
    /** The messages of one thread, for either side of it. Fetching them reads them. */
    public function getMessages(Request $request, Conversation $conversation)
    {
        $conversation->markReadBy($this->side($request, $conversation));

        return response()->json([
            'messages' => $conversation->messages()->with('sender')->oldest('id')->get()->map(function (Message $message) use ($conversation) {
                $fromCustomer = ! $message->from_pharmacy;

                return [
                    'id' => $message->id,
                    'sender_id' => $message->sender_id,
                    'is_mine' => (int) $message->sender_id === (int) Auth::id(),
                    'from_customer' => $fromCustomer,
                    // Who at the pharmacy answered, shown to the pharmacy side only.
                    'sender_name' => $fromCustomer ? null : ($message->sender?->full_name ?? 'Pharmacy'),
                    'sender_role' => $fromCustomer ? null : ($message->sender?->role === 'vendor' ? 'Owner' : StaffRoles::label($message->sender?->staff_role)),
                    'message' => $message->message,
                    'is_read' => (bool) $message->is_read,
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Unread messages waiting for this person: as a customer, what pharmacies
     * wrote; on the pharmacy side, what customers wrote to the branches this
     * person may see (every branch for the owner, their own for staff).
     */
    public function getUnreadCount(Request $request)
    {
        $user = Auth::user();

        if (in_array($user->role, ['vendor', 'sales_person'], true)) {
            $count = Conversation::pharmacySideAllows($user)
                ? Message::where('is_read', false)
                    ->whereHas('conversation', fn ($conversation) => $conversation
                        ->whereIn('pharmacy_id', BranchScope::for($user, $request)->branches->pluck('id')))
                    ->where('from_pharmacy', false)
                    ->count()
                : 0;
        } else {
            $count = Message::where('is_read', false)
                ->where('from_pharmacy', true)
                ->whereHas('conversation', fn ($conversation) => $conversation->where('user_id', $user->id))
                ->count();
        }

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, Conversation $conversation)
    {
        $conversation->markReadBy($this->side($request, $conversation));

        return response()->json(['success' => true]);
    }

    public function getUnreadNotificationsCount()
    {
        return response()->json([
            'count' => Notification::audience(Auth::user()->role === 'vendor' ? 'pharmacy' : 'customer')
                ->where('user_id', Auth::id())->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Which side of the thread is asking: 'customer' or 'pharmacy'. Only the
     * two sides may read it. The customer's page says so (?as=customer),
     * which matters for an owner who is the customer of their own branch.
     */
    protected function side(Request $request, Conversation $conversation): string
    {
        $user = Auth::user();

        if ($conversation->isCustomer($user) && ($request->query('as') === 'customer' || ! $conversation->isPharmacySide($user))) {
            return 'customer';
        }

        abort_unless($conversation->isPharmacySide($user), 403);

        return 'pharmacy';
    }
}
