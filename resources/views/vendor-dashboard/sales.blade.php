@extends('layouts.app')

@php
    $pageTitle = $seesAll ? 'Sales & Receipts' : 'My Sales';
    $posRoute = $user->role === 'vendor' ? route('vendor.pos') : route('sales.staff.pos');
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);

    $statusStyles = [
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'voided' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
        'partially_refunded' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
        'refunded' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
    ];
    $methodIcons = ['cash' => 'fa-money-bill-wave', 'pos_card' => 'fa-credit-card', 'bank_transfer' => 'fa-university', 'valu_wallet' => 'fa-wallet'];

    // A link to this page with some filters changed (null removes one) and back to page 1.
    $filterUrl = function (array $changes) use ($filters) {
        $query = array_merge($filters, $changes);
        $query = array_filter($query, fn ($value) => $value !== null && $value !== '');

        return route('vendor.sales', $query);
    };

    // Quick date ranges, and which one (if any) is in use.
    $today = now()->toDateString();
    $datePresets = [
        'Today' => [$today, $today],
        'Yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
        'Last 7 days' => [now()->subDays(6)->toDateString(), $today],
        'This month' => [now()->startOfMonth()->toDateString(), $today],
    ];
    $activePreset = collect($datePresets)->search(fn ($range) => $range[0] === ($filters['from'] ?: null) && $range[1] === ($filters['to'] ?: null));
    $hasDates = $filters['from'] || $filters['to'];

    $totalCount = $statusCounts->sum();
    $staffName = $seesAll && $filters['staff'] ? optional($staffOptions->firstWhere('id', (int) $filters['staff']))->full_name : null;

    // Chips for each filter in use, each with a link that removes it.
    $chips = [];
    if ($filters['q'] !== '') $chips[] = ['Search: “' . $filters['q'] . '”', $filterUrl(['q' => null])];
    if ($filters['method']) $chips[] = ['Paid by ' . \App\Models\PosPayment::methodLabel($filters['method']), $filterUrl(['method' => null])];
    if ($staffName) $chips[] = ['Sold by ' . $staffName, $filterUrl(['staff' => null])];
    if ($hasDates) {
        $dateLabel = $activePreset ?: trim(
            ($filters['from'] ? \Carbon\Carbon::parse($filters['from'])->format('M j, Y') : 'Start') . ' – ' . ($filters['to'] ? \Carbon\Carbon::parse($filters['to'])->format('M j, Y') : 'today')
        );
        $chips[] = [$dateLabel, $filterUrl(['from' => null, 'to' => null])];
    }

    $field = 'h-10 rounded-xl border border-slate-200 bg-white text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
@endphp

