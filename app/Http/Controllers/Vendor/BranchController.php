<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InventoryBatch;
use App\Models\Medication;
use App\Models\OrderFulfillment;
use App\Models\Organization;
use App\Models\Pharmacy;
use App\Models\PosSale;
use App\Models\StockTransfer;
use App\Models\User;
use App\Support\Audit;
use App\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The owner's view of the whole organization: every branch side by side,
 * and one branch in detail. Only the owner reaches these pages; staff work
 * inside their own branch and never see another one.
 */
class BranchController extends Controller
{
    /** All branches, with the numbers added up across the organization. */
    public function index()
    {
        $owner = Auth::user();
        $organization = $this->organization($owner);

        if (! $organization) {
            return redirect()->route('vendor.onboarding');
        }

        $branches = Pharmacy::branchesOf($owner)->get();
        $ids = $branches->pluck('id');
        $monthStart = now()->startOfMonth();

        // One query per measure, grouped by branch, rather than one per branch.
        $posToday = $this->posTotals($ids, today(), null);
        $posMonth = $this->posTotals($ids, $monthStart, null);
        $onlineToday = $this->onlineTotals($ids, today());
        $onlineMonth = $this->onlineTotals($ids, $monthStart);
        $openOrders = OrderFulfillment::whereIn('pharmacy_id', $ids)->whereIn('status', ['awaiting_confirmation', 'confirmed', 'ready'])
            ->selectRaw('pharmacy_id, COUNT(*) as total')->groupBy('pharmacy_id')->pluck('total', 'pharmacy_id');
        $stock = Medication::whereIn('pharmacy_id', $ids)
            ->selectRaw('pharmacy_id, COUNT(*) as products, COALESCE(SUM(stock_level * COALESCE(cost_price, 0)), 0) as value,
                SUM(CASE WHEN stock_level <= low_stock_threshold THEN 1 ELSE 0 END) as low, COALESCE(SUM(expired_stock), 0) as expired')
            ->groupBy('pharmacy_id')->get()->keyBy('pharmacy_id');
        $staff = User::where('cura_role', 'sales_person')->where('is_active', 1)->whereIn('assigned_pharmacy_id', $ids)
            ->selectRaw('assigned_pharmacy_id, COUNT(*) as total')->groupBy('assigned_pharmacy_id')->pluck('total', 'assigned_pharmacy_id');

        $rows = $branches->map(fn (Pharmacy $branch) => (object) [
            'branch' => $branch,
            'sales_today' => (float) ($posToday[$branch->id]->amount ?? 0) + (float) ($onlineToday[$branch->id] ?? 0),
            'sales_month' => (float) ($posMonth[$branch->id]->amount ?? 0) + (float) ($onlineMonth[$branch->id] ?? 0),
            'sale_count_today' => (int) ($posToday[$branch->id]->count ?? 0),
            'open_orders' => (int) ($openOrders[$branch->id] ?? 0),
            'products' => (int) ($stock[$branch->id]->products ?? 0),
            'stock_value' => (float) ($stock[$branch->id]->value ?? 0),
            'low_stock' => (int) ($stock[$branch->id]->low ?? 0),
            'expired' => (int) ($stock[$branch->id]->expired ?? 0),
            'staff' => (int) ($staff[$branch->id] ?? 0),
        ]);

        return view('vendor-dashboard.branches', [
            'organization' => $organization,
            'rows' => $rows,
            'current' => Pharmacy::current(),
            'totals' => [
                'sales_today' => $rows->sum('sales_today'),
                'sales_month' => $rows->sum('sales_month'),
                'open_orders' => $rows->sum('open_orders'),
                'stock_value' => $rows->sum('stock_value'),
                'low_stock' => $rows->sum('low_stock'),
                'staff' => $rows->sum('staff'),
            ],
            'bestMonth' => max(1, $rows->max('sales_month')),
            'transfersOnTheWay' => StockTransfer::where('organization_id', $organization->id)->where('status', 'in_transit')->count(),
        ]);
    }

    /** One branch: how it is doing, who is there, and what has been happening. */
    public function show(Pharmacy $branch)
    {
        $owner = Auth::user();
        abort_unless($branch->isOpenTo($owner), 404);

        $id = $branch->id;
        $pos = fn () => PosSale::where('pharmacy_id', $id)->where('status', '!=', PosSale::STATUS_VOIDED);
        $online = fn () => OrderFulfillment::where('pharmacy_id', $id)->where('status', 'completed');
        $period = fn ($from) => [
            'amount' => (float) $pos()->where('created_at', '>=', $from)->sum(DB::raw('total_amount - refunded_amount')) + (float) $online()->where('completed_at', '>=', $from)->sum('subtotal'),
            'count' => $pos()->where('created_at', '>=', $from)->count() + $online()->where('completed_at', '>=', $from)->count(),
        ];

        // Sales for each of the last 14 days, till and online together.
        $from = today()->subDays(13);
        $posDays = $pos()->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as day, SUM(total_amount - refunded_amount) as amount')->groupBy('day')->pluck('amount', 'day');
        $onlineDays = $online()->where('completed_at', '>=', $from)->selectRaw('DATE(completed_at) as day, SUM(subtotal) as amount')->groupBy('day')->pluck('amount', 'day');
        $days = collect(range(0, 13))->map(function ($offset) use ($from, $posDays, $onlineDays) {
            $day = $from->copy()->addDays($offset);
            $key = $day->toDateString();

            return (object) ['date' => $day, 'till' => (float) ($posDays[$key] ?? 0), 'online' => (float) ($onlineDays[$key] ?? 0)];
        });

        $stock = Medication::where('pharmacy_id', $id)
            ->selectRaw('COUNT(*) as products, COALESCE(SUM(stock_level), 0) as units, COALESCE(SUM(stock_level * COALESCE(cost_price, 0)), 0) as value,
                SUM(CASE WHEN stock_level <= low_stock_threshold THEN 1 ELSE 0 END) as low, COALESCE(SUM(expired_stock), 0) as expired')
            ->first();

        $staff = User::where('cura_role', 'sales_person')->where('assigned_pharmacy_id', $id)->orderByDesc('is_active')->orderBy('full_name')->get();

        return view('vendor-dashboard.branch-show', [
            'branch' => $branch->load('organization'),
            'isCurrent' => Pharmacy::current()?->id === $id,
            'branchCount' => Pharmacy::branchesOf($owner)->count(),
            'today' => $period(today()),
            'week' => $period(today()->subDays(6)),
            'month' => $period(now()->startOfMonth()),
            'days' => $days,
            'peak' => max(1, $days->max(fn ($day) => $day->till + $day->online)),
            'stock' => $stock,
            'staff' => $staff,
            'orders' => OrderFulfillment::where('pharmacy_id', $id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'lowStock' => Medication::where('pharmacy_id', $id)->whereColumn('stock_level', '<=', 'low_stock_threshold')->orderBy('stock_level')->limit(6)->get(['id', 'name', 'dosage', 'stock_level', 'low_stock_threshold']),
            'expiring' => InventoryBatch::where('pharmacy_id', $id)->where('quantity', '>', 0)->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', today()->addDays(90))->with('medication:id,name,dosage')->orderBy('expiry_date')->limit(6)->get(),
            'topProducts' => DB::table('pos_sale_items as i')->join('pos_sales as s', 's.id', '=', 'i.pos_sale_id')
                ->where('s.pharmacy_id', $id)->where('s.status', '!=', PosSale::STATUS_VOIDED)->where('s.created_at', '>=', today()->subDays(29))
                ->selectRaw('i.product_name, SUM(i.quantity - i.returned_quantity) as units, SUM(i.line_total) as amount')
                ->groupBy('i.product_name')->orderByDesc('amount')->limit(5)->get(),
            'transfers' => StockTransfer::where(fn ($query) => $query->where('from_pharmacy_id', $id)->orWhere('to_pharmacy_id', $id))
                ->with(['from:id,name', 'to:id,name'])->withSum('items', 'quantity')->latest('id')->limit(5)->get(),
            'activity' => AuditLog::where('pharmacy_id', $id)->with('user:id,full_name')->latest()->latest('id')->limit(14)->get(),
            'roleLabel' => fn (?string $role) => StaffRoles::label($role),
        ]);
    }

    public function store(Request $request)
    {
        $owner = Auth::user();
        $organization = $this->organization($owner);
        abort_unless($organization, 403);

        $data = $this->validated($request);
        $main = Pharmacy::branchesOf($owner)->first();

        $branch = Pharmacy::create($data + [
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'type' => 'pharmacy',
            'email' => $main->email ?? $owner->email,
            'is_active' => true,
        ]);

        Audit::log('branch.created', "Opened branch {$branch->name}", $branch->id, $branch);

        return redirect()->route('vendor.branches.show', $branch)->with('success', "{$branch->name} is ready. Add its staff, then send or receive its first stock.");
    }

    public function update(Request $request, Pharmacy $branch)
    {
        abort_unless($branch->isOpenTo(Auth::user()), 404);

        $branch->update($this->validated($request, $branch));
        Audit::log('branch.updated', "Updated the details of {$branch->name}", $branch->id, $branch);

        return back()->with('success', 'Branch details saved.');
    }

    /**
     * Sets where the branch is on the map, from the dashboard. The owner
     * may do it for any of their branches, a Branch Manager for their own.
     */
    public function setLocation(Request $request, Pharmacy $branch)
    {
        $user = Auth::user();
        abort_unless($branch->isOpenTo($user), 404);
        abort_unless($user->role === 'vendor' || $user->staff_role === 'branch_manager', 403);

        $data = $request->validateWithBag('location', [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
        ], [
            'latitude.required' => 'Put the pin on the map first: press “Use my location” or tap the map.',
            'longitude.required' => 'Put the pin on the map first: press “Use my location” or tap the map.',
            'address.required' => 'Enter the street address of the branch.',
        ]);

        $hadPin = filled($branch->latitude) && filled($branch->longitude);

        $branch->update([
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'address' => trim($data['address']),
            'city' => filled($data['city'] ?? null) ? trim($data['city']) : null,
            'state' => filled($data['state'] ?? null) ? trim($data['state']) : null,
        ]);

        Audit::log(
            'branch.location_set',
            ($user->full_name ?: $user->email) . ($hadPin ? ' moved the map location of ' : ' set the map location of ') . $branch->name,
            $branch->id,
            $branch,
            ['latitude' => (float) $data['latitude'], 'longitude' => (float) $data['longitude']],
        );

        return back()->with('success', "{$branch->name} is now on the map. Customers can find it and get directions.");
    }

    /** Takes a branch off the marketplace, or puts it back. Its POS keeps working either way. */
    public function toggle(Pharmacy $branch)
    {
        abort_unless($branch->isOpenTo(Auth::user()), 404);

        $branch->update(['is_active' => ! $branch->is_active]);
        Audit::log('branch.' . ($branch->is_active ? 'opened_online' : 'closed_online'), $branch->is_active ? "{$branch->name} is taking online orders again" : "{$branch->name} stopped taking online orders", $branch->id, $branch);

        return back()->with('success', $branch->is_active ? "{$branch->name} is back on the marketplace." : "{$branch->name} no longer takes online orders. Its till still works.");
    }

    /** The owner chooses which branch the till, stock and reports show. */
    public function switch(Request $request, Pharmacy $branch)
    {
        abort_unless($branch->isOpenTo(Auth::user()), 404);

        session(['branch_id' => $branch->id]);

        $to = (string) $request->input('to');

        return ($to !== '' && str_starts_with($to, url('/')) ? redirect($to) : redirect()->route('vendor.dashboard'))
            ->with('success', "You are now working in {$branch->name}.");
    }

    private function organization(User $owner): ?Organization
    {
        return Organization::where('owner_id', $owner->id)->first();
    }

    private function validated(Request $request, ?Pharmacy $branch = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'operating_hours' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ], [
            'name.required' => 'Give the branch a name, for example the area it is in.',
            'address.required' => 'Enter the street address of the branch.',
            'phone.required' => 'Enter a phone number customers can call.',
        ]);

        return array_map(fn ($value) => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value, $data);
    }

    private function posTotals($ids, $from, $to)
    {
        return PosSale::whereIn('pharmacy_id', $ids)->where('status', '!=', PosSale::STATUS_VOIDED)->where('created_at', '>=', $from)
            ->selectRaw('pharmacy_id, COUNT(*) as count, COALESCE(SUM(total_amount - refunded_amount), 0) as amount')
            ->groupBy('pharmacy_id')->get()->keyBy('pharmacy_id');
    }

    private function onlineTotals($ids, $from)
    {
        return OrderFulfillment::whereIn('pharmacy_id', $ids)->where('status', 'completed')->where('completed_at', '>=', $from)
            ->selectRaw('pharmacy_id, COALESCE(SUM(subtotal), 0) as amount')->groupBy('pharmacy_id')->pluck('amount', 'pharmacy_id');
    }
}
