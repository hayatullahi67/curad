<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The customer's wallet: the balance, and every payment and refund that went through it. */
class HistoryController extends Controller
{
    private const FILTERS = ['all' => 'Everything', 'debit' => 'Money out', 'credit' => 'Money in'];

    public function index(Request $request)
    {
        $user = Auth::user();
        $filter = array_key_exists((string) $request->query('show'), self::FILTERS) ? $request->query('show') : 'all';
        $mine = fn () => WalletTransaction::where('user_id', $user->id);
        $monthStart = now()->startOfMonth();

        return view('user-dashboard.history', [
            'user' => $user,
            'filters' => self::FILTERS,
            'filter' => $filter,
            'transactions' => $mine()->when($filter !== 'all', fn ($query) => $query->where('type', $filter))->latest('id')->paginate(20)->withQueryString(),
            'spentThisMonth' => (float) $mine()->where('type', 'debit')->where('created_at', '>=', $monthStart)->sum('amount'),
            'receivedThisMonth' => (float) $mine()->where('type', 'credit')->where('created_at', '>=', $monthStart)->sum('amount'),
            'hasPin' => (bool) $user->transaction_pin,
        ]);
    }
}
