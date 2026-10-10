<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderFulfillment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Messages between a customer and a pharmacy. A customer has one thread per
 * pharmacy, opened from an order that pharmacy is supplying.
 */
class ChatController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $conversations = Conversation::where('user_id', $user->id)
            ->with(['pharmacy', 'latestMessage', 'order'])
            ->withCount(['messages as unread_count' => fn ($query) => $query->where('from_pharmacy', true)->where('is_read', false)])
            ->latest('updated_at')
            ->get();

        return view('user-dashboard.chat', compact('conversations'));
    }

    public function show(Conversation $conversation)
    {
        abort_unless((int) $conversation->user_id === (int) Auth::id(), 403);

        $conversation->load(['pharmacy', 'order']);
        $messages = $conversation->messages()->oldest('id')->get();

        // Opening the thread reads what the pharmacy wrote.
        $conversation->markReadBy('customer');

        return view('user-dashboard.chat-show', compact('conversation', 'messages'));
    }

    public function store(Request $request, Conversation $conversation)
    {
        abort_unless((int) $conversation->user_id === (int) Auth::id(), 403);

        $data = $request->validate(['message' => ['required', 'string', 'max:1000']], ['message.required' => 'Type a message first.']);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => Auth::id(),
            'from_pharmacy' => false,
            'message' => trim($data['message']),
            'is_read' => false,
        ]);

        $conversation->touch();

        if ($request->expectsJson()) {
            return response()->json(['id' => $message->id]);
        }

        return redirect()->route('user.chat.show', $conversation);
    }

    /** Opens the thread with a pharmacy that is supplying one of the customer's orders. */
    public function create(Request $request)
    {
        $data = $request->validate(['fulfillment_id' => ['required', 'integer']]);

        $fulfillment = OrderFulfillment::with('order')->findOrFail($data['fulfillment_id']);
        abort_unless((int) $fulfillment->order?->user_id === (int) Auth::id(), 403);

        $conversation = Conversation::firstOrCreate(
            ['user_id' => Auth::id(), 'pharmacy_id' => $fulfillment->pharmacy_id],
            ['order_id' => $fulfillment->order_id],
        );

        // The thread is about whichever order the customer came from last.
        if ((int) $conversation->order_id !== (int) $fulfillment->order_id) {
            $conversation->update(['order_id' => $fulfillment->order_id]);
        }

        return redirect()->route('user.chat.show', $conversation);
    }
}
