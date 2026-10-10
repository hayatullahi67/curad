@extends('layouts.app')

@section('title', 'Reports')
@section('header_title', 'Reports')

@php
    $averageSale = $totals->sale_count > 0 ? $totals->net_sales / $totals->sale_count : 0;
    $hasSales = $totals->sale_count > 0;
    $hasCostData = $lineTotals->line_count > 0 && $lineTotals->lines_without_cost < $lineTotals->line_count;
    $paymentTotal = max(1, $paymentMethods->sum('amount'));
    $staffTop = max(1, (float) $staffSales->max('amount'));
    $productTop = max(1, (float) $topProducts->max('units'));
    $categoryTotal = max(1, $categories->sum('revenue'));
@endphp

@section('content')
<div class="reports-page mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- Heading + period --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm text-slate-500">
                How {{ $pharmacy->name }} is doing at the POS.
                Showing <span class="font-semibold text-slate-900">{{ $periodLabel }}</span>
                @if ($period !== 'custom') ({{ $from->isSameDay($to) ? $from->format('M j, Y') : $from->format('M j') . ' – ' . $to->format('M j, Y') }}) @endif
            </p>
        </div>
        <button type="button" onclick="window.print()" class="no-print inline-flex h-10 items-center justify-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 lg:self-auto">
            <i class="fas fa-print text-slate-400"></i> Print report
        </button>
    </div>

    <div class="no-print rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex flex-wrap gap-1.5">
                @foreach ($periods as $key => $label)
                    <a href="{{ route('vendor.reports', ['period' => $key]) }}"
                       class="rounded-lg px-3.5 py-2 text-sm font-semibold transition {{ $period === $key ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('vendor.reports') }}" class="flex flex-wrap items-center gap-2">
                <input type="hidden" name="period" value="custom">
                <span class="text-xs font-bold text-slate-500">Or pick dates:</span>
                <input type="date" name="from" required value="{{ $period === 'custom' ? $from->toDateString() : '' }}" max="{{ now()->toDateString() }}" aria-label="From date"
                    class="rounded-lg border px-2.5 py-1.5 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 {{ $period === 'custom' ? 'border-slate-900' : 'border-slate-200' }}">
                <span class="text-slate-400">to</span>
                <input type="date" name="to" value="{{ $period === 'custom' ? $to->toDateString() : '' }}" max="{{ now()->toDateString() }}" aria-label="To date"
                    class="rounded-lg border px-2.5 py-1.5 text-sm text-slate-800 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100 {{ $period === 'custom' ? 'border-slate-900' : 'border-slate-200' }}">
                <button type="submit" class="rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-bold text-white transition hover:bg-emerald-700">Show</button>
            </form>
        </div>
    </div>

    {{-- Headline numbers --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative col-span-2 overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5 lg:col-span-1">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <p class="relative text-[13px] font-medium text-slate-300">Sales</p>
            <p class="relative mt-3 truncate text-[26px] font-bold leading-none sm:text-[28px]">₦{{ number_format($totals->net_sales, 2) }}</p>
            <p class="relative mt-2 text-xs text-slate-400">
                @if ($changePercent !== null)
                    <span class="font-bold {{ $changePercent >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        <i class="fas fa-arrow-{{ $changePercent >= 0 ? 'up' : 'down' }} text-[10px]"></i> {{ abs($changePercent) }}%
                    </span>
                    vs the period before
                @else
                    After voids and refunds
                @endif
            </p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-[13px] font-medium text-slate-500">Transactions</p>
            <p class="mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] text-slate-900">{{ number_format($totals->sale_count) }}</p>
            <p class="mt-1 text-xs text-slate-500">₦{{ number_format($averageSale, 2) }} average sale</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-[13px] font-medium text-slate-500">Units sold</p>
            <p class="mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] text-slate-900">{{ number_format($lineTotals->units) }}</p>
            <p class="mt-1 text-xs text-slate-500">Items that left the shelf</p>
        </div>
        <div class="col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1">
            <p class="text-[13px] font-medium text-slate-500">Estimated profit</p>
            @if ($hasCostData)
                <p class="mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] text-emerald-700">₦{{ number_format($lineTotals->profit, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">
                    @if ($lineTotals->lines_without_cost > 0)
                        Excludes {{ $lineTotals->lines_without_cost }} sale line(s) with no cost price
                    @else
                        Selling price minus cost price
                    @endif
                </p>
            @else
                <p class="mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] text-slate-300">—</p>
                <p class="mt-1 text-xs text-slate-500">Add cost prices to your products to see profit</p>
            @endif
        </div>
    </div>

    {{-- Money that came off --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-tag"></i></span>
            <div>
                <p class="text-xs text-slate-500">Discounts given</p>
                <p class="text-lg font-bold text-slate-900">₦{{ number_format($totals->discounts, 2) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="fas fa-undo"></i></span>
            <div>
                <p class="text-xs text-slate-500">Refunded to customers</p>
                <p class="text-lg font-bold text-slate-900">₦{{ number_format($totals->refunds, 2) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600"><i class="fas fa-ban"></i></span>
            <div>
                <p class="text-xs text-slate-500">Voided sales</p>
                <p class="text-lg font-bold text-slate-900">{{ number_format($voided->sale_count) }} <span class="text-sm font-bold text-slate-400">· ₦{{ number_format($voided->amount, 2) }}</span></p>
            </div>
        </div>
    </div>

    @unless ($hasSales)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas fa-chart-line"></i></div>
            <h3 class="mt-4 text-lg font-semibold text-slate-900">No sales in this period</h3>
            <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">Choose a different period above, or make a sale on the POS and it shows up here.</p>
        </div>
    @else
        {{-- Sales over time --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">Sales over time</h2>
            <p class="text-xs text-slate-500">Money kept per {{ $trend['unit'] }}, after voids and refunds.</p>
            <div class="relative mt-5 h-[280px]">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Best sellers --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Best-selling products</h2>
                <p class="text-xs text-slate-500">Ranked by units sold.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($topProducts as $index => $product)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <p class="min-w-0 truncate font-semibold text-slate-800"><span class="mr-1.5 font-mono text-xs text-slate-400">{{ $index + 1 }}.</span>{{ $product->product_name }}</p>
                                <p class="flex-shrink-0 font-mono text-xs text-slate-500"><span class="font-bold text-slate-900">{{ number_format($product->units) }}</span> sold · ₦{{ number_format($product->revenue, 0) }}</p>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(2, round($product->units / $productTop * 100)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Payment methods --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">How customers paid</h2>
                <p class="text-xs text-slate-500">Use this to check your cash drawer, card machine and bank alerts.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($paymentMethods as $method)
                        @php $share = round($method->amount / $paymentTotal * 100); @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <p class="font-semibold text-slate-800">{{ $method->label }} <span class="font-normal text-slate-400">· {{ number_format($method->sale_count) }} {{ \Illuminate\Support\Str::plural('sale', $method->sale_count) }}</span></p>
                                <p class="font-semibold text-slate-900">₦{{ number_format($method->amount, 2) }}</p>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-500" style="width: {{ max(2, $share) }}%"></div>
                                </div>
                                <span class="w-9 text-right font-mono text-xs text-slate-500">{{ $share }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Staff --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Sales by staff</h2>
                <p class="text-xs text-slate-500">Who rang up the sales. Click a name to see their receipts.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($staffSales as $person)
                        <a href="{{ route('vendor.sales', ['staff' => $person->staff_id, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="group block">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <p class="font-semibold text-slate-800 group-hover:text-emerald-700 group-hover:underline">{{ $person->name }} <span class="font-normal text-slate-400">· {{ number_format($person->sale_count) }} {{ \Illuminate\Support\Str::plural('sale', $person->sale_count) }}</span></p>
                                <p class="font-semibold text-slate-900">₦{{ number_format($person->amount, 2) }}</p>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-violet-500" style="width: {{ max(2, round($person->amount / $staffTop * 100)) }}%"></div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Categories --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Sales by category</h2>
                <p class="text-xs text-slate-500">Which kinds of products bring in the money.</p>
                <div class="mt-5 space-y-4">
                    @foreach ($categories->take(8) as $category)
                        @php $share = round($category->revenue / $categoryTotal * 100); @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <p class="min-w-0 truncate font-semibold text-slate-800">{{ $category->category }}</p>
                                <p class="flex-shrink-0 font-semibold text-slate-900">₦{{ number_format($category->revenue, 2) }}</p>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ max(2, $share) }}%"></div>
                                </div>
                                <span class="w-9 text-right font-mono text-xs text-slate-500">{{ $share }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endunless

    {{-- Stock + online orders --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Stock on hand</h2>
                    <p class="text-xs text-slate-500">What is on your shelves right now.</p>
                </div>
                @if (auth()->user()->role === 'vendor' || \App\Support\StaffRoles::can(auth()->user()->staff_role, 'inventory'))
                    <a href="{{ route('vendor.stock.receive', ['tab' => 'history']) }}" class="no-print flex-shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">Stock history <i class="fas fa-chevron-right text-[10px]"></i></a>
                @endif
            </div>
            <dl class="mt-5 grid grid-cols-3 gap-4">
                <div>
                    <dt class="text-xs text-slate-500">Products</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format($stock->product_count) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Worth at selling price</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">₦{{ number_format($stock->selling_value, 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Worth at cost price</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">{{ $stock->cost_value > 0 ? '₦' . number_format($stock->cost_value, 0) : '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Online orders</h2>
                    <p class="text-xs text-slate-500">Marketplace orders handed over in this period. Not included in the POS figures above.</p>
                </div>
                @if (auth()->user()->role === 'vendor' || \App\Support\StaffRoles::canViewReservations(auth()->user()->staff_role))
                    <a href="{{ route('vendor.orders') }}" class="no-print flex-shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">Online Orders <i class="fas fa-chevron-right text-[10px]"></i></a>
                @endif
            </div>
            <dl class="mt-5 grid grid-cols-3 gap-4">
                <div>
                    <dt class="text-xs text-slate-500">Orders</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format($onlineOrders->order_count) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Order value</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">₦{{ number_format($onlineOrders->order_value, 0) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Still in progress</dt>
                    <dd class="mt-0.5 text-lg font-bold text-slate-900">{{ number_format($activeReservations) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    @media print {
        #sidebar, header, .no-print { display: none !important; }
        body, main { background: white !important; }
        .reports-page > * { break-inside: avoid; }
    }
</style>
@endpush

@if ($hasSales)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        const trend = @json($trend);
        const naira = (value) => '₦' + Number(value).toLocaleString('en-NG', { maximumFractionDigits: 0 });

        new Chart(document.getElementById('salesTrendChart').getContext('2d'), {
            type: trend.labels.length > 14 || trend.unit === 'hour' ? 'bar' : 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Sales',
                    data: trend.values,
                    borderColor: '#059669',
                    backgroundColor: trend.labels.length > 14 || trend.unit === 'hour' ? '#10b981' : 'rgba(16, 185, 129, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    borderRadius: 4,
                    pointBackgroundColor: '#059669',
                    pointRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (context) => naira(context.parsed.y) } },
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: naira, font: { size: 11 } } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, maxRotation: 0, autoSkip: true } },
                },
            },
        });
    })();
</script>
@endpush
@endif
