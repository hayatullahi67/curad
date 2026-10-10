<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Activity Log: who did what in this facility, newest first.
 */
class AuditLogController extends Controller
{
    /** Actions the owner should look at twice. */
    public const SENSITIVE = [
        'sale.voided', 'sale.refunded', 'product.deleted', 'staff.login_as', 'staff.password_reset',
        'account.sign_in_failed', 'account.sign_in_blocked', 'account.locked', 'account.password_reset',
        'account.password_changed', 'order.rejected',
    ];

    public function index(Request $request)
    {
        $pharmacy = Pharmacy::current();
        abort_unless($pharmacy, 403, 'Please set up your facility profile first.');

        $filters = [
            'group' => array_key_exists((string) $request->query('group'), AuditLog::GROUPS) ? $request->query('group') : null,
            'user' => $request->query('user'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'flagged' => $request->boolean('flagged') ? 1 : null,
        ];

        // Everything filed under this pharmacy, plus the owner's own account
        // events from before the pharmacy existed (creating the account).
        $ownerId = Auth::id();
        $mine = fn () => AuditLog::where(function ($query) use ($pharmacy, $ownerId) {
            $query->where('pharmacy_id', $pharmacy->id)
                ->orWhere(fn ($early) => $early->whereNull('pharmacy_id')->where('user_id', $ownerId)->where('action', 'like', 'account.%'));
        });

        $query = $mine();

        if ($filters['group']) {
            $query->where('action', 'like', $filters['group'] . '.%');
        }
        if ($filters['user']) {
            $query->where('user_id', (int) $filters['user']);
        }
        if ($filters['from'] && strtotime($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] && strtotime($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if ($filters['flagged']) {
            $query->whereIn('action', self::SENSITIVE);
        }

        $logs = $query->with('user')->latest()->latest('id')->paginate(40)->withQueryString();

        $people = User::whereIn('id', $mine()->distinct()->pluck('user_id')->filter())
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        // Counted without the filters, so the numbers stay put while filtering.
        $weekAgo = now()->subDays(6)->startOfDay();
        $stats = [
            'today' => $mine()->whereDate('created_at', today())->count(),
            'sign_ins_today' => $mine()->where('action', 'account.signed_in')->whereDate('created_at', today())->count(),
            'people_today' => $mine()->where('action', 'account.signed_in')->whereDate('created_at', today())->distinct()->count('user_id'),
            'failed_week' => $mine()->whereIn('action', ['account.sign_in_failed', 'account.sign_in_blocked', 'account.locked'])->where('created_at', '>=', $weekAgo)->count(),
            'flagged_week' => $mine()->whereIn('action', self::SENSITIVE)->where('created_at', '>=', $weekAgo)->count(),
        ];

        $groupCounts = $mine()
            ->selectRaw("SUBSTRING_INDEX(action, '.', 1) as grp, COUNT(*) as total")
            ->groupBy('grp')
            ->pluck('total', 'grp');

        $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();

        return view('vendor-dashboard.activity-log', [
            'pharmacy' => $pharmacy,
            'logs' => $logs,
            'filters' => $filters,
            'people' => $people,
            'hasFilters' => $hasFilters,
            'stats' => $stats,
            'groupCounts' => $groupCounts,
            'sensitive' => self::SENSITIVE,
        ]);
    }
}
