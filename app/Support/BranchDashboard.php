<?php

namespace App\Support;

use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Medication;
use App\Models\OrderFulfillment;
use App\Models\Pharmacy;
use App\Models\PosSale;
use App\Models\StaffPasswordResetRequest;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Builds the dashboard for whoever is signed in. Everyone gets the same
 * page; what is on it depends on what their role lets them do in their
 * branch. A cashier sees their own sales, an inventory officer
 * sees stock, a finance officer sees the branch's money, and the owner or
 * a branch manager sees all of it.
 */
class BranchDashboard
{
    public static function for(User $user, Pharmacy $branch): array
    {
        $isOwner = $user->role === 'vendor';
        $role = $user->staff_role;
        $can = [
            'sell' => $isOwner || StaffRoles::canSell($role),
            'all_sales' => $isOwner || StaffRoles::can($role, 'all_sales'),
            'supervise' => $isOwner || StaffRoles::canApproveVoids($role),
            'orders' => $isOwner || StaffRoles::canViewReservations($role),
            'manage_orders' => $isOwner || StaffRoles::canManageOrders($role),
            'stock' => $isOwner || StaffRoles::can($role, 'inventory'),
            'reports' => $isOwner || StaffRoles::can($role, 'reports'),
            'owner' => $isOwner,
        ];

        $id = $branch->id;
        $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
        $posUrl = $isOwner ? route('vendor.pos') : route('sales.staff.pos');

        $cards = [];
        $tasks = [];
        $actions = [];
        $chart = null;
        $recent = null;

        // ---------------------------------------------------------------- selling
        $standing = fn () => PosSale::where('pharmacy_id', $id)->where('status', '!=', PosSale::STATUS_VOIDED);
        $net = DB::raw('total_amount - refunded_amount');

        if ($can['sell']) {
            $mine = fn () => $standing()->where('staff_id', $user->id);
            $myToday = $mine()->whereDate('created_at', today());

            // A branch manager's own sales matter less than the branch's, so theirs come second.
            if (! $can['all_sales']) {
                $cards[] = ['My sales today', $money((clone $myToday)->sum($net)), (clone $myToday)->count() . ' ' . str('sale')->plural((clone $myToday)->count()), 'fa-coins', 'dark', route('vendor.sales')];
                $cards[] = ['This week', $money($mine()->where('created_at', '>=', today()->subDays(6))->sum($net)), 'Last 7 days', 'fa-calendar-week', 'emerald', route('vendor.sales')];
                $cards[] = ['This month', $money($mine()->where('created_at', '>=', now()->startOfMonth())->sum($net)), now()->format('F'), 'fa-calendar', 'sky', route('vendor.sales')];
            }

            $actions[] = ['Open the POS', 'Start selling', 'fa-cash-register', $posUrl, true];
            $actions[] = [$can['all_sales'] || $can['supervise'] ? 'Sales & receipts' : 'My sales', 'Find or reprint a receipt', 'fa-receipt', route('vendor.sales'), false];
        }

        // ------------------------------------------------------- the branch's money
        if ($can['all_sales']) {
            $online = fn () => OrderFulfillment::where('pharmacy_id', $id)->where('status', 'completed');
            $today = (float) $standing()->whereDate('created_at', today())->sum($net) + (float) $online()->whereDate('completed_at', today())->sum('subtotal');
            $yesterday = (float) $standing()->whereDate('created_at', today()->subDay())->sum($net) + (float) $online()->whereDate('completed_at', today()->subDay())->sum('subtotal');
            $count = $standing()->whereDate('created_at', today())->count();
            $month = (float) $standing()->where('created_at', '>=', now()->startOfMonth())->sum($net) + (float) $online()->where('completed_at', '>=', now()->startOfMonth())->sum('subtotal');

            $versus = $yesterday > 0
                ? (($today >= $yesterday ? 'Up ' : 'Down ') . abs((int) round(($today - $yesterday) / $yesterday * 100)) . '% on yesterday')
                : $count . ' ' . str('sale')->plural($count) . ' at the POS';

            array_unshift($cards,
                ['Branch sales today', $money($today), $versus, 'fa-coins', 'dark', route('vendor.sales')],
                ['This month', $money($month), 'POS and online together', 'fa-calendar', 'emerald', $can['reports'] ? route('vendor.reports', ['period' => 'month']) : route('vendor.sales')],
            );

            $from = today()->subDays(6);
            $posDays = $standing()->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as day, SUM(total_amount - refunded_amount) as amount')->groupBy('day')->pluck('amount', 'day');
            $onlineDays = $online()->where('completed_at', '>=', $from)->selectRaw('DATE(completed_at) as day, SUM(subtotal) as amount')->groupBy('day')->pluck('amount', 'day');
            $days = collect(range(0, 6))->map(function ($offset) use ($from, $posDays, $onlineDays) {
                $day = $from->copy()->addDays($offset);

                return (object) ['date' => $day, 'amount' => (float) ($posDays[$day->toDateString()] ?? 0) + (float) ($onlineDays[$day->toDateString()] ?? 0)];
            });
            $chart = ['title' => 'Branch sales, last 7 days', 'days' => $days, 'peak' => max(1, $days->max('amount')), 'total' => $days->sum('amount')];

$cards[] = ['Last 7 days', $money($days->sum('amount')), 'POS and online together', 'fa-calendar-week', 'sky', route('vendor.sales')];

            $recent = [
                'title' => 'Latest sales in the branch',
                'url' => route('vendor.sales'),
                'rows' => PosSale::where('pharmacy_id', $id)->with('staff:id,full_name')->latest()->limit(6)->get()->map(fn (PosSale $sale) => [
                    'title' => $sale->receipt_no,
                    'meta' => ($sale->staff->full_name ?? 'Staff') . ' · ' . $sale->created_at->format($sale->created_at->isToday() ? 'g:i A' : 'M j, g:i A'),
                    'value' => $money($sale->total_amount - $sale->refunded_amount),
                    'note' => $sale->status === PosSale::STATUS_COMPLETED ? null : (PosSale::STATUS_LABELS[$sale->status] ?? null),
                    'url' => route('vendor.sales.show', $sale),
                ]),
            ];
        } elseif ($can['sell']) {
            $from = today()->subDays(6);
            $mineDays = $standing()->where('staff_id', $user->id)->where('created_at', '>=', $from)->selectRaw('DATE(created_at) as day, SUM(total_amount - refunded_amount) as amount')->groupBy('day')->pluck('amount', 'day');
            $days = collect(range(0, 6))->map(fn ($offset) => (object) ['date' => $from->copy()->addDays($offset), 'amount' => (float) ($mineDays[$from->copy()->addDays($offset)->toDateString()] ?? 0)]);
            $chart = ['title' => 'My sales, last 7 days', 'days' => $days, 'peak' => max(1, $days->max('amount')), 'total' => $days->sum('amount')];

            $recent = [
                'title' => 'My latest sales',
                'url' => route('vendor.sales'),
                'rows' => PosSale::where('pharmacy_id', $id)->where('staff_id', $user->id)->withCount('items')->latest()->limit(6)->get()->map(fn (PosSale $sale) => [
                    'title' => $sale->receipt_no,
                    'meta' => $sale->items_count . ' ' . str('item')->plural($sale->items_count) . ' · ' . $sale->created_at->format($sale->created_at->isToday() ? 'g:i A' : 'M j, g:i A'),
                    'value' => $money($sale->total_amount - $sale->refunded_amount),
                    'note' => $sale->status === PosSale::STATUS_COMPLETED ? null : (PosSale::STATUS_LABELS[$sale->status] ?? null),
                    'url' => route('vendor.sales.show', $sale),
                ]),
            ];
        }

        // ------------------------------------------------------------ online orders
        if ($can['orders']) {
            $byStatus = OrderFulfillment::where('pharmacy_id', $id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $new = (int) ($byStatus['awaiting_confirmation'] ?? 0);
            $preparing = (int) ($byStatus['confirmed'] ?? 0);
            $ready = (int) ($byStatus['ready'] ?? 0);
            $waiting = $new + $preparing + $ready;

            $cards[] = ['Online orders waiting', (string) $waiting, $waiting ? "{$new} new · {$preparing} preparing · {$ready} ready" : 'Nothing to do right now', 'fa-bag-shopping', $new ? 'amber' : 'slate', route('vendor.orders')];

            if ($new) {
                $tasks[] = ['fa-bell', 'amber', $new . ' new online ' . str('order')->plural($new) . ' to accept', 'Unanswered orders are cancelled after ' . config('curad.marketplace.confirmation_hours') . ' hours', route('vendor.orders', ['status' => 'awaiting_confirmation'])];
            }
            if ($preparing && $can['manage_orders']) {
                $tasks[] = ['fa-box', 'sky', $preparing . ' ' . str('order')->plural($preparing) . ' to pack', 'Mark each one ready when it is packed', route('vendor.orders', ['status' => 'confirmed'])];
            }
            if ($ready && $can['manage_orders']) {
                $tasks[] = ['fa-box-open', 'emerald', $ready . ' ' . str('order')->plural($ready) . ' ready to hand over', 'To the customer, or to the rider in Logistics', route('vendor.orders', ['status' => 'ready'])];
            }

            $actions[] = ['Online orders', 'Accept, pack, hand over', 'fa-bag-shopping', route('vendor.orders'), false];
        }

        // -------------------------------------------------------------------- stock
        if ($can['stock'] || $can['owner']) {
            $stock = Medication::where('pharmacy_id', $id)->selectRaw(
                'COUNT(*) as products,
                 SUM(CASE WHEN stock_level <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                 SUM(CASE WHEN stock_level > 0 AND stock_level <= low_stock_threshold THEN 1 ELSE 0 END) as low,
                 COALESCE(SUM(expired_stock), 0) as expired,
                 SUM(CASE WHEN is_listed = 0 THEN 1 ELSE 0 END) as unlisted,
                 COALESCE(SUM(stock_level * COALESCE(cost_price, 0)), 0) as value'
            )->first();
            $soon = (int) InventoryBatch::where('pharmacy_id', $id)->where('quantity', '>', 0)->whereDate('expiry_date', '>=', today())->whereDate('expiry_date', '<=', today()->addDays(90))->sum('quantity');
            $incoming = StockTransfer::where('to_pharmacy_id', $id)->where('status', 'in_transit')->count();
            $low = (int) $stock->low + (int) $stock->out_of_stock;

            if (! $can['all_sales']) {
                $cards[] = ['Products in this branch', (string) (int) $stock->products, $money($stock->value) . ' at cost price', 'fa-boxes-stacked', 'dark', route('vendor.stock.receive', ['tab' => 'list'])];
                $cards[] = ['Expiring within 90 days', number_format($soon), 'Units to sell first', 'fa-hourglass-half', $soon ? 'amber' : 'slate', route('vendor.stock.receive', ['tab' => 'batches'])];
            }
            if (! $can['all_sales']) {
                $cards[] = ['Transfers to confirm', (string) $incoming, $incoming ? 'Sent from another branch' : 'Nothing on the way', 'fa-right-left', $incoming ? 'amber' : 'slate', route('vendor.stock.receive', ['tab' => 'transfers'])];
            }
            $cards[] = ['Running low', (string) $low, $stock->out_of_stock ? (int) $stock->out_of_stock . ' out of stock' : 'At or under the reorder level', 'fa-arrow-trend-down', $low ? 'rose' : 'slate', route('vendor.inventory')];

            if ($incoming) {
                $tasks[] = ['fa-right-left', 'amber', $incoming . ' stock ' . str('transfer')->plural($incoming) . ' to confirm', 'Sent from another branch; confirm when it arrives', route('vendor.stock.receive', ['tab' => 'transfers'])];
            }
            if ($stock->expired > 0) {
                $tasks[] = ['fa-calendar-xmark', 'rose', number_format($stock->expired) . ' expired ' . str('unit')->plural((int) $stock->expired) . ' on the shelf', 'Blocked from sale. Remove them and adjust the count', route('vendor.stock.receive', ['tab' => 'batches'])];
            }
            if ($stock->out_of_stock > 0) {
                $tasks[] = ['fa-box-archive', 'rose', (int) $stock->out_of_stock . ' ' . str('product')->plural((int) $stock->out_of_stock) . ' out of stock', 'Receive a delivery or ask another branch', route('vendor.inventory')];
            }
            if ($stock->unlisted > 0) {
                $tasks[] = ['fa-tag', 'sky', (int) $stock->unlisted . ' ' . str('product')->plural((int) $stock->unlisted) . ' in stock but not for sale yet', 'Give them a selling price in Inventory', route('vendor.inventory')];
            }

            $actions[] = ['Receive stock', 'Record a delivery', 'fa-truck', route('vendor.stock.receive'), ! $can['sell']];
            $actions[] = ['Inventory', 'Prices and products', 'fa-pills', route('vendor.inventory'), false];

            if (! $recent) {
                $recent = [
                    'title' => 'Latest stock changes',
                    'url' => route('vendor.stock.receive', ['tab' => 'history']),
                    'rows' => InventoryMovement::where('pharmacy_id', $id)->with(['product:id,name', 'user:id,full_name'])->latest()->latest('id')->limit(6)->get()->map(fn (InventoryMovement $movement) => [
                        'title' => $movement->product->name ?? 'Product',
                        'meta' => $movement->typeLabel() . ' · ' . ($movement->user->full_name ?? 'System') . ' · ' . $movement->created_at->format($movement->created_at->isToday() ? 'g:i A' : 'M j'),
                        'value' => ($movement->quantity_change > 0 ? '+' : '') . $movement->quantity_change,
                        'note' => null,
                        'url' => route('vendor.stock.receive', ['tab' => 'history', 'product' => $movement->medication_id]),
                    ]),
                ];
            }
        }

        // -------------------------------------------------------------- owner only
        if ($can['owner']) {
            $resets = StaffPasswordResetRequest::pending()->whereIn('staff_id', User::where('created_by_user_id', $user->id)->pluck('id'))->count();

            if ($resets) {
                $tasks[] = ['fa-key', 'amber', $resets . ' staff ' . str('member')->plural($resets) . ' asked for a new password', 'Set it from Staff Access', route('vendor.sales-staff')];
            }
            if (! $branch->latitude || ! $branch->longitude) {
                $tasks[] = ['fa-location-dot', 'sky', 'This branch is not pinned on the map', 'Delivery fees need its position', route('vendor.branches.show', $branch)];
            }

            $actions[] = ['Branches', 'Compare all branches', 'fa-code-branch', route('vendor.branches'), false];
        }

        if ($can['reports']) {
            $actions[] = ['Reports', 'Sales, profit, staff', 'fa-chart-pie', route('vendor.reports'), ! $can['sell'] && ! $can['stock']];
        }

        // Exactly one button is the main one: the first that asked for it.
        $main = false;
        $actions = array_map(function ($action) use (&$main) {
            $action[4] = $action[4] && ! $main;
            $main = $main || $action[4];

            return $action;
        }, $actions);

        return [
            'branch' => $branch,
            'user' => $user,
            'roleLabel' => $isOwner ? 'Owner' : StaffRoles::label($role),
            'cards' => array_slice($cards, 0, 4),
            'tasks' => $tasks,
            'actions' => array_slice($actions, 0, 6),
            'chart' => $chart,
            'recent' => $recent,
            'can' => $can,
        ];
    }
}
