@extends('layouts.app')

@php
    $pharmacy = \App\Models\Pharmacy::current();
    $pageTitle = 'Inventory Listing';

    // $medications is what has been listed for sale; $stock is every product
    // record, listed or not. Stock that is not listed yet is offered in the
    // "waiting to be listed" strip and first in the product form's dropdown.
    $totalItems = $medications->count();
    $lowStockItems = $medications->filter(fn ($item) => $item->stock_level > 0 && $item->stock_level <= $item->low_stock_threshold)->count();
    $outOfStockItems = $medications->where('stock_level', '<=', 0)->count();
    $stockValue = $medications->sum(fn ($item) => $item->stock_level * $item->price);
    $costValue = $medications->sum(fn ($item) => $item->stock_level * (float) $item->cost_price);
    $pendingProducts = $stock->where('is_listed', false)->sortBy('name')->values();

    $photoUrl = fn ($item) => $item->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->photo) : null;

    // Everything in stock, for the product form: picking one fills the form
    // from it instead of making a second copy.
    $stockData = $stock->sortBy(fn ($item) => [$item->is_listed ? 1 : 0, mb_strtolower($item->name)])->map(fn ($item) => [
        'id' => $item->id,
        'is_listed' => (bool) $item->is_listed,
        'is_marketplace_available' => (bool) $item->is_marketplace_available,
        'name' => $item->name,
        'category' => $item->category,
        'dosage' => $item->dosage,
        'sku' => $item->sku,
        'barcode' => $item->barcode,
        // Stock that is not listed has no selling price yet: leave the box empty.
        'price' => $item->is_listed ? $item->price : null,
        'cost_price' => $item->cost_price,
        'stock_level' => (int) $item->stock_level,
        'low_stock_threshold' => $item->low_stock_threshold,
        'expiry_date' => $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('Y-m-d') : '',
        'batch_number' => $item->batch_number,
        'description' => $item->description,
        'photo_url' => $photoUrl($item),
        // Shown in the details popup only.
        'expiry_label' => $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('M j, Y') : null,
        'expiry_days' => $item->expiry_date ? (int) floor(now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($item->expiry_date)->startOfDay(), false)) : null,
        'listed_label' => $item->listed_at?->format('M j, Y · g:i A'),
        'created_label' => $item->created_at?->format('M j, Y · g:i A'),
        'updated_label' => $item->updated_at?->diffForHumans(),
        'history_url' => route('vendor.stock.receive', ['tab' => 'history', 'product' => $item->id]),
    ])->values();

    $categoryOptions = collect([
        'Prescription (Rx)', 'OTC Medications', 'Pain & Fever', 'Malaria, Infections & Immunity', 'Cough, Cold & Allergy',
        'Stomach & Digestion', 'Blood, Sugar & Heart', 'Women’s Health', 'Men’s Health', 'Skin & Beauty', 'Eye, Ear & Nose',
        'Vitamins & Supplements', 'Medical Devices', 'Baby & Child Care', 'Personal Care & Hygiene', 'Beverages & Retail', 'Other',
    ])->merge($stock->pluck('category')->filter())->unique()->values();

    $field = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $label = 'mb-1.5 block text-[13px] font-medium text-slate-700';
    $hint = 'mt-1.5 text-xs text-slate-500';
    $optional = '<span class="font-normal text-slate-400">· optional</span>';
@endphp

@section('title', $pageTitle)
@section('header_title', $pageTitle)