@section('title', $pageTitle)
@section('header_title', $pageTitle)

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            {{ $seesAll ? 'Every sale made on the POS. Open one to reprint its receipt, void it, or refund returned items.' : 'Every sale you have made. Open one to reprint its receipt, or start a void or refund with a manager.' }}
        </p>
        <div class="flex items-center gap-2">
            <a href="{{ $posRoute }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                <i class="fas fa-plus text-xs"></i> New sale
            </a>
        </div>
    </div>

    @if ($customer)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-white px-5 py-4 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30"><i class="fas fa-user text-sm"></i></span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ $customer->displayName() }}'s purchases</p>
                    <p class="text-xs text-slate-500">{{ $customer->phone }} · customer since {{ $customer->created_at->format('M j, Y') }}</p>
                </div>
            </div>
            <a href="{{ $filterUrl(['customer' => null]) }}" class="inline-flex h-9 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900">
                <i class="fas fa-times text-xs"></i> Show everyone
            </a>
        </div>
    @endif

    {{-- ============ Key numbers (for whatever the filters match) ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-300">Money kept</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-coins text-sm"></i></span>
            </div>
            <p class="relative mt-3 truncate text-[26px] font-bold leading-none tracking-tight sm:text-[28px]">₦{{ number_format($summary->net_total) }}</p>
            <p class="relative mt-2 text-xs text-slate-400">After voids and refunds</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Sales</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fas fa-receipt text-sm"></i></span>
            </div>
            <p class="mt-3 text-[26px] font-bold leading-none tracking-tight text-slate-900 sm:text-[28px]">{{ number_format($summary->sale_count) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $hasFilters && collect($filters)->except('status')->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty() ? 'Matching your filters' : 'All time' }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Refunded</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="fas fa-undo text-sm"></i></span>
            </div>
            <p class="mt-3 truncate text-[26px] font-bold leading-none tracking-tight sm:text-[28px] {{ $summary->refunded_total > 0 ? 'text-amber-600' : 'text-slate-900' }}">₦{{ number_format($summary->refunded_total) }}</p>
            <p class="mt-2 text-xs text-slate-500">Given back to customers</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Voided</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600"><i class="fas fa-ban text-sm"></i></span>
            </div>
            <p class="mt-3 text-[26px] font-bold leading-none tracking-tight sm:text-[28px] {{ $summary->voided_count > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($summary->voided_count) }}</p>
            <p class="mt-2 text-xs text-slate-500">Sales cancelled</p>
        </div>
    </section>

    {{-- ============ Sales ============ --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Status tabs, with how many sales each holds --}}
        <nav class="sales-tabs flex gap-1 overflow-x-auto overflow-y-hidden border-b border-slate-100 px-3 pt-2 sm:px-4" aria-label="Filter by status">
            @foreach (['' => 'All'] + \App\Models\PosSale::STATUS_LABELS as $value => $label)
                @php
                    $active = (string) $filters['status'] === (string) $value;
                    $count = $value === '' ? $totalCount : (int) ($statusCounts[$value] ?? 0);
                @endphp
                <a href="{{ $filterUrl(['status' => $value ?: null]) }}"
                    class="relative inline-flex items-center gap-2 whitespace-nowrap px-3 py-3 text-sm font-medium transition {{ $active ? 'text-slate-900' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $label }}
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums {{ $active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($count) }}</span>
                    @if ($active)<span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-emerald-600"></span>@endif
                </a>
            @endforeach
        </nav>

        {{-- Filters: dropdowns apply as soon as they change; Enter searches --}}
        <form method="GET" action="{{ route('vendor.sales') }}" id="sales-filters" class="border-b border-slate-100 p-3 sm:p-4">
            @if ($filters['status']) <input type="hidden" name="status" value="{{ $filters['status'] }}"> @endif
            @if ($customer) <input type="hidden" name="customer" value="{{ $customer->id }}"> @endif

            <div class="flex flex-col gap-2 lg:flex-row lg:items-center">
                <div class="relative flex-1">
                    <i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                    <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search receipt number, customer name or phone"
                        class="{{ $field }} w-full pl-10 pr-3 placeholder:text-slate-400">
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                    <select name="method" aria-label="Paid by" onchange="this.form.submit()" class="{{ $field }} px-3">
                        <option value="">Any payment</option>
                        @foreach (\App\Models\PosPayment::METHODS as $value => $label)
                            <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    @if ($seesAll && $staffOptions->isNotEmpty())
                        <select name="staff" aria-label="Sold by" onchange="this.form.submit()" class="{{ $field }} px-3">
                            <option value="">Everyone</option>
                            @foreach ($staffOptions as $person)
                                <option value="{{ $person->id }}" @selected((string) $filters['staff'] === (string) $person->id)>{{ $person->full_name }}</option>
                            @endforeach
                        </select>
                    @endif

                    <button type="button" id="sales-date-toggle" aria-expanded="{{ $hasDates && ! $activePreset ? 'true' : 'false' }}"
                        class="{{ $field }} col-span-2 inline-flex items-center justify-center gap-2 px-3.5 font-medium sm:col-span-1 {{ $hasDates ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : '' }}">
                        <i class="far fa-calendar text-sm {{ $hasDates ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                        {{ $hasDates ? ($activePreset ?: 'Custom dates') : 'Any date' }}
                        <i class="fas fa-chevron-down text-[10px] text-slate-400"></i>
                    </button>

                    <button type="submit" class="col-span-2 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800 sm:col-span-1">
                        <i class="fas fa-search text-xs"></i> Search
                    </button>
                </div>
            </div>

            {{-- Dates: quick picks, or any range --}}
            <div id="sales-date-panel" class="{{ $hasDates && ! $activePreset ? 'flex' : 'hidden' }} mt-3 flex-col gap-3 rounded-xl bg-slate-50 p-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($datePresets as $label => [$from, $to])
                        <a href="{{ $filterUrl(['from' => $from, 'to' => $to]) }}"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $activePreset === $label ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }}">{{ $label }}</a>
                    @endforeach
                    @if ($hasDates)
                        <a href="{{ $filterUrl(['from' => null, 'to' => null]) }}" class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-white hover:text-slate-900">Any date</a>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-[13px] font-medium text-slate-600">From</span>
                    <input type="date" name="from" value="{{ $filters['from'] }}" max="{{ $today }}" aria-label="From date" class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                    <span class="text-[13px] text-slate-400">to</span>
                    <input type="date" name="to" value="{{ $filters['to'] }}" max="{{ $today }}" aria-label="To date" class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                    <button type="submit" class="h-9 rounded-lg bg-emerald-600 px-3.5 text-xs font-semibold text-white transition hover:bg-emerald-700">Apply</button>
                </div>
            </div>

            {{-- What is filtering the list right now --}}
            @if ($chips)
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($chips as [$label, $removeUrl])
                        <a href="{{ $removeUrl }}" class="group inline-flex items-center gap-1.5 rounded-full bg-slate-100 py-1 pl-3 pr-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-200" title="Remove this filter">
                            {{ $label }}
                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white text-[8px] text-slate-500 group-hover:text-rose-600"><i class="fas fa-times"></i></span>
                        </a>
                    @endforeach
                    <a href="{{ route('vendor.sales', array_filter(['customer' => $customer?->id])) }}" class="text-xs font-semibold text-slate-500 hover:text-slate-900 hover:underline">Clear all</a>
                </div>
            @endif
        </form>

        @if ($sales->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas fa-receipt"></i></div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $hasFilters ? 'No sales match these filters' : 'No sales yet' }}</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    {{ $hasFilters ? 'Try a wider date range, another tab, or clear the filters.' : 'Sales made at the till appear here as soon as they are completed.' }}
                </p>
                <div class="mt-5 flex justify-center gap-2">
                    @if ($hasFilters)
                        <a href="{{ route('vendor.sales') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:border-slate-300"><i class="fas fa-times text-xs text-slate-400"></i> Clear filters</a>
                    @else
                        <a href="{{ $posRoute }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"><i class="fas fa-cash-register text-xs"></i> Make the first sale</a>
                    @endif
                </div>
            </div>
        @else
            {{-- Wide screens: table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="whitespace-nowrap border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-3 pl-5 pr-3">Receipt</th>
                            <th class="px-3 py-3">Customer</th>
                            <th class="px-3 py-3">Items</th>
                            @if ($seesAll)<th class="px-3 py-3">Sold by</th>@endif
                            <th class="px-3 py-3">Payment</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="py-3 pl-3 pr-5"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sales as $sale)
                            @php
                                $voided = $sale->isVoided();
                                $methods = $sale->payments->pluck('method')->unique()->values();
                            @endphp
                            <tr class="group cursor-pointer transition hover:bg-slate-50/80" onclick="window.location='{{ route('vendor.sales.show', $sale) }}'">
                                <td class="whitespace-nowrap py-3.5 pl-5 pr-3">
                                    <p class="font-semibold tabular-nums text-slate-900">{{ $sale->receipt_no }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">{{ $sale->created_at->isToday() ? 'Today' : ($sale->created_at->isYesterday() ? 'Yesterday' : $sale->created_at->format('M j, Y')) }} · {{ $sale->created_at->format('g:i A') }}</p>
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($sale->customer_id)
                                        <a href="{{ $filterUrl(['customer' => $sale->customer_id]) }}" onclick="event.stopPropagation()" title="See everything this customer has bought" class="font-medium text-slate-800 hover:text-emerald-700 hover:underline">{{ $sale->customer_name ?: 'Customer' }}</a>
                                    @else
                                        <p class="whitespace-nowrap font-medium text-slate-800">{{ $sale->customer_name ?: 'Walk-in Customer' }}</p>
                                    @endif
                                    @if ($sale->customer_phone)
                                        <p class="mt-0.5 text-xs tabular-nums text-slate-500">{{ $sale->customer_phone }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-slate-600">{{ $sale->items_count }} {{ \Illuminate\Support\Str::plural('product', $sale->items_count) }}</td>
                                @if ($seesAll)
                                    <td class="whitespace-nowrap px-3 py-3.5 text-slate-600">{{ $sale->staff->full_name ?? '—' }}</td>
                                @endif
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    @if ($sale->isSplitPayment())
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-600/15" title="{{ $sale->paymentLabel() }}">
                                            <i class="fas fa-code-branch text-[10px]"></i> Split
                                            <span class="font-normal text-indigo-500">{{ $sale->payments->map(fn ($line) => \App\Models\PosPayment::methodLabel($line->method))->implode(' + ') }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-slate-600">
                                            <i class="fas {{ $methodIcons[$methods->first()] ?? 'fa-money-bill-wave' }} w-4 text-center text-xs text-slate-400"></i>
                                            {{ $sale->paymentLabel() }}
                                        </span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-right">
                                    <p class="font-semibold tabular-nums {{ $voided ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $money($sale->total_amount) }}</p>
                                    @if (! $voided && $sale->refunded_amount > 0)
                                        <p class="mt-0.5 text-xs tabular-nums text-rose-600">−{{ $money($sale->refunded_amount) }} refunded</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $statusStyles[$sale->status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15' }}">{{ $sale->statusLabel() }}</span>
                                </td>
                                <td class="whitespace-nowrap py-3.5 pl-3 pr-5 text-right">
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition group-hover:bg-white group-hover:text-emerald-700 group-hover:shadow-sm"><i class="fas fa-chevron-right text-xs"></i></span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Phones: one card per sale --}}
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($sales as $sale)
                    @php $voided = $sale->isVoided(); @endphp
                    <li>
                        <a href="{{ route('vendor.sales.show', $sale) }}" class="flex items-start gap-3 px-4 py-3.5 transition active:bg-slate-50">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold tabular-nums text-slate-900">{{ $sale->receipt_no }}</p>
                                    <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $statusStyles[$sale->status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/15' }}">{{ $sale->statusLabel() }}</span>
                                </div>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $sale->customer_name ?: 'Walk-in Customer' }} · {{ $sale->items_count }} {{ \Illuminate\Support\Str::plural('product', $sale->items_count) }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-400">
                                    {{ $sale->created_at->isToday() ? 'Today' : $sale->created_at->format('M j') }}, {{ $sale->created_at->format('g:i A') }} · {{ $sale->isSplitPayment() ? 'Split payment' : $sale->paymentLabel() }}@if ($seesAll && $sale->staff) · {{ $sale->staff->full_name }}@endif
                                </p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <p class="text-sm font-semibold tabular-nums {{ $voided ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $money($sale->total_amount) }}</p>
                                @if (! $voided && $sale->refunded_amount > 0)
                                    <p class="mt-0.5 text-[11px] tabular-nums text-rose-600">−{{ $money($sale->refunded_amount) }}</p>
                                @endif
                                <i class="fas fa-chevron-right mt-1.5 text-[10px] text-slate-300"></i>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-3.5 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>Showing <span class="font-semibold text-slate-700">{{ $sales->firstItem() }}–{{ $sales->lastItem() }}</span> of <span class="font-semibold text-slate-700">{{ number_format($sales->total()) }}</span> {{ \Illuminate\Support\Str::plural('sale', $sales->total()) }}</p>
                @if ($sales->hasPages())
                    <div class="flex items-center gap-2">
                        @if ($sales->onFirstPage())
                            <span class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-100 px-3 text-xs font-semibold text-slate-300"><i class="fas fa-chevron-left text-[10px]"></i> Previous</span>
                        @else
                            <a href="{{ $sales->previousPageUrl() }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300"><i class="fas fa-chevron-left text-[10px]"></i> Previous</a>
                        @endif
                        <span class="px-1 text-xs tabular-nums">Page {{ $sales->currentPage() }} of {{ $sales->lastPage() }}</span>
                        @if ($sales->hasMorePages())
                            <a href="{{ $sales->nextPageUrl() }}" class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300">Next <i class="fas fa-chevron-right text-[10px]"></i></a>
                        @else
                            <span class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-100 px-3 text-xs font-semibold text-slate-300">Next <i class="fas fa-chevron-right text-[10px]"></i></span>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    </section>
</div>
@endsection

@push('styles')
<style>
    /* The status tabs scroll sideways on small screens without showing a scrollbar. */
    .sales-tabs { scrollbar-width: none; }
    .sales-tabs::-webkit-scrollbar { display: none; }
</style>
@endpush

@push('scripts')
<script>
    // The date button opens the quick picks and the custom range.
    (function () {
        const toggle = document.getElementById('sales-date-toggle');
        const panel = document.getElementById('sales-date-panel');
        if (!toggle || !panel) return;
        toggle.addEventListener('click', () => {
            const open = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !open);
            panel.classList.toggle('flex', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    })();
</script>
@endpush
