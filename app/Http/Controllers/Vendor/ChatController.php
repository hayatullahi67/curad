<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\BranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Messages, seen from the pharmacy side. Staff who handle online orders see
 * the conversations of the branch they work in. The owner sees the
 * conversations of every branch, can narrow the list to one branch, and can
 * read and answer any of them.
 */
class ChatController extends Controller
{
    private const FILTERS = ['all' => 'All', 'unread' => 'Unread'];

    public function index(Request $request)
    {
        return $this->inbox($request, null);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->allow($conversation);

        return $this->inbox($request, $conversation);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $this->allow($conversation);

        $data = $request->validate(['message' => ['required', 'string', 'max:1000']], ['message.required' => 'Type a message first.']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => Auth::id(),
            'from_pharmacy' => true,
            'message' => trim($data['message']),
            'is_read' => false,
        ]);

        $conversation->touch();

        if ($request->expectsJson()) {
            return response()->json(['id' => $message->id]);
        }

        return redirect()->route('vendor.chat.show', [$conversation] + $request->only('branch'));
    }

    /** The list of conversations, with one of them open beside it when $open is given. */
    private function inbox(Request $request, ?Conversation $open)
    {
        $user = Auth::user();
        abort_unless(Conversation::pharmacySideAllows($user), 403, 'Your role does not use Messages.');

        $scope = BranchScope::for($user, $request);
        $filter = array_key_exists((string) $request->query('show'), self::FILTERS) ? $request->query('show') : 'all';
        $search = trim((string) $request->query('q'));

        // A message is waiting when the customer wrote it and nobody at the pharmacy has read it.
        $waiting = fn ($messages) => $messages->where('is_read', false)->where('from_pharmacy', false);
        $mine = fn (?array $ids = null) => Conversation::whereIn('pharmacy_id', $ids ?? $scope->ids());

        if ($open) {
            $open->markReadBy('pharmacy');
            $open->load(['user', 'pharmacy', 'order']);
        }

        $conversations = $mine()
            ->when($filter === 'unread', fn ($query) => $query->whereHas('messages', $waiting))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->whereHas('user', fn ($customer) => $customer->where('full_name', 'like', $like)->orWhere('phone', 'like', $like))
                        ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', $like));
                });
            })
            ->with(['user', 'pharmacy', 'order', 'latestMessage'])
            ->withCount(['messages as unread_count' => $waiting])
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return view('vendor-dashboard.chat', [
            'scope' => $scope,
            'conversations' => $conversations,
            'open' => $open,
            'messages' => $open ? $open->messages()->with('sender')->oldest('id')->get() : collect(),
            'filters' => self::FILTERS,
            'filter' => $filter,
            'search' => $search,
            'unreadTotal' => $mine()->whereHas('messages', $waiting)->count(),
            // Conversations with something unread in each branch, for the numbers on the branch filter.
            'waitingByBranch' => $mine($scope->branches->pluck('id')->all())->whereHas('messages', $waiting)
                ->selectRaw('pharmacy_id, COUNT(*) as total')->groupBy('pharmacy_id')->pluck('total', 'pharmacy_id'),
            // What to keep in the address when moving around the page.
            'keep' => array_filter(['branch' => $scope->param(), 'show' => $filter === 'all' ? null : $filter, 'q' => $search ?: null]),
        ]);
    }

    /** Another business's conversation, or for staff another branch's, looks like it does not exist. */
    private function allow(Conversation $conversation): void
    {
        abort_unless($conversation->isPharmacySide(Auth::user()), 404);
    }
}