@section('content')
<div class="inventory-page mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">Everything you have listed for sale. New stock comes in on the <a href="{{ route('vendor.stock.receive') }}" class="font-medium text-emerald-700 hover:underline">Stock page</a>.</p>
        {{-- On phones the row spans the screen: two small icon buttons, then the two main actions sharing the rest.
             On very narrow phones Add product drops to its own full-width row. --}}
        <div class="no-print flex w-full flex-wrap items-center gap-2 min-[380px]:flex-nowrap sm:w-auto sm:flex-shrink-0">
            <button type="button" data-cura-modal-open="import-modal" title="Import products from a CSV file" aria-label="Import products from a CSV file" class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:text-slate-900 sm:h-10 sm:w-10">
                <i class="fas fa-file-csv"></i>
            </button>
            <button type="button" onclick="window.print()" title="Print this list" aria-label="Print this list" class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 shadow-sm transition hover:border-slate-300 hover:text-slate-900 sm:h-10 sm:w-10">
                <i class="fas fa-print"></i>
            </button>
            <a href="{{ route('vendor.stock.receive') }}" class="inline-flex h-11 min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900 sm:h-10 sm:flex-none sm:px-4">
                <i class="fas fa-truck text-xs text-slate-400"></i> Add stock
            </a>
            <button type="button" data-cura-modal-open="add-med-modal" class="inline-flex h-11 w-full min-w-0 items-center min-[380px]:w-auto min-[380px]:flex-1 justify-center gap-2 whitespace-nowrap rounded-xl bg-emerald-600 px-3 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 sm:h-10 sm:flex-none sm:px-4">
                <i class="fas fa-plus text-xs"></i> Add product
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">That could not be saved:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach (collect($errors->all())->unique() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============ Key numbers ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Products listed</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fas fa-pills text-sm"></i></span>
            </div>
            <p class="mt-3 truncate text-[22px] font-bold leading-none tracking-tight min-[380px]:text-[26px] sm:text-[28px] text-slate-900">{{ number_format($totalItems) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $pendingProducts->count() > 0 ? $pendingProducts->count() . ' more waiting in stock' : 'For sale at the till' }}</p>
        </div>

        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-300">Stock value</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-coins text-sm"></i></span>
            </div>
            <p class="relative mt-3 truncate text-[22px] font-bold leading-none tracking-tight min-[380px]:text-[26px] sm:text-[28px]">₦{{ number_format($stockValue) }}</p>
            <p class="relative mt-2 text-xs text-slate-400">{{ $costValue > 0 ? 'At selling price · cost you ₦' . number_format($costValue) : 'At selling price' }}</p>
        </div>

        <button type="button" id="stat-low-stock" class="rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-amber-300 sm:p-5" title="Show only products running low">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Running low</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="fas fa-exclamation-triangle text-sm"></i></span>
            </div>
            <p class="mt-3 truncate text-[22px] font-bold leading-none tracking-tight min-[380px]:text-[26px] sm:text-[28px] {{ $lowStockItems > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format($lowStockItems) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $lowStockItems > 0 ? 'Tap to see them' : 'Nothing needs restocking' }}</p>
        </button>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Out of stock</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-50 text-rose-600"><i class="fas fa-box-open text-sm"></i></span>
            </div>
            <p class="mt-3 truncate text-[22px] font-bold leading-none tracking-tight min-[380px]:text-[26px] sm:text-[28px] {{ $outOfStockItems > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($outOfStockItems) }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $outOfStockItems > 0 ? 'Cannot be sold right now' : 'Everything is available' }}</p>
        </div>
    </section>

    {{-- ============ Stock waiting to be listed ============ --}}
    @if ($pendingProducts->isNotEmpty())
        <section class="no-print overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-white shadow-sm">
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:p-5">
                <div class="flex items-center gap-3 sm:w-72 sm:flex-shrink-0">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30"><i class="fas fa-tags text-sm"></i></span>
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">{{ $pendingProducts->count() }} in stock, not listed yet</h2>
                        <p class="text-xs text-slate-500">Give {{ $pendingProducts->count() === 1 ? 'it' : 'them' }} a selling price to sell {{ $pendingProducts->count() === 1 ? 'it' : 'them' }}.</p>
                    </div>
                </div>
                <div class="flex flex-1 flex-wrap gap-2">
                    @foreach ($pendingProducts as $pending)
                        <button type="button" data-complete-id="{{ $pending->id }}" class="group inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-3.5 pr-2 text-[13px] font-medium text-slate-800 shadow-sm transition hover:border-emerald-400 hover:text-emerald-800">
                            {{ $pending->name }}
                            <span class="text-xs font-normal text-slate-400">{{ number_format($pending->stock_level) }} in stock</span>
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-100 text-[9px] text-slate-500 transition group-hover:bg-emerald-600 group-hover:text-white"><i class="fas fa-arrow-right"></i></span>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ============ Products ============ --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="no-print border-b border-slate-100 p-3 sm:p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative flex-1">
                    <i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                    <input type="text" id="inventory-search" autocomplete="off" placeholder="Search by name, category, SKU or barcode" class="{{ $field }} pl-10">
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="inventory-category" aria-label="Filter by category" class="h-[42px] rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                        <option value="">All categories</option>
                        @foreach ($medications->pluck('category')->filter()->unique()->sort() as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                    <div class="relative">
                        <i class="far fa-calendar pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                        <select id="inventory-date" aria-label="Filter by date added" class="h-[42px] rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                            <option value="">Added any time</option>
                            <option value="today">Added today</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="month">This month</option>
                            <option value="custom">Choose dates…</option>
                        </select>
                    </div>
                    <button type="button" id="inventory-low-stock" aria-pressed="false" class="inline-flex h-[42px] items-center gap-2 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:border-slate-300">
                        <i class="fas fa-exclamation-triangle text-xs"></i> Low stock
                    </button>
                </div>
            </div>

            {{-- Shown only when "Choose dates…" is picked --}}
            <div id="inventory-date-range" class="mt-3 hidden flex-wrap items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5">
                <span class="text-[13px] font-medium text-slate-600">Added between</span>
                <input type="date" id="inventory-date-from" aria-label="From date" max="{{ now()->toDateString() }}" class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
                <span class="text-[13px] text-slate-400">and</span>
                <input type="date" id="inventory-date-to" aria-label="To date" max="{{ now()->toDateString() }}" class="h-9 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-800 outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
            </div>
        </div>

        @if ($medications->isEmpty())
            <div class="px-6 py-16 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-pills"></i></div>
                <h3 class="mt-4 text-base font-semibold text-slate-900">Nothing listed for sale yet</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                    {{ $pendingProducts->isNotEmpty() ? 'Pick a product from your stock above and give it a selling price.' : 'Add stock first, then list it here with a selling price.' }}
                </p>
                <div class="mt-5 flex justify-center gap-2">
                    <a href="{{ route('vendor.stock.receive') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm hover:border-slate-300"><i class="fas fa-truck text-xs text-slate-400"></i> Add stock</a>
                    <button type="button" data-cura-modal-open="add-med-modal" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"><i class="fas fa-plus text-xs"></i> Add product</button>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead>
                        <tr class="whitespace-nowrap border-b border-slate-100 bg-slate-50/70 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-3 pl-5 pr-3">Product</th>
                            <th class="px-3 py-3">In stock</th>
                            <th class="px-3 py-3">Price</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Date added <i class="fas fa-arrow-down ml-0.5 text-[9px] text-slate-400" title="Newest first"></i></th>
                            <th class="no-print py-3 pl-3 pr-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($medications as $med)
                            @php
                                $out = $med->stock_level <= 0;
                                $low = ! $out && $med->stock_level <= $med->low_stock_threshold;
                                $tone = $out ? 'rose' : ($low ? 'amber' : 'emerald');
                                // The bar is full at four times the alert level, so "low" shows as a short bar.
                                $fill = $out ? 0 : max(6, min(100, (int) round($med->stock_level / max(1, $med->low_stock_threshold * 4) * 100)));
                                $added = $med->listed_at ?? $med->created_at;
                                $photo = $photoUrl($med);
                            @endphp
                            <tr class="group cursor-pointer transition hover:bg-slate-50/70" data-inventory-row data-view-id="{{ $med->id }}" tabindex="0"
                                aria-label="View details of {{ $med->name }}"
                                data-search="{{ mb_strtolower($med->name . ' ' . $med->category . ' ' . $med->dosage . ' ' . $med->sku . ' ' . $med->barcode) }}"
                                data-category="{{ $med->category }}"
                                data-date="{{ $added?->toDateString() }}"
                                data-low="{{ ($out || $low) ? '1' : '0' }}">
                                <td class="py-3 pl-5 pr-3">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-emerald-50 to-emerald-100 text-xs font-bold uppercase text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                            {{ mb_substr(preg_replace('/[^A-Za-z]/', '', $med->name) ?: '?', 0, 2) }}
                                            @if ($photo)
                                                <img src="{{ $photo }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900 group-hover:text-emerald-700">{{ $med->name }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ collect([$med->dosage, $med->category])->filter()->implode(' · ') }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="whitespace-nowrap"><span class="font-semibold text-slate-900">{{ number_format($med->stock_level) }}</span> <span class="text-xs text-slate-400">units</span></p>
                                    <div class="mt-1.5 h-1 w-16 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-{{ $tone }}-500" style="width: {{ $fill }}%"></div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3 font-semibold text-slate-900">₦{{ number_format($med->price, 2) }}</td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $tone }}-50 px-2.5 py-1 text-xs font-medium text-{{ $tone }}-700 ring-1 ring-inset ring-{{ $tone }}-600/15">
                                        <span class="h-1.5 w-1.5 rounded-full bg-{{ $tone }}-500"></span>
                                        {{ $out ? 'Out of stock' : ($low ? 'Low stock' : 'In stock') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3">
                                    <p class="text-slate-700">{{ $added?->format('M j, Y') ?? '—' }}</p>
                                    @if ($added)
                                        <p class="text-xs text-slate-400">{{ $added->isToday() ? 'Today, ' . $added->format('g:i A') : $added->diffForHumans() }}</p>
                                    @endif
                                </td>
                                <td class="no-print whitespace-nowrap py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-0.5">
                                        <button type="button" data-view-id="{{ $med->id }}" title="View details" aria-label="View details of {{ $med->name }}" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-700">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        <button type="button" data-edit-id="{{ $med->id }}" title="Edit product" aria-label="Edit {{ $med->name }}" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-900">
                                            <i class="fas fa-pen text-xs"></i>
                                        </button>
                                        <button type="button" data-delete-id="{{ $med->id }}" title="Delete product" aria-label="Delete {{ $med->name }}" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="inventory-no-match" class="hidden px-6 py-12 text-center">
                <i class="fas fa-search text-2xl text-slate-300"></i>
                <p class="mt-3 text-sm font-semibold text-slate-700">No product matches</p>
                <p class="mt-0.5 text-xs text-slate-500">Try another search, a wider date range, or clear the filters.</p>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-5 py-3 text-xs text-slate-500">
                <span>Showing <span id="inventory-count" class="font-semibold text-slate-700">{{ $totalItems }}</span> of {{ $totalItems }} {{ \Illuminate\Support\Str::plural('product', $totalItems) }} · newest first · click a row for full details</span>
                <button type="button" id="inventory-clear" class="no-print hidden font-medium text-emerald-700 hover:underline">Clear filters</button>
            </div>
        @endif
    </section>
</div>

{{-- ============ Product details (opened by clicking a row) ============ --}}
<x-modal id="details-modal" max-width="2xl" body-class="p-0">
    <div id="details-body"></div>
</x-modal>

{{-- ====================================================================
     Product form: add a new product, list one from stock, or edit one.
     ==================================================================== --}}
<x-modal id="add-med-modal" max-width="3xl" body-class="p-0">
    <form id="add-med-form" action="{{ route('vendor.inventory.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        {{-- Switched to PUT by the page script when saving onto a product that already exists. --}}
        <input type="hidden" name="_method" id="add-med-method" value="POST">

        <datalist id="category-list">
            @foreach ($categoryOptions as $categoryOption)
                <option value="{{ $categoryOption }}"></option>
            @endforeach
        </datalist>

        <div class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30"><i id="add-med-icon" class="fas fa-plus text-sm"></i></span>
                <div class="min-w-0">
                    <h3 id="add-med-title" class="truncate text-base font-semibold text-slate-900">Add product</h3>
                    <p id="add-med-subtitle" class="truncate text-xs text-slate-500">A new product for your inventory</p>
                </div>
            </div>
            <button type="button" data-cura-modal-close aria-label="Close" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="divide-y divide-slate-100 px-6">
            <div id="add-med-completing" class="hidden py-4">
                <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50/70 px-4 py-3">
                    <i class="fas fa-check-circle mt-0.5 text-emerald-600"></i>
                    <div class="text-[13px] text-emerald-900">
                        <p class="font-semibold">Filled in from your stock</p>
                        <p class="mt-0.5 text-emerald-800">Fill in what is missing and save. This updates the same product, it does not create a copy.</p>
                        <button type="button" id="add-med-start-blank" class="mt-1.5 font-semibold text-emerald-700 underline decoration-emerald-300 underline-offset-2 hover:text-emerald-900">Not this product? Start with a blank form</button>
                    </div>
                </div>
            </div>

            {{-- Product --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Product</h4>
                    <p class="mt-0.5 text-xs text-slate-500">What it is and how it is packed.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="relative sm:col-span-2">
                        <label for="add-med-name" class="{{ $label }}">Name</label>
                        <input type="text" name="name" id="add-med-name" required autocomplete="off" class="{{ $field }}" placeholder="{{ $stock->isNotEmpty() ? 'Pick from your stock, or type a new name' : 'e.g. Amoxicillin 500mg' }}">
                        {{-- Every product already in stock. Picking one fills the form from it. --}}
                        <div id="add-med-suggestions" class="absolute left-0 right-0 top-full z-20 mt-1.5 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
                            <p class="border-b border-slate-100 bg-slate-50 px-3.5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">In your stock · pick one to fill the form</p>
                            <div id="add-med-suggestion-list" class="max-h-56 overflow-y-auto py-1"></div>
                            <p id="add-med-new-hint" class="hidden border-t border-slate-100 bg-emerald-50 px-3.5 py-2 text-xs font-medium text-emerald-800"></p>
                        </div>
                    </div>
                    <div>
                        <label for="add-med-category" class="{{ $label }}">Category</label>
                        <input type="text" name="category" id="add-med-category" list="category-list" required autocomplete="off" class="{{ $field }}" placeholder="Pick or type one">
                    </div>
                    <div>
                        <label for="add-med-dosage" class="{{ $label }}">Dosage / size {!! $optional !!}</label>
                        <input type="text" name="dosage" id="add-med-dosage" class="{{ $field }}" placeholder="e.g. 500mg capsule, 100ml bottle">
                    </div>
                </div>
            </div>

            {{-- Pricing --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Pricing</h4>
                    <p class="mt-0.5 text-xs text-slate-500">What customers pay, and what it cost you.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="add-med-price" class="{{ $label }}">Selling price</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">₦</span>
                            <input type="number" name="price" id="add-med-price" required min="0" step="0.01" inputmode="decimal" class="{{ $field }} pl-8" placeholder="0.00">
                        </div>
                    </div>
                    <div>
                        <label for="add-med-cost" class="{{ $label }}">Cost price {!! $optional !!}</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">₦</span>
                            <input type="number" name="cost_price" id="add-med-cost" min="0" step="0.01" inputmode="decimal" class="{{ $field }} pl-8" placeholder="0.00">
                        </div>
                    </div>
                    <p id="add-med-margin" class="hidden rounded-xl bg-slate-50 px-3.5 py-2.5 text-[13px] text-slate-600 sm:col-span-2"></p>
                </div>
            </div>

            {{-- Stock --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Stock</h4>
                    <p class="mt-0.5 text-xs text-slate-500">How many you have and when to be warned.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label id="add-med-stock-label" for="add-med-stock" class="{{ $label }}">Opening stock</label>
                        <input type="number" name="stock_level" id="add-med-stock" required min="0" step="1" inputmode="numeric" class="{{ $field }} read-only:cursor-not-allowed read-only:bg-slate-50 read-only:text-slate-500" placeholder="0">
                        <p id="add-med-stock-hint" class="{{ $hint }}">Units on the shelf right now.</p>
                    </div>
                    <div>
                        <label for="add-med-threshold" class="{{ $label }}">Low-stock alert at</label>
                        <input type="number" name="low_stock_threshold" id="add-med-threshold" required min="0" step="1" inputmode="numeric" value="10" class="{{ $field }}" placeholder="10">
                        <p class="{{ $hint }}">You are warned at or below this.</p>
                    </div>
                    <div>
                        <label for="add-med-batch" class="{{ $label }}">Batch number {!! $optional !!}</label>
                        <input type="text" name="batch_number" id="add-med-batch" maxlength="100" class="{{ $field }} font-mono" placeholder="From the pack">
                    </div>
                    <div>
                        <label for="add-med-expiry" class="{{ $label }}">Expiry date {!! $optional !!}</label>
                        <input type="date" name="expiry_date" id="add-med-expiry" class="{{ $field }}">
                    </div>
                </div>
            </div>

            {{-- Codes --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Codes</h4>
                    <p class="mt-0.5 text-xs text-slate-500">Used to find it fast at the till.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="add-med-barcode" class="{{ $label }}">Barcode {!! $optional !!}</label>
                        <div class="relative">
                            <i class="fas fa-barcode pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="barcode" id="add-med-barcode" maxlength="100" autocomplete="off" class="{{ $field }} pl-10 font-mono" placeholder="Click here, then scan the pack" onkeydown="if (event.key === 'Enter') event.preventDefault()">
                        </div>
                    </div>
                    <div>
                        <label for="add-med-sku" class="{{ $label }}">SKU {!! $optional !!}</label>
                        <input type="text" name="sku" id="add-med-sku" maxlength="50" autocomplete="off" class="{{ $field }} font-mono" placeholder="Blank = made for you">
                    </div>
                </div>
            </div>

            {{-- Photo & description --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Photo & details</h4>
                    <p class="mt-0.5 text-xs text-slate-500">Shown at the till and to online customers.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-[132px_minmax(0,1fr)]">
                    <div>
                        <span class="{{ $label }}">Photo</span>
                        <label for="add-med-photo" id="add-med-photo-box" class="group relative flex h-[132px] w-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 text-center transition hover:border-emerald-400 hover:bg-emerald-50/50 sm:w-[132px]">
                            <img id="add-med-photo-preview" src="" alt="" class="absolute inset-0 hidden h-full w-full object-cover">
                            <span id="add-med-photo-empty" class="px-2">
                                <i class="fas fa-image text-xl text-slate-300 transition group-hover:text-emerald-500"></i>
                                <span class="mt-1.5 block text-xs font-medium text-slate-600">Add photo</span>
                                <span class="block text-[10px] text-slate-400">Up to 2MB</span>
                            </span>
                            <span id="add-med-photo-change" class="absolute inset-x-0 bottom-0 hidden bg-slate-900/70 py-1 text-[11px] font-medium text-white">Change</span>
                        </label>
                        <input type="file" name="photo" id="add-med-photo" accept="image/*" class="sr-only">
                        <p id="add-med-photo-error" class="mt-1.5 hidden text-xs font-medium text-rose-600"></p>
                    </div>
                    <div>
                        <label for="add-med-description" class="{{ $label }}">Description {!! $optional !!}</label>
                        <textarea name="description" id="add-med-description" rows="5" class="{{ $field }} h-[132px] resize-none" placeholder="What it is for, how to use it, warnings"></textarea>
                    </div>
                </div>
            </div>

            {{-- Online --}}
            <div class="grid gap-x-8 gap-y-4 py-5 md:grid-cols-[170px_minmax(0,1fr)]">
                <div>
                    <h4 class="text-sm font-semibold text-slate-900">Selling online</h4>
                    <p class="mt-0.5 text-xs text-slate-500">It is always for sale in store.</p>
                </div>
                <label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 transition hover:border-slate-300">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Show on the marketplace</span>
                        <span class="block text-xs text-slate-500">Online customers can find and order this product.</span>
                    </span>
                    <input type="hidden" name="is_marketplace_available" value="0">
                    <input type="checkbox" name="is_marketplace_available" value="1" checked class="peer sr-only">
                    <span class="relative h-6 w-11 flex-shrink-0 rounded-full bg-slate-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-emerald-600 peer-checked:after:translate-x-5 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-500/20"></span>
                </label>
            </div>
        </div>

        <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
            <button type="button" data-cura-modal-close class="h-10 rounded-xl px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</button>
            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                <i class="fas fa-check text-xs"></i> <span id="add-med-submit">Add product</span>
            </button>
        </div>
    </form>
</x-modal>

{{-- ============ Stock correction ============ --}}
<x-modal id="stock-modal" max-width="md" :z-index="110" body-class="p-0">
    <form id="stock-form" method="POST">
        @csrf
        <div class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-6 py-4">
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-slate-900">Correct stock count</h3>
                <p id="stock-med-name" class="truncate text-xs text-slate-500"></p>
            </div>
            <button type="button" data-cura-modal-close aria-label="Close" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>

        <div class="space-y-5 px-6 py-5">
            <div class="flex items-center justify-between rounded-xl bg-slate-900 px-4 py-3.5 text-white">
                <div>
                    <p class="text-xs text-slate-400">In stock now</p>
                    <p id="stock-current" class="text-2xl font-bold tracking-tight">0</p>
                </div>
                <i class="fas fa-arrow-right text-slate-500"></i>
                <div class="text-right">
                    <p class="text-xs text-slate-400">After this change</p>
                    <p id="stock-after" class="text-2xl font-bold tracking-tight text-emerald-400">0</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="add" required checked class="peer sr-only">
                    <span class="flex items-center justify-center gap-2 rounded-lg py-2 text-sm font-semibold text-slate-600 transition peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-sm"><i class="fas fa-plus text-xs"></i> Add</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="subtract" required class="peer sr-only">
                    <span class="flex items-center justify-center gap-2 rounded-lg py-2 text-sm font-semibold text-slate-600 transition peer-checked:bg-white peer-checked:text-rose-600 peer-checked:shadow-sm"><i class="fas fa-minus text-xs"></i> Remove</span>
                </label>
            </div>

            <div>
                <label for="stock-quantity" class="{{ $label }}">How many units?</label>
                <input type="number" name="adjustment" id="stock-quantity" required min="1" step="1" inputmode="numeric" class="{{ $field }}" placeholder="0">
            </div>

            <div>
                <label for="stock-reason" class="{{ $label }}">Reason</label>
                <div class="mb-2 flex flex-wrap gap-1.5">
                    @foreach (['Count correction', 'Damaged', 'Expired', 'Lost or stolen', 'Found extra'] as $quick)
                        <button type="button" data-reason class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-400 hover:text-slate-900">{{ $quick }}</button>
                    @endforeach
                </div>
                <input type="text" name="reason" id="stock-reason" maxlength="255" class="{{ $field }}" placeholder="Pick one above or type your own">
                <p class="{{ $hint }}">Saved in Stock history. For a delivery, use the Stock page instead.</p>
            </div>
        </div>

        <div class="sticky bottom-0 z-10 flex items-center justify-end gap-2 border-t border-slate-100 bg-white px-6 py-4">
            <button type="button" data-cura-modal-close class="h-10 rounded-xl px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</button>
            <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Save correction</button>
        </div>
    </form>
</x-modal>

{{-- ============ Delete confirmation ============ --}}
<x-modal id="delete-modal" max-width="sm" :z-index="110" body-class="p-0">
    <form id="delete-form" method="POST" class="p-6 text-center">
        @csrf
        @method('DELETE')
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 text-rose-600"><i class="fas fa-trash-alt"></i></div>
        <h3 class="mt-4 text-base font-semibold text-slate-900">Delete this product?</h3>
        <p class="mt-1 text-sm text-slate-500"><span id="delete-name" class="font-medium text-slate-800"></span> will be removed from your inventory and stock. <span id="delete-stock-warning"></span>Past sales stay on record.</p>
        <div class="mt-6 grid grid-cols-2 gap-2">
            <button type="button" data-cura-modal-close class="h-10 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Keep it</button>
            <button type="submit" class="h-10 rounded-xl bg-rose-600 text-sm font-semibold text-white transition hover:bg-rose-700">Delete</button>
        </div>
    </form>
</x-modal>

{{-- ============ CSV import ============ --}}
<x-modal id="import-modal" max-width="2xl" :z-index="110" body-class="p-0">
    <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white"><i class="fas fa-file-csv text-sm"></i></span>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-slate-900">Import products</h3>
                <p id="import-step-subtitle" class="truncate text-xs text-slate-500">Any column order or naming — we'll figure it out</p>
            </div>
        </div>
        <button type="button" data-cura-modal-close aria-label="Close" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-times"></i></button>
    </div>

    <div id="import-error" class="mx-6 mt-5 hidden rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"></div>

    <!-- Step 1: upload -->
    <div id="import-step-upload" class="px-6 py-5">
        <p class="text-sm leading-relaxed text-slate-600">
            Upload any CSV sheet of your products, even if the columns are out of order or renamed. We read both the headings and the data, then show you how each column will be used before anything is saved.
        </p>
        <div class="group mt-4 cursor-pointer rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center transition hover:border-emerald-400 hover:bg-emerald-50/50" onclick="document.getElementById('csv_file_input').click()">
            <i class="fas fa-cloud-upload-alt text-3xl text-slate-300 transition group-hover:text-emerald-500"></i>
            <p id="import-file-label" class="mt-3 text-sm font-medium text-slate-600">Drop manifest here or click to browse</p>
            <input type="file" id="csv_file_input" class="hidden" accept=".csv,text/csv">
        </div>
        <div class="mt-5 flex items-center justify-end gap-2">
            <button type="button" data-cura-modal-close class="h-10 rounded-xl px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</button>
            <button type="button" id="import-analyze-btn" disabled class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none">
                <span id="import-analyze-btn-label">Analyze File</span>
            </button>
        </div>
    </div>

    <!-- Step 2: confirm mapping -->
    <div id="import-step-mapping" class="hidden px-6 py-5">
        <p class="mb-4 text-sm leading-relaxed text-slate-600">
            Here's how we matched your columns. Anything <span class="font-semibold text-amber-600">flagged</span> is a lower-confidence guess — double-check it before importing.
        </p>

        <div id="import-mapping-rows" class="mb-5 space-y-2"></div>

        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Data preview</p>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-xs">
                <thead id="import-preview-head" class="bg-slate-50 font-semibold uppercase tracking-wider text-slate-500"></thead>
                <tbody id="import-preview-body" class="divide-y divide-slate-100"></tbody>
            </table>
        </div>

        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="button" id="import-back-btn" class="text-sm font-medium text-slate-500 transition hover:text-slate-900"><i class="fas fa-arrow-left mr-1 text-xs"></i> Choose a different file</button>
            <button type="button" id="import-confirm-btn" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                <span id="import-confirm-btn-label">Confirm &amp; Import</span>
            </button>
        </div>
    </div>
</x-modal>

<script>
// Every product record on this page, shared by the blocks below.
const inventoryProducts = @json($stockData);

// ---- Product details: everything about one product, opened from its row ----
(function () {
    const products = new Map(inventoryProducts.map((item) => [item.id, item]));
    const body = document.getElementById('details-body');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const money = (amount) => '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dash = '<span class="text-slate-300">—</span>';

    const fact = (label, value) => `
        <div class="flex items-baseline justify-between gap-4 py-2.5">
            <dt class="flex-shrink-0 text-[13px] text-slate-500">${label}</dt>
            <dd class="min-w-0 text-right text-[13px] font-medium text-slate-900">${value}</dd>
        </div>`;

    function render(product) {
        const stock = product.stock_level;
        const threshold = Number(product.low_stock_threshold) || 0;
        const out = stock <= 0;
        const low = !out && stock <= threshold;
        const tone = out ? 'rose' : (low ? 'amber' : 'emerald');
        const price = Number(product.price) || 0;
        const hasCost = product.cost_price !== null && product.cost_price !== '';
        const cost = Number(product.cost_price) || 0;
        const profit = price - cost;
        const initials = escapeHtml((product.name.replace(/[^A-Za-z]/g, '') || '?').slice(0, 2));

        let expiry = dash;
        if (product.expiry_label) {
            expiry = escapeHtml(product.expiry_label);
            if (product.expiry_days < 0) expiry += ' <span class="ml-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">Expired</span>';
            else if (product.expiry_days <= 90) expiry += ` <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">${product.expiry_days} ${product.expiry_days === 1 ? 'day' : 'days'} left</span>`;
        }

        body.innerHTML = `
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                <div class="flex min-w-0 items-center gap-3.5">
                    <div class="relative flex h-14 w-14 flex-shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100 text-base font-bold uppercase text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                        ${initials}
                        ${product.photo_url ? `<img src="${escapeHtml(product.photo_url)}" alt="" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">` : ''}
                    </div>
                    <div class="min-w-0">
                        <h3 class="truncate text-lg font-semibold tracking-tight text-slate-900">${escapeHtml(product.name)}</h3>
                        <p class="truncate text-[13px] text-slate-500">${escapeHtml([product.dosage, product.category].filter(Boolean).join(' · ')) || 'No category'}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-${tone}-50 px-2.5 py-0.5 text-xs font-medium text-${tone}-700 ring-1 ring-inset ring-${tone}-600/15">
                                <span class="h-1.5 w-1.5 rounded-full bg-${tone}-500"></span>${out ? 'Out of stock' : (low ? 'Low stock' : 'In stock')}
                            </span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">${product.is_marketplace_available ? '<i class="fas fa-globe mr-1 text-[10px]"></i>On the marketplace' : '<i class="fas fa-store mr-1 text-[10px]"></i>In-store only'}</span>
                        </div>
                    </div>
                </div>
                <button type="button" data-cura-modal-close aria-label="Close" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-times"></i></button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-xl border border-slate-200 p-3.5">
                        <p class="text-xs text-slate-500">Selling price</p>
                        <p class="mt-1 text-lg font-semibold tracking-tight text-slate-900">${money(price)}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-3.5">
                        <p class="text-xs text-slate-500">In stock</p>
                        <p class="mt-1 text-lg font-semibold tracking-tight text-${out ? 'rose-600' : (low ? 'amber-600' : 'slate-900')}">${stock.toLocaleString('en-NG')} <span class="text-xs font-normal text-slate-400">units</span></p>
                    </div>
                    <div class="rounded-xl bg-slate-900 p-3.5 text-white">
                        <p class="text-xs text-slate-400">Stock value</p>
                        <p class="mt-1 text-lg font-semibold tracking-tight">${money(price * stock)}</p>
                    </div>
                </div>

                <div class="grid gap-x-8 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pricing</h4>
                        <dl class="divide-y divide-slate-100">
                            ${fact('Cost price', hasCost ? money(cost) : dash)}
                            ${fact('Profit per unit', hasCost ? `<span class="${profit >= 0 ? 'text-emerald-700' : 'text-rose-600'}">${money(profit)}</span>` : dash)}
                            ${fact('Margin', hasCost && price > 0 ? `<span class="${profit >= 0 ? 'text-emerald-700' : 'text-rose-600'}">${Math.round(profit / price * 100)}%</span>` : dash)}
                        </dl>

                        <h4 class="mb-1 mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Stock</h4>
                        <dl class="divide-y divide-slate-100">
                            ${fact('Low-stock alert at', threshold.toLocaleString('en-NG') + ' units')}
                            ${fact('Batch number', product.batch_number ? `<span class="font-mono">${escapeHtml(product.batch_number)}</span>` : dash)}
                            ${fact('Expiry date', expiry)}
                        </dl>
                    </div>
                    <div class="mt-5 sm:mt-0">
                        <h4 class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Codes</h4>
                        <dl class="divide-y divide-slate-100">
                            ${fact('SKU', product.sku ? `<span class="font-mono">${escapeHtml(product.sku)}</span>` : dash)}
                            ${fact('Barcode', product.barcode ? `<span class="font-mono"><i class="fas fa-barcode mr-1 text-slate-400"></i>${escapeHtml(product.barcode)}</span>` : dash)}
                        </dl>

                        <h4 class="mb-1 mt-5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Dates</h4>
                        <dl class="divide-y divide-slate-100">
                            ${fact('Added to inventory', escapeHtml(product.listed_label) || dash)}
                            ${fact('First put in stock', escapeHtml(product.created_label) || dash)}
                            ${fact('Last changed', escapeHtml(product.updated_label) || dash)}
                        </dl>
                    </div>
                </div>

                <div>
                    <h4 class="mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Description</h4>
                    ${product.description
                        ? `<p class="whitespace-pre-line rounded-xl bg-slate-50 px-4 py-3 text-sm leading-relaxed text-slate-700">${escapeHtml(product.description)}</p>`
                        : '<p class="rounded-xl border border-dashed border-slate-200 px-4 py-3 text-sm text-slate-400">No description yet. Add one with Edit.</p>'}
                </div>
            </div>

            <div class="sticky bottom-0 z-10 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                <a href="${escapeHtml(product.history_url)}" class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                    <i class="fas fa-history text-xs"></i> Stock history
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" data-delete-id="${product.id}" aria-label="Delete this product" title="Delete" class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-trash-alt text-sm"></i></button>
                    <button type="button" data-adjust-id="${product.id}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300"><i class="fas fa-sliders-h text-xs text-slate-400"></i> Correct stock</button>
                    <button type="button" data-edit-id="${product.id}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"><i class="fas fa-pen text-xs"></i> Edit</button>
                </div>
            </div>`;
    }

    function open(id) {
        const product = products.get(Number(id));
        if (!product) return;
        render(product);
        CuraModal.open('details-modal');
    }

    // A click anywhere on a row opens its details, except on the row's own
    // action buttons, which do their own job.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-view-id]');
        if (!trigger) return;
        if (trigger.tagName === 'TR' && event.target.closest('button, a')) return;
        open(trigger.dataset.viewId);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.matches('tr[data-view-id]')) open(event.target.dataset.viewId);
    });
})();

// ---- Product form: add new, list one from stock, or edit ----
(function () {
    const stockProducts = inventoryProducts;
    const form = document.getElementById('add-med-form');
    const method = document.getElementById('add-med-method');
    const nameInput = document.getElementById('add-med-name');
    const stockInput = document.getElementById('add-med-stock');
    const priceInput = document.getElementById('add-med-price');
    const costInput = document.getElementById('add-med-cost');
    const marginEl = document.getElementById('add-med-margin');
    const suggestions = document.getElementById('add-med-suggestions');
    const suggestionList = document.getElementById('add-med-suggestion-list');
    const photoInput = document.getElementById('add-med-photo');
    const photoPreview = document.getElementById('add-med-photo-preview');
    const storeUrl = form.action;
    const updateUrl = "{{ route('vendor.inventory') }}/";

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const money = (amount) => '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const setField = (name, value) => { const input = form.elements[name]; if (input && input.type !== 'file') input.value = value ?? ''; };

    // One form, three jobs. Only the wording, the target and the stock box differ.
    function setMode(mode, product = null) {
        const existing = mode !== 'new';
        const text = {
            new: ['Add product', 'A new product for your inventory', 'Add product', 'fa-plus'],
            list: ['List product for sale', 'Already in your stock', 'List product', 'fa-tags'],
            edit: ['Edit product', product ? product.name : '', 'Save changes', 'fa-pen'],
        }[mode];

        document.getElementById('add-med-title').textContent = text[0];
        document.getElementById('add-med-subtitle').textContent = text[1];
        document.getElementById('add-med-submit').textContent = text[2];
        document.getElementById('add-med-icon').className = `fas ${text[3]} text-sm`;
        document.getElementById('add-med-completing').classList.toggle('hidden', mode !== 'list');
        document.getElementById('add-med-stock-label').textContent = existing ? 'In stock' : 'Opening stock';
        document.getElementById('add-med-stock-hint').textContent = existing
            ? 'Changed by deliveries, sales and stock corrections.'
            : 'Units on the shelf right now.';
        stockInput.readOnly = existing;
        suggestions.classList.add('hidden');
    }

    function showPhoto(url) {
        photoPreview.src = url || '';
        photoPreview.classList.toggle('hidden', !url);
        document.getElementById('add-med-photo-empty').classList.toggle('hidden', !!url);
        document.getElementById('add-med-photo-change').classList.toggle('hidden', !url);
        photoProblem('');
    }

    // The red note under the photo box. An empty message clears it.
    function photoProblem(message) {
        const note = document.getElementById('add-med-photo-error');
        const box = document.getElementById('add-med-photo-box');
        note.textContent = message;
        note.classList.toggle('hidden', !message);
        box.classList.toggle('border-rose-400', !!message);
        box.classList.toggle('bg-rose-50', !!message);
        box.classList.toggle('border-slate-200', !message);
        box.classList.toggle('bg-slate-50', !message);
        return box;
    }

    function showMargin() {
        const price = parseFloat(priceInput.value);
        const cost = parseFloat(costInput.value);

        if (!(price > 0) || isNaN(cost)) {
            marginEl.classList.add('hidden');
            return;
        }

        const profit = price - cost;
        const percent = Math.round(profit / price * 100);
        marginEl.innerHTML = profit >= 0
            ? `You make <span class="font-semibold text-emerald-700">${money(profit)}</span> on each one sold <span class="text-slate-400">·</span> <span class="font-semibold text-emerald-700">${percent}%</span> margin`
            : `<span class="font-semibold text-rose-600">You lose ${money(-profit)} on each one sold.</span> The selling price is below the cost.`;
        marginEl.classList.remove('hidden');
    }

    function startBlank() {
        form.reset();
        form.action = storeUrl;
        method.value = 'POST';
        showPhoto(null);
        showMargin();
        setMode('new');
    }

    // Fill the form from a product that already exists, listed or not.
    function startExisting(id) {
        const product = stockProducts.find((item) => item.id === Number(id));
        if (!product) return;

        form.reset();
        form.action = updateUrl + product.id;
        method.value = 'PUT';

        ['name', 'category', 'dosage', 'sku', 'barcode', 'price', 'cost_price', 'stock_level', 'low_stock_threshold', 'expiry_date', 'batch_number', 'description']
            .forEach((field) => setField(field, product[field]));

        // Stock being listed for the first time goes on the marketplace by
        // default; a product already listed keeps the choice it had.
        form.querySelector('input[type="checkbox"][name="is_marketplace_available"]').checked = !product.is_listed || product.is_marketplace_available;

        showPhoto(product.photo_url);
        showMargin();
        setMode(product.is_listed ? 'edit' : 'list', product);
    }

    function openExisting(id) {
        startExisting(id);
        CuraModal.open('add-med-modal');
        if (method.value === 'PUT' && !priceInput.value) setTimeout(() => priceInput.focus(), 350);
    }

    function showSuggestions() {
        // Only while adding: once a product is picked the list has done its job.
        if (method.value === 'PUT' || stockProducts.length === 0) return;

        const typed = nameInput.value.trim();
        const needle = typed.toLowerCase();
        const found = stockProducts.filter((item) =>
            needle === ''
            || item.name.toLowerCase().includes(needle)
            || (item.sku || '').toLowerCase().includes(needle)
            || (item.barcode || '').toLowerCase().includes(needle)
        );

        suggestionList.innerHTML = found.map((item) => `
            <button type="button" data-id="${item.id}" class="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left transition hover:bg-slate-50">
                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-slate-900">${escapeHtml(item.name)}</span>
                    <span class="block truncate text-xs text-slate-500">${escapeHtml([item.dosage, item.category].filter(Boolean).join(' · '))}</span>
                </span>
                <span class="flex flex-shrink-0 items-center gap-2 text-xs">
                    <span class="text-slate-500">${item.stock_level} in stock</span>
                    ${item.is_listed
                        ? '<span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Listed</span>'
                        : '<span class="rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/15">Not listed</span>'}
                </span>
            </button>`).join('');

        // Typing a name that is not in stock simply adds a new product.
        const hint = document.getElementById('add-med-new-hint');
        const exact = stockProducts.some((item) => item.name.toLowerCase() === needle);
        hint.classList.toggle('hidden', typed === '' || exact);
        hint.textContent = found.length === 0
            ? `“${typed}” is not in your stock. Carry on to add it as a new product.`
            : `Not one of these? Carry on to add “${typed}” as a new product.`;

        suggestionList.classList.toggle('hidden', found.length === 0);
        suggestions.classList.toggle('hidden', found.length === 0 && typed === '');
    }

    nameInput.addEventListener('focus', showSuggestions);
    nameInput.addEventListener('input', showSuggestions);
    suggestionList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-id]');
        if (button) startExisting(button.dataset.id);
    });
    document.addEventListener('mousedown', (event) => {
        if (!event.target.closest('#add-med-suggestions') && event.target !== nameInput) suggestions.classList.add('hidden');
    });

    priceInput.addEventListener('input', showMargin);
    costInput.addEventListener('input', showMargin);

    photoInput.addEventListener('change', () => {
        if (!photoInput.files.length) return;
        const file = photoInput.files[0];
        if (file.size > 2 * 1024 * 1024) {
            photoInput.value = '';
            photoProblem('That photo is over 2MB. Pick a smaller one.');
            return;
        }
        showPhoto(URL.createObjectURL(file));
    });

    // A photo is compulsory: either one is picked now, or the product already has one.
    form.addEventListener('submit', (event) => {
        if (photoInput.files.length || !photoPreview.classList.contains('hidden')) return;
        event.preventDefault();
        photoProblem('Add a photo of the product before saving.').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    document.getElementById('add-med-start-blank').addEventListener('click', () => { startBlank(); nameInput.focus(); });

    // "Add product" always opens a blank form, whatever was in it last time.
    document.querySelectorAll('[data-cura-modal-open="add-med-modal"]').forEach((button) => button.addEventListener('click', startBlank));

    // Stock waiting to be listed, and the pencil on each row.
    document.querySelectorAll('[data-complete-id]').forEach((button) => button.addEventListener('click', () => openExisting(button.dataset.completeId)));
    // Delegated, because the Edit button inside the details popup is created on the fly.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-edit-id]');
        if (!button) return;
        CuraModal.close('details-modal');
        openExisting(button.dataset.editId);
    });

    // Arriving from the Stock page's "List for sale" button (?list=ID).
    const listId = Number(new URLSearchParams(location.search).get('list'));
    if (listId && stockProducts.some((item) => item.id === listId)) {
        document.addEventListener('DOMContentLoaded', () => openExisting(listId));
    }
})();

// ---- Stock correction and delete ----
(function () {
    const base = "{{ route('vendor.inventory') }}/";

    const stockForm = document.getElementById('stock-form');
    const quantity = document.getElementById('stock-quantity');
    const afterEl = document.getElementById('stock-after');
    let current = 0;

    function showAfter() {
        const amount = Math.max(0, parseInt(quantity.value, 10) || 0);
        const adding = stockForm.elements.type.value === 'add';
        const after = adding ? current + amount : Math.max(0, current - amount);

        afterEl.textContent = after.toLocaleString('en-NG');
        afterEl.className = 'text-2xl font-bold tracking-tight ' + (amount === 0 ? 'text-slate-400' : (adding ? 'text-emerald-400' : 'text-rose-400'));
    }

    const productFor = (id) => inventoryProducts.find((item) => item.id === Number(id));

    // Delegated, because these buttons also live inside the details popup.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-adjust-id]');
        const product = button && productFor(button.dataset.adjustId);
        if (!product) return;

        current = product.stock_level;
        stockForm.reset();
        stockForm.action = base + product.id + '/adjust';
        document.getElementById('stock-med-name').textContent = product.name;
        document.getElementById('stock-current').textContent = current.toLocaleString('en-NG');
        showAfter();
        CuraModal.close('details-modal');
        CuraModal.open('stock-modal');
        setTimeout(() => quantity.focus(), 350);
    });

    quantity.addEventListener('input', showAfter);
    stockForm.querySelectorAll('input[name="type"]').forEach((radio) => radio.addEventListener('change', showAfter));
    stockForm.querySelectorAll('[data-reason]').forEach((chip) => chip.addEventListener('click', () => {
        document.getElementById('stock-reason').value = chip.textContent.trim();
    }));

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-delete-id]');
        const product = button && productFor(button.dataset.deleteId);
        if (!product) return;

        const units = product.stock_level;
        document.getElementById('delete-form').action = base + product.id;
        document.getElementById('delete-name').textContent = product.name;
        document.getElementById('delete-stock-warning').textContent = units > 0 ? `It still has ${units.toLocaleString('en-NG')} unit(s) in stock. ` : '';
        CuraModal.close('details-modal');
        CuraModal.open('delete-modal');
    });
})();

// ---- Search and filters above the table (works on what is already loaded) ----
(function () {
    const search = document.getElementById('inventory-search');
    const category = document.getElementById('inventory-category');
    const lowStock = document.getElementById('inventory-low-stock');
    const noMatch = document.getElementById('inventory-no-match');
    const count = document.getElementById('inventory-count');
    const clear = document.getElementById('inventory-clear');
    const dateFilter = document.getElementById('inventory-date');
    const dateRange = document.getElementById('inventory-date-range');
    const dateFrom = document.getElementById('inventory-date-from');
    const dateTo = document.getElementById('inventory-date-to');
    const rows = Array.from(document.querySelectorAll('[data-inventory-row]'));
    if (!search || rows.length === 0) return;

    let lowOnly = false;

    // Dates are compared as YYYY-MM-DD text, which sorts the same as the dates.
    const isoDay = (date) => date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    const daysAgo = (days) => { const date = new Date(); date.setDate(date.getDate() - days); return isoDay(date); };

    // The first and last day (inclusive) the date filter allows; '' = open.
    function dateBounds() {
        const today = isoDay(new Date());
        switch (dateFilter.value) {
            case 'today': return [today, today];
            case '7': return [daysAgo(6), today];
            case '30': return [daysAgo(29), today];
            case 'month': return [today.slice(0, 8) + '01', today];
            case 'custom': return [dateFrom.value, dateTo.value];
            default: return ['', ''];
        }
    }

    function applyFilters() {
        const needle = search.value.trim().toLowerCase();
        const [from, to] = dateBounds();
        const dateActive = from !== '' || to !== '';
        let visible = 0;

        rows.forEach((row) => {
            const added = row.dataset.date;
            const show = (needle === '' || row.dataset.search.includes(needle))
                && (category.value === '' || row.dataset.category === category.value)
                && (!lowOnly || row.dataset.low === '1')
                && (!dateActive || (added !== '' && (from === '' || added >= from) && (to === '' || added <= to)));

            row.classList.toggle('hidden', !show);
            if (show) visible++;
        });

        dateRange.classList.toggle('hidden', dateFilter.value !== 'custom');
        dateRange.classList.toggle('flex', dateFilter.value === 'custom');

        count.textContent = visible;
        noMatch.classList.toggle('hidden', visible > 0);
        clear.classList.toggle('hidden', needle === '' && category.value === '' && !lowOnly && dateFilter.value === '');

        lowStock.setAttribute('aria-pressed', lowOnly ? 'true' : 'false');
        lowStock.classList.toggle('border-amber-400', lowOnly);
        lowStock.classList.toggle('bg-amber-50', lowOnly);
        lowStock.classList.toggle('text-amber-800', lowOnly);
        lowStock.classList.toggle('border-slate-200', !lowOnly);
        lowStock.classList.toggle('bg-white', !lowOnly);
        lowStock.classList.toggle('text-slate-700', !lowOnly);
    }

    search.addEventListener('input', applyFilters);
    category.addEventListener('change', applyFilters);
    dateFilter.addEventListener('change', () => { applyFilters(); if (dateFilter.value === 'custom') dateFrom.focus(); });
    dateFrom.addEventListener('change', applyFilters);
    dateTo.addEventListener('change', applyFilters);
    lowStock.addEventListener('click', () => { lowOnly = !lowOnly; applyFilters(); });
    document.getElementById('stat-low-stock').addEventListener('click', () => { lowOnly = true; applyFilters(); search.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
    clear.addEventListener('click', () => { search.value = ''; category.value = ''; dateFilter.value = ''; dateFrom.value = ''; dateTo.value = ''; lowOnly = false; applyFilters(); });
})();

// ---- Smart CSV bulk import: analyze -> confirm mapping -> commit ----
(function () {
    const FIELD_LABELS = {
        name: 'Name', category: 'Category', dosage: 'Dosage', price: 'Price',
        stock_level: 'Stock Level', low_stock_threshold: 'Low Stock Threshold',
        expiry_date: 'Expiry Date', description: 'Description',
    };
    const REQUIRED_FIELDS = ['name', 'price', 'stock_level'];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const fileInput = document.getElementById('csv_file_input');
    const analyzeBtn = document.getElementById('import-analyze-btn');
    const analyzeBtnLabel = document.getElementById('import-analyze-btn-label');
    const fileLabel = document.getElementById('import-file-label');
    const errorBox = document.getElementById('import-error');
    const stepUpload = document.getElementById('import-step-upload');
    const stepMapping = document.getElementById('import-step-mapping');
    const subtitle = document.getElementById('import-step-subtitle');
    const confirmBtn = document.getElementById('import-confirm-btn');
    const confirmBtnLabel = document.getElementById('import-confirm-btn-label');

    let importState = { token: null, mapping: {} };

    function showMessage(message, isError) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden', 'bg-red-50', 'border-red-100', 'text-red-700', 'bg-emerald-50', 'border-emerald-100', 'text-emerald-700');
        errorBox.classList.add(...(isError ? ['bg-red-50', 'border-red-100', 'text-red-700'] : ['bg-emerald-50', 'border-emerald-100', 'text-emerald-700']));
    }

    function clearMessage() {
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
    }

    function resetImportModal() {
        fileInput.value = '';
        fileLabel.textContent = 'Drop manifest here or click to browse';
        analyzeBtn.disabled = true;
        clearMessage();
        stepMapping.classList.add('hidden');
        stepUpload.classList.remove('hidden');
        subtitle.textContent = "Any column order or naming — we'll figure it out";
        importState = { token: null, mapping: {} };
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function describeSource(detected) {
        if (!detected || detected.column === null) return 'Not detected';
        const pct = Math.round((detected.confidence || 0) * 100);
        const labels = {
            exact_header: 'Matched header',
            fuzzy_header: 'Similar header',
            content_sniffed: 'Guessed from data',
            fallback_first_column: 'Best guess',
        };
        return (labels[detected.source] || 'Detected') + ' · ' + pct + '%';
    }

    function renderMappingStep(data) {
        const rowsEl = document.getElementById('import-mapping-rows');
        rowsEl.innerHTML = '';

        data.fields.forEach((field) => {
            const detected = data.mapping[field] || { column: null };
            const confidence = detected.confidence || 0;
            const isRequired = REQUIRED_FIELDS.includes(field);
            const isUnmapped = detected.column === null || detected.column === undefined;
            const isLow = !isUnmapped && confidence < 0.6;

            const row = document.createElement('div');
            row.className = 'flex items-center gap-4 rounded-xl border px-3 py-2.5 ' +
                (isUnmapped && isRequired ? 'border-red-200 bg-red-50/60' : (isLow ? 'border-amber-200 bg-amber-50/60' : 'border-slate-200 bg-white'));

            const labelWrap = document.createElement('div');
            labelWrap.className = 'w-40 shrink-0';
            labelWrap.innerHTML = '<p class="text-sm font-medium text-slate-800">' + FIELD_LABELS[field] +
                (isRequired ? ' <span class="text-red-500">*</span>' : '') + '</p>' +
                '<p class="mt-0.5 text-[11px] font-medium ' + (isLow || isUnmapped ? 'text-amber-600' : 'text-emerald-600') + '">' +
                describeSource(detected) + '</p>';

            const select = document.createElement('select');
            select.className = 'flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 outline-none focus:border-emerald-500';
            select.dataset.field = field;

            const noneOpt = document.createElement('option');
            noneOpt.value = '';
            noneOpt.textContent = "— Don't import this field —";
            select.appendChild(noneOpt);

            data.columns.forEach((col) => {
                const opt = document.createElement('option');
                opt.value = col.index;
                opt.textContent = col.label;
                if (!isUnmapped && detected.column === col.index) opt.selected = true;
                select.appendChild(opt);
            });

            select.addEventListener('change', () => {
                importState.mapping[field] = select.value === '' ? null : parseInt(select.value, 10);
            });

            importState.mapping[field] = isUnmapped ? null : detected.column;

            row.appendChild(labelWrap);
            row.appendChild(select);
            rowsEl.appendChild(row);
        });

        const head = document.getElementById('import-preview-head');
        const body = document.getElementById('import-preview-body');
        head.innerHTML = '<tr>' + data.columns.map((c) => '<th class="px-3 py-2 text-left">' + escapeHtml(c.label) + '</th>').join('') + '</tr>';
        body.innerHTML = data.preview_rows.map((row) =>
            '<tr>' + data.columns.map((c) => '<td class="px-3 py-2 text-slate-600">' + escapeHtml(row[c.index]) + '</td>').join('') + '</tr>'
        ).join('');
    }

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            fileLabel.textContent = fileInput.files[0].name;
            analyzeBtn.disabled = false;
        } else {
            fileLabel.textContent = 'Drop manifest here or click to browse';
            analyzeBtn.disabled = true;
        }
    });

    analyzeBtn.addEventListener('click', async () => {
        if (!fileInput.files.length) return;
        clearMessage();
        analyzeBtn.disabled = true;
        analyzeBtnLabel.textContent = 'Analyzing…';

        const formData = new FormData();
        formData.append('csv_file', fileInput.files[0]);

        try {
            const res = await fetch("{{ route('vendor.inventory.import.preview') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: formData,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Could not analyze the file.');

            importState.token = data.token;
            renderMappingStep(data);

            if (data.missing_required && data.missing_required.length) {
                showMessage('Could not confidently detect: ' + data.missing_required.map((f) => FIELD_LABELS[f]).join(', ') + '. Please select the matching column manually below.', true);
            }

            stepUpload.classList.add('hidden');
            stepMapping.classList.remove('hidden');
            subtitle.textContent = 'Review the detected mapping, then confirm';
        } catch (err) {
            showMessage(err.message, true);
        } finally {
            analyzeBtn.disabled = false;
            analyzeBtnLabel.textContent = 'Analyze File';
        }
    });

    document.getElementById('import-back-btn').addEventListener('click', resetImportModal);

    confirmBtn.addEventListener('click', async () => {
        clearMessage();
        const missing = REQUIRED_FIELDS.filter((f) => importState.mapping[f] === null || importState.mapping[f] === undefined);
        if (missing.length) {
            showMessage('Please map a column for: ' + missing.map((f) => FIELD_LABELS[f]).join(', '), true);
            return;
        }

        confirmBtn.disabled = true;
        confirmBtnLabel.textContent = 'Importing…';

        try {
            const res = await fetch("{{ route('vendor.inventory.import.confirm') }}", {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: importState.token, mapping: importState.mapping }),
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || 'Import failed.');

            showMessage(data.success, false);
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            showMessage(err.message, true);
            confirmBtn.disabled = false;
            confirmBtnLabel.textContent = 'Confirm & Import';
        }
    });

    const openImportBtn = document.querySelector('[data-cura-modal-open="import-modal"]');
    if (openImportBtn) openImportBtn.addEventListener('click', resetImportModal);
})();
</script>

@push('styles')
<style>
    @media print {
        #sidebar, header, .no-print { display: none !important; }
        body, main { background: white !important; padding: 0 !important; margin: 0 !important; }
        .inventory-page section { border: none !important; box-shadow: none !important; }
        table { width: 100% !important; min-width: 0 !important; }
    }
</style>
@endpush
@endsection
