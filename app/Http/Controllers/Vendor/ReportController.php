<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Medication;
use App\Models\Pharmacy;
use App\Models\PosPayment;
use App\Models\PosSale;
use App\Models\OrderFulfillment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Quick date ranges offered at the top of the page. */
    public const PERIODS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        'month' => 'This month',
    ];

    public function index(Request $request)
    {
        $user = Auth::user();
        $pharmacy = Pharmacy::current();

        if (!$pharmacy) {
            return redirect()->route('vendor.dashboard')->with('error', 'Pharmacy record not found.');
        }

        [$period, $from, $to, $periodLabel] = $this->resolvePeriod($request);

        // "Sales" below always means sales that still stand: voided sales
        // are left out and refunds are taken off.
        $sales = fn () => PosSale::where('pharmacy_id', $pharmacy->id)->whereBetween('created_at', [$from, $to]);
        $standing = fn () => $sales()->where('status', '!=', PosSale::STATUS_VOIDED);

        $totals = $standing()->selectRaw(
            'COUNT(*) as sale_count,
             COALESCE(SUM(total_amount - refunded_amount), 0) as net_sales,
             COALESCE(SUM(discount_amount), 0) as discounts,
             COALESCE(SUM(refunded_amount), 0) as refunds'
        )->first();

        $voided = $sales()->where('status', PosSale::STATUS_VOIDED)
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total_amount), 0) as amount')
            ->first();

        // Same-length window immediately before, for the "vs previous" figure.
        $windowSeconds = $from->diffInSeconds($to) + 1;
        $previousNet = (float) PosSale::where('pharmacy_id', $pharmacy->id)
            ->where('status', '!=', PosSale::STATUS_VOIDED)
            ->whereBetween('created_at', [$from->copy()->subSeconds($windowSeconds), $from->copy()->subSecond()])
            ->sum(DB::raw('total_amount - refunded_amount'));
        $changePercent = $previousNet > 0 ? round(($totals->net_sales - $previousNet) / $previousNet * 100, 1) : null;

        // Line-level figures. "kept" = units sold and not returned; revenue
        // per unit is the line price less its share of the sale discount.
        $lines = fn () => DB::table('pos_sale_items as i')
            ->join('pos_sales as s', 's.id', '=', 'i.pos_sale_id')
            ->where('s.pharmacy_id', $pharmacy->id)
            ->where('s.status', '!=', PosSale::STATUS_VOIDED)
            ->whereBetween('s.created_at', [$from, $to]);

        $keptUnits = '(i.quantity - i.returned_quantity)';
        $unitRevenue = '((i.line_total - i.discount_amount) / i.quantity)';

        $lineTotals = $lines()->selectRaw(
            "COALESCE(SUM({$keptUnits}), 0) as units,
             COALESCE(SUM(CASE WHEN i.unit_cost IS NOT NULL THEN {$keptUnits} * ({$unitRevenue} - i.unit_cost) ELSE 0 END), 0) as profit,
             COALESCE(SUM(CASE WHEN i.unit_cost IS NULL THEN 1 ELSE 0 END), 0) as lines_without_cost,
             COUNT(*) as line_count"
        )->first();

        $topProducts = $lines()
            ->selectRaw("i.medication_id, i.product_name, SUM({$keptUnits}) as units, SUM({$keptUnits} * {$unitRevenue}) as revenue")
            ->groupBy('i.medication_id', 'i.product_name')
            ->orderByDesc('units')
            ->limit(8)
            ->get();

        $categories = $lines()
            ->leftJoin('medications as m', 'm.id', '=', 'i.medication_id')
            ->selectRaw("COALESCE(m.category, 'Uncategorised') as category, SUM({$keptUnits} * {$unitRevenue}) as revenue")
            ->groupBy('m.category')
            ->orderByDesc('revenue')
            ->get();

        $paymentMethods = DB::table('pos_payments as p')
            ->join('pos_sales as s', 's.id', '=', 'p.pos_sale_id')
            ->where('s.pharmacy_id', $pharmacy->id)
            ->where('s.status', '!=', PosSale::STATUS_VOIDED)
            ->whereBetween('s.created_at', [$from, $to])
            ->where('p.status', 'paid')
            // Each payment counts what was paid that way (a split sale has several),
            // less its share of any refund on the sale.
            ->selectRaw('p.method, COUNT(DISTINCT s.id) as sale_count, SUM(p.amount * CASE WHEN s.total_amount > 0 THEN (s.total_amount - s.refunded_amount) / s.total_amount ELSE 0 END) as amount')
            ->groupBy('p.method')
            ->orderByDesc('amount')
            ->get()
            ->each(fn ($row) => $row->label = PosPayment::methodLabel($row->method));

        $staffSales = $standing()
            ->selectRaw('staff_id, COUNT(*) as sale_count, SUM(total_amount - refunded_amount) as amount')
            ->groupBy('staff_id')
            ->orderByDesc('amount')
            ->get();
        $staffNames = User::whereIn('id', $staffSales->pluck('staff_id')->filter())->pluck('full_name', 'id');
        $staffSales->each(fn ($row) => $row->name = $staffNames[$row->staff_id] ?? 'Unknown');

        $trend = $this->buildTrend($standing()->get(['created_at', 'total_amount', 'refunded_amount']), $from, $to);

        // Stock on hand right now (not tied to the period).
        $stock = Medication::where('pharmacy_id', $pharmacy->id)
            ->selectRaw('COALESCE(SUM(price * stock_level), 0) as selling_value, COALESCE(SUM(COALESCE(cost_price, 0) * stock_level), 0) as cost_value, COUNT(*) as product_count')
            ->first();

        // Online (marketplace) orders this branch handed over in the same period.
        $onlineOrders = OrderFulfillment::where('pharmacy_id', $pharmacy->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$from, $to])
            ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(subtotal), 0) as order_value, COALESCE(SUM(commission), 0) as fees')
            ->first();

        $activeReservations = OrderFulfillment::where('pharmacy_id', $pharmacy->id)
            ->whereIn('status', ['awaiting_confirmation', 'confirmed', 'ready'])
            ->count();

        return view('vendor-dashboard.reports', [
            'pharmacy' => $pharmacy,
            'period' => $period,
            'periods' => self::PERIODS,
            'periodLabel' => $periodLabel,
            'from' => $from,
            'to' => $to,
            'totals' => $totals,
            'voided' => $voided,
            'changePercent' => $changePercent,
            'lineTotals' => $lineTotals,
            'topProducts' => $topProducts,
            'categories' => $categories,
            'paymentMethods' => $paymentMethods,
            'staffSales' => $staffSales,
            'trend' => $trend,
            'stock' => $stock,
            'onlineOrders' => $onlineOrders,
            'activeReservations' => $activeReservations,
        ]);
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon, 3: string} period key, start, end, wording
     */
    protected function resolvePeriod(Request $request): array
    {
        $period = $request->query('period', '7d');

        if ($period === 'custom') {
            try {
                $from = Carbon::parse($request->query('from'))->startOfDay();
                $to = Carbon::parse($request->query('to') ?: $request->query('from'))->endOfDay();

                if ($request->query('from') && $from->lte($to)) {
                    return ['custom', $from, $to, $from->format('M j, Y') . ' – ' . $to->format('M j, Y')];
                }
            } catch (\Throwable) {
                // Unreadable dates fall through to the default range.
            }

            $period = '7d';
        }

        if (! array_key_exists($period, self::PERIODS)) {
            $period = '7d';
        }

        [$from, $to] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            '30d' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
        };

        return [$period, $from, $to, self::PERIODS[$period]];
    }

    /**
     * Sales over time for the chart: by hour for a single day, by day
     * otherwise, with empty slots filled in so quiet days show as zero
     * instead of disappearing.
     *
     * @return array{labels: list<string>, values: list<float>, unit: string}
     */
    protected function buildTrend($sales, Carbon $from, Carbon $to): array
    {
        $singleDay = $from->isSameDay($to);
        $format = $singleDay ? 'H' : 'Y-m-d';

        $byBucket = $sales->groupBy(fn ($sale) => $sale->created_at->format($format))
            ->map(fn ($group) => round($group->sum(fn ($sale) => $sale->total_amount - $sale->refunded_amount), 2));

        $labels = [];
        $values = [];

        if ($singleDay) {
            for ($hour = 0; $hour < 24; $hour++) {
                $labels[] = Carbon::createFromTime($hour)->format('ga');
                $values[] = (float) ($byBucket[str_pad((string) $hour, 2, '0', STR_PAD_LEFT)] ?? 0);
            }
        } else {
            for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                $labels[] = $day->format('M j');
                $values[] = (float) ($byBucket[$day->format('Y-m-d')] ?? 0);
            }
        }

        return ['labels' => $labels, 'values' => $values, 'unit' => $singleDay ? 'hour' : 'day'];
    }
}
