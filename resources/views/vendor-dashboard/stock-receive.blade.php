@extends('layouts.app')

@section('title', 'Stock')
@section('header_title', 'Stock')

@php
    $fieldClass = 'w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100';
    $labelClass = 'mb-1 block text-xs font-medium text-slate-500';

    // One column layout shared by the table heading and every product row.
    $rowGrid = 'xl:grid-cols-[minmax(0,1fr)_84px_112px_104px_100px_146px_100px_28px]';

    $productData = $products->map(fn ($product) => \App\Http\Controllers\Vendor\StockController::pickerProduct($product))->values();
    $categoryOptions = $products->pluck('category')->filter()->unique()->sort()->values();

    // Lines already typed in when the form comes back with an error, and a
    // product handed over by a link (?add=ID).
    $restoredLines = array_values((array) old('items', []));
    $preselectedProduct = (int) request()->query('add');

    // Which tab opens first. A delivery sent back with mistakes always
    // reopens on the delivery itself.
    $activeTab = in_array(request()->query('tab'), ['list', 'history', 'batches', 'transfers'], true) && ! $errors->any() ? request()->query('tab') : 'new';
    $unlistedCount = $products->where('is_listed', false)->count();
    $tabOn = 'border-emerald-600 text-emerald-700';
    $tabOff = 'border-transparent text-slate-500 hover:text-slate-800';

    // Icon and colour for each kind of stock change in the history tab.
    $typeStyles = [
        'opening_stock' => ['fa-flag', 'bg-slate-100 text-slate-600'],
        'received' => ['fa-truck', 'bg-emerald-50 text-emerald-700'],
        'sale' => ['fa-cash-register', 'bg-blue-50 text-blue-700'],
        'void' => ['fa-ban', 'bg-slate-100 text-slate-600'],
        'return' => ['fa-undo', 'bg-amber-50 text-amber-700'],
        'adjustment' => ['fa-sliders-h', 'bg-violet-50 text-violet-700'],
        'import' => ['fa-file-csv', 'bg-slate-100 text-slate-600'],
        'reservation' => ['fa-shopping-bag', 'bg-blue-50 text-blue-700'],
        'online_order' => ['fa-shopping-bag', 'bg-indigo-50 text-indigo-700'],
        'transfer_out' => ['fa-arrow-up', 'bg-sky-50 text-sky-700'],
        'transfer_in' => ['fa-arrow-down', 'bg-emerald-50 text-emerald-700'],
    ];
    $showMore = old('supplier_phone') || old('note');
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1180px]">

    {{-- Tabs (the page title itself is already in the top bar) --}}
    <div class="mb-4 flex flex-wrap items-end justify-between gap-x-3 gap-y-2 border-b border-slate-200">
        <div class="-mb-px flex gap-1 overflow-x-auto" role="tablist" aria-label="Stock">
            <button type="button" role="tab" id="tab-btn-new" data-tab="new" aria-controls="tab-new" aria-selected="{{ $activeTab === 'new' ? 'true' : 'false' }}"
                class="tab-btn inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $activeTab === 'new' ? $tabOn : $tabOff }}">
                <i class="fas fa-plus-circle text-xs"></i> Add stock
            </button>
            <button type="button" role="tab" id="tab-btn-list" data-tab="list" aria-controls="tab-list" aria-selected="{{ $activeTab === 'list' ? 'true' : 'false' }}"
                class="tab-btn inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $activeTab === 'list' ? $tabOn : $tabOff }}">
                <i class="fas fa-boxes text-xs"></i> Stock list
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ $products->count() }}</span>
            </button>
            <button type="button" role="tab" id="tab-btn-history" data-tab="history" aria-controls="tab-history" aria-selected="{{ $activeTab === 'history' ? 'true' : 'false' }}"
                class="tab-btn inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $activeTab === 'history' ? $tabOn : $tabOff }}">
                <i class="fas fa-history text-xs"></i> Stock history
            </button>
            <button type="button" role="tab" id="tab-btn-batches" data-tab="batches" aria-controls="tab-batches" aria-selected="{{ $activeTab === 'batches' ? 'true' : 'false' }}"
                class="tab-btn inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $activeTab === 'batches' ? $tabOn : $tabOff }}">
                <i class="fas fa-layer-group text-xs"></i> Batches &amp; expiry
                @if ($batchCounts['expired'])
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">{{ $batchCounts['expired'] }} expired</span>
                @endif
            </button>
            <button type="button" role="tab" id="tab-btn-transfers" data-tab="transfers" aria-controls="tab-transfers" aria-selected="{{ $activeTab === 'transfers' ? 'true' : 'false' }}"
                class="tab-btn inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-semibold {{ $activeTab === 'transfers' ? $tabOn : $tabOff }}">
                <i class="fas fa-right-left text-xs"></i> Transfers
                @if ($incomingTransfers)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">{{ $incomingTransfers }} to confirm</span>
                @endif
            </button>
        </div>
        <div class="flex gap-2 pb-2">
            <a href="{{ route('vendor.inventory') }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-slate-900">
                <i class="fas fa-pills text-slate-400"></i> Inventory
            </a>

        </div>
    </div>

    @include('vendor-dashboard.partials.stock-batches-transfers')

    {{-- ======================= TAB 1: ADD STOCK ======================= --}}
    <div id="tab-new" role="tabpanel" aria-labelledby="tab-btn-new" data-tab-panel="new" class="{{ $activeTab === 'new' ? '' : 'hidden' }}">

    @if ($errors->any())
        <div id="receive-errors" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <p class="font-semibold">Please fix the following and save again:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach (collect($errors->all())->unique() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('vendor.stock.receive.store') }}" id="receive-form">
        @csrf

        {{-- ============ Delivery details: one slim row ============ --}}
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="supplier-name" class="{{ $labelClass }}">Supplier</label>
                    <input id="supplier-name" type="text" name="supplier_name" list="supplier-list" value="{{ old('supplier_name') }}" maxlength="255" autocomplete="off" placeholder="Who delivered it? (optional)" class="{{ $fieldClass }}">
                    <datalist id="supplier-list">
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->name }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label for="received-at" class="{{ $labelClass }}">Date received</label>
                    <input id="received-at" type="date" name="received_at" required max="{{ now()->toDateString() }}" value="{{ old('received_at', now()->toDateString()) }}" class="{{ $fieldClass }}">
                </div>
                <div>
                    <label for="invoice-no" class="{{ $labelClass }}">Invoice number</label>
                    <input id="invoice-no" type="text" name="invoice_no" value="{{ old('invoice_no') }}" maxlength="100" placeholder="From the supplier's invoice (optional)" class="{{ $fieldClass }}">
                </div>
            </div>

            <div id="more-details" class="{{ $showMore ? '' : 'hidden' }} mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="supplier-phone" class="{{ $labelClass }}">Supplier phone</label>
                    <input id="supplier-phone" type="text" name="supplier_phone" value="{{ old('supplier_phone') }}" maxlength="30" placeholder="08012345678" class="{{ $fieldClass }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="receive-note" class="{{ $labelClass }}">Note</label>
                    <input id="receive-note" type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="Anything worth remembering about this delivery" class="{{ $fieldClass }}">
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                <button type="button" id="more-details-toggle" class="{{ $showMore ? 'hidden' : '' }} text-xs font-semibold text-emerald-700 hover:underline">
                    <i class="fas fa-plus mr-1 text-[10px]"></i>Add supplier phone or a note
                </button>
                <button type="button" id="repeat-delivery-btn" class="hidden items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-200 transition hover:bg-blue-100">
                    <i class="fas fa-redo text-[10px]"></i> <span id="repeat-delivery-label"></span>
                </button>
            </div>
        </div>

        {{-- ============ Products table ============ --}}
        <div class="mt-4 rounded-xl border border-slate-200 bg-white">

            {{-- Add a product --}}
            <div id="picker" class="border-b border-slate-200 p-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                        <input id="product-search" type="text" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="picker-panel" aria-autocomplete="list" aria-label="Add a product to this delivery"
                            placeholder="Choose a product in stock, type a name, or scan a barcode"
                            class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-10 text-sm text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-100">
                        <button type="button" id="picker-toggle" tabindex="-1" aria-label="Show the product list" class="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                            <i id="picker-chevron" class="fas fa-chevron-down text-xs transition-transform"></i>
                        </button>

                        <div id="picker-panel" class="absolute left-0 right-0 top-full z-30 mt-1.5 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl">
                            <div id="product-list" role="listbox" class="max-h-64 overflow-y-auto py-1"></div>
                            <p id="product-list-empty" class="hidden px-4 py-5 text-center text-sm text-slate-500"></p>
                            <button type="button" id="new-product-btn" class="flex w-full items-center gap-2.5 border-t border-slate-200 bg-slate-50 px-3.5 py-2.5 text-left text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                <i class="fas fa-plus-circle"></i> <span id="new-product-label">Add new stock</span>
                            </button>
                        </div>
                    </div>

                    <button type="button" id="low-stock-btn" class="hidden flex-shrink-0 items-center justify-center gap-1.5 rounded-lg bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200 transition hover:bg-amber-100">
                        <i class="fas fa-exclamation-triangle text-[10px]"></i> <span id="low-stock-label"></span>
                    </button>
                </div>
            </div>

            {{-- Column headings (wide screens) --}}
            <div class="hidden gap-x-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-500 xl:grid {{ $rowGrid }}">
                <span>Product</span>
                <span>In stock</span>
                <span>Qty received</span>
                <span>Cost / unit</span>
                <span>Batch no.</span>
                <span>Expiry date</span>
                <span class="text-right">Line total</span>
                <span></span>
            </div>

            <div id="lines" class="divide-y divide-slate-100"></div>

            <div id="lines-empty" class="px-6 py-10 text-center">
                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="fas fa-truck-loading"></i></div>
                <p class="mt-3 text-sm font-semibold text-slate-700">No products added yet</p>
                <p class="mt-0.5 text-xs text-slate-500">Use the box above: pick a product already in stock, or add new stock.</p>
            </div>
        </div>

        {{-- ============ Totals and save: always in view ============ --}}
        <div class="sticky bottom-0 z-20 -mx-1 mt-4 px-1 pb-1">
            <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-[0_-4px_24px_-6px_rgba(15,23,42,0.18)] sm:flex-row sm:items-center sm:justify-between">
                <dl class="flex items-center gap-5 text-sm sm:gap-7">
                    <div><dt class="text-[11px] font-medium text-slate-500">Products</dt><dd id="sum-products" class="font-mono text-base font-bold text-slate-900">0</dd></div>
                    <div><dt class="text-[11px] font-medium text-slate-500">Units coming in</dt><dd id="sum-units" class="font-mono text-base font-bold text-slate-900">0</dd></div>
                    <div><dt class="text-[11px] font-medium text-slate-500">Total cost</dt><dd id="sum-cost" class="font-mono text-base font-bold text-slate-900">₦0.00</dd></div>
                </dl>
                <div class="flex items-center gap-3">
                    <p id="save-hint" class="hidden text-xs font-medium text-amber-700 sm:block"></p>
                    <button type="submit" id="receive-submit" disabled class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none sm:flex-none">
                        <i class="fas fa-check"></i> Add to stock
                    </button>
                </div>
            </div>
        </div>
    </form>

    </div>

    {{-- ======================= TAB 2: STOCK LIST ======================= --}}
    <div id="tab-list" role="tabpanel" aria-labelledby="tab-btn-list" data-tab-panel="list" class="{{ $activeTab === 'list' ? '' : 'hidden' }}">
        @if ($products->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center">
                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="fas fa-boxes"></i></div>
                <p class="mt-3 text-sm font-semibold text-slate-700">Nothing in stock yet</p>
                <p class="mt-0.5 text-xs text-slate-500">Everything you add to stock will be listed here with its quantity.</p>
                <button type="button" data-go-tab="new" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                    <i class="fas fa-plus text-xs"></i> Add stock
                </button>
            </div>
        @else
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div class="relative w-full sm:max-w-sm">
                    <i class="fas fa-search pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                    <input id="stock-list-search" type="text" autocomplete="off" placeholder="Search by name, SKU or barcode" class="{{ $fieldClass }} pl-10">
                </div>
                <p class="text-sm text-slate-500">
                    <span class="font-mono font-semibold text-slate-800">{{ $products->count() }}</span> {{ \Illuminate\Support\Str::plural('product', $products->count()) }} in stock
                    @if ($unlistedCount > 0)
                        · <span class="font-mono font-semibold text-amber-700">{{ $unlistedCount }}</span> not listed for sale yet
                    @endif
                </p>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="whitespace-nowrap border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2">SKU / Barcode</th>
                                <th class="px-4 py-2 text-right">In stock</th>
                                <th class="px-4 py-2 text-right">Cost / unit</th>
                                <th class="px-4 py-2">Batch</th>
                                <th class="px-4 py-2">Expiry</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($products as $product)
                                @php
                                    $out = $product->stock_level <= 0;
                                    $low = ! $out && $product->stock_level <= $product->low_stock_threshold;
                                    $expiry = $product->expiry_date ? \Carbon\Carbon::parse($product->expiry_date) : null;
                                @endphp
                                <tr class="transition hover:bg-slate-50" data-stock-row data-search="{{ mb_strtolower($product->name . ' ' . $product->dosage . ' ' . $product->sku . ' ' . $product->barcode . ' ' . $product->category) }}">
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                        <p class="text-xs text-slate-500">{{ collect([$product->dosage, $product->category])->filter()->implode(' · ') }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-600">
                                        <p>{{ $product->sku ?: '—' }}</p>
                                        @if ($product->barcode)
                                            <p class="text-slate-400"><i class="fas fa-barcode"></i> {{ $product->barcode }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                        <span class="font-mono font-semibold {{ $out ? 'text-rose-600' : ($low ? 'text-amber-600' : 'text-slate-900') }}">{{ number_format($product->stock_level) }}</span>
                                        @if ($out) <p class="text-[11px] text-rose-600">Out of stock</p> @elseif ($low) <p class="text-[11px] text-amber-600">Low</p> @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono text-slate-600">{{ $product->cost_price !== null ? '₦' . number_format($product->cost_price, 2) : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs text-slate-600">{{ $product->batch_number ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 {{ $expiry && $expiry->isPast() ? 'font-semibold text-rose-600' : 'text-slate-600' }}">{{ $expiry ? $expiry->format('M Y') : '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        @if ($product->is_listed)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700"><i class="fas fa-check text-[10px]"></i> Listed</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700" title="In stock, but not yet listed in Inventory with a selling price, so it cannot be sold.">Not listed</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @unless ($product->is_listed)
                                                <a href="{{ route('vendor.inventory', ['list' => $product->id]) }}" class="rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-700">List for sale</a>
                                            @endunless
                                            <a href="{{ route('vendor.stock.receive', ['add' => $product->id]) }}" title="Add more stock of this product" aria-label="Add more stock of {{ $product->name }}" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-700"><i class="fas fa-plus text-xs"></i></a>
                                            <a href="{{ route('vendor.stock.receive', ['tab' => 'history', 'product' => $product->id]) }}" title="See this product's stock history" aria-label="Stock history of {{ $product->name }}" class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-history text-xs"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p id="stock-list-no-match" class="hidden px-6 py-8 text-center text-sm text-slate-500">No product in your stock matches that search.</p>
            </div>
        @endif
    </div>

    {{-- ======================= TAB 3: STOCK HISTORY ======================= --}}
    <div id="tab-history" role="tabpanel" aria-labelledby="tab-btn-history" data-tab-panel="history" class="{{ $activeTab === 'history' ? '' : 'hidden' }}">

        {{-- Filters --}}
        <form method="GET" action="{{ route('vendor.stock.receive') }}" class="rounded-xl border border-slate-200 bg-white p-4">
            <input type="hidden" name="tab" value="history">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_150px_150px_auto] lg:items-end">
                <div class="col-span-2 lg:col-span-1">
                    <label for="history-product" class="{{ $labelClass }}">Product</label>
                    <select id="history-product" name="product" class="{{ $fieldClass }}">
                        <option value="">All products</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected((string) $historyFilters['product'] === (string) $product->id)>{{ $product->name }}{{ $product->dosage ? ' · ' . $product->dosage : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2 lg:col-span-1">
                    <label for="history-type" class="{{ $labelClass }}">What happened</label>
                    <select id="history-type" name="type" class="{{ $fieldClass }}">
                        <option value="">Everything</option>
                        @foreach (\App\Models\InventoryMovement::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected($historyFilters['type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="history-from" class="{{ $labelClass }}">From</label>
                    <input id="history-from" type="date" name="from" value="{{ $historyFilters['from'] }}" class="{{ $fieldClass }}">
                </div>
                <div>
                    <label for="history-to" class="{{ $labelClass }}">To</label>
                    <input id="history-to" type="date" name="to" value="{{ $historyFilters['to'] }}" class="{{ $fieldClass }}">
                </div>
                <div class="col-span-2 flex items-center gap-2 lg:col-span-1">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 lg:flex-none">
                        <i class="fas fa-filter text-[10px]"></i> Filter
                    </button>
                    @if ($historyHasFilters)
                        <a href="{{ route('vendor.stock.receive', ['tab' => 'history']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">Clear</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Totals for whatever the filters match --}}
        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-1 px-1 text-sm text-slate-500">
            <span><span class="font-mono font-semibold text-emerald-700">+{{ number_format($historyTotals->units_in) }}</span> units in</span>
            <span><span class="font-mono font-semibold text-rose-600">−{{ number_format($historyTotals->units_out) }}</span> units out</span>
            <span><span class="font-mono font-semibold text-slate-800">{{ number_format($historyTotals->movement_count) }}</span> {{ \Illuminate\Support\Str::plural('change', $historyTotals->movement_count) }} {{ $historyHasFilters ? 'matching your filters' : 'in total' }}</span>
        </div>

        <div class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @if ($movements->isEmpty())
                <div class="px-6 py-12 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400"><i class="fas fa-history"></i></div>
                    <p class="mt-3 text-sm font-semibold text-slate-700">{{ $historyHasFilters ? 'Nothing matches these filters' : 'No stock changes yet' }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $historyHasFilters ? 'Try a wider date range or clear the filters.' : 'Deliveries, sales, returns and corrections will be listed here as they happen.' }}</p>
                    @unless ($historyHasFilters)
                        <button type="button" data-go-tab="new" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            <i class="fas fa-truck-loading text-xs"></i> Record a delivery
                        </button>
                    @endunless
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="whitespace-nowrap border-b border-slate-200 bg-slate-50 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-4 py-2">When</th>
                                <th class="px-4 py-2">Product</th>
                                <th class="px-4 py-2">What happened</th>
                                <th class="px-4 py-2 text-right">Change</th>
                                <th class="px-4 py-2 text-right">Stock after</th>
                                <th class="px-4 py-2">Details</th>
                                <th class="px-4 py-2">By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($movements as $movement)
                                @php
                                    [$icon, $tone] = $typeStyles[$movement->type] ?? ['fa-exchange-alt', 'bg-slate-100 text-slate-600'];
                                    $isIn = $movement->quantity_change > 0;
                                @endphp
                                <tr class="transition hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <p class="text-slate-800">{{ $movement->created_at->format('M j, Y') }}</p>
                                        <p class="text-xs text-slate-500">{{ $movement->created_at->format('g:i A') }}</p>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if ($movement->product)
                                            <a href="{{ route('vendor.stock.receive', ['tab' => 'history', 'product' => $movement->medication_id]) }}" title="Show only this product" class="font-medium text-slate-900 hover:text-emerald-700 hover:underline">{{ $movement->product->name }}</a>
                                        @else
                                            <span class="italic text-slate-400">Deleted product</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $tone }}">
                                            <i class="fas {{ $icon }} text-[10px]"></i> {{ $movement->typeLabel() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono font-semibold {{ $isIn ? 'text-emerald-700' : 'text-rose-600' }}">{{ $isIn ? '+' : '−' }}{{ number_format(abs($movement->quantity_change)) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-right font-mono font-semibold text-slate-900">{{ number_format($movement->quantity_after) }}</td>
                                    <td class="min-w-[220px] px-4 py-2.5 text-slate-600">{{ $movement->reason ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">{{ $movement->user->full_name ?? 'System' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($movements->hasPages())
                    <div class="border-t border-slate-100 px-4 py-3">{{ $movements->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>

{{-- ============ Add new stock popup ============ --}}
<x-modal id="new-product-modal" title="Add new stock" subtitle="A product you have not stocked before" max-width="xl">
    <form id="new-product-form" class="space-y-4" novalidate>
        <div id="new-product-error" class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-700"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="np-name" class="{{ $labelClass }}">Product name</label>
                <input id="np-name" type="text" maxlength="255" autocomplete="off" placeholder="e.g. Amoxicillin 500mg" class="{{ $fieldClass }}">
            </div>
            <div>
                <label for="np-category" class="{{ $labelClass }}">Category</label>
                <input id="np-category" type="text" list="np-category-list" maxlength="255" autocomplete="off" placeholder="Pick or type one" class="{{ $fieldClass }}">
                <datalist id="np-category-list">
                    @foreach ($categoryOptions->merge(['Prescription (Rx)', 'OTC Medications', 'Pain & Fever', 'Malaria, Infections & Immunity', 'Cough, Cold & Allergy', 'Vitamins & Supplements', 'Medical Devices', 'Baby & Child Care', 'Personal Care & Hygiene', 'Beverages & Retail', 'Other'])->unique() as $category)
                        <option value="{{ $category }}"></option>
                    @endforeach
                </datalist>
            </div>
            <div>
                <label for="np-dosage" class="{{ $labelClass }}">Dosage / size <span class="text-slate-400">(optional)</span></label>
                <input id="np-dosage" type="text" maxlength="255" autocomplete="off" placeholder="e.g. 500mg capsule, 100ml bottle" class="{{ $fieldClass }}">
            </div>
            <div>
                <label for="np-barcode" class="{{ $labelClass }}">Barcode <span class="text-slate-400">(optional)</span></label>
                <div class="relative">
                    <i class="fas fa-barcode pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input id="np-barcode" type="text" maxlength="100" autocomplete="off" placeholder="Click here, then scan the pack" class="{{ $fieldClass }} pl-9 font-mono">
                </div>
                <p class="mt-1 text-[11px] text-slate-400">The number printed on the pack. Lets the till find it with one scan.</p>
            </div>
            <div>
                <label for="np-sku" class="{{ $labelClass }}">SKU <span class="text-slate-400">(your own code)</span></label>
                <input id="np-sku" type="text" maxlength="50" autocomplete="off" placeholder="Leave blank to create one automatically" class="{{ $fieldClass }} font-mono">
                <p class="mt-1 text-[11px] text-slate-400">Blank is fine: we make one like AMO-0007.</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5">
            <p class="text-xs font-semibold text-slate-700">This delivery <span class="font-normal text-slate-500">(optional, you can also enter these in the table)</span></p>
            <div class="mt-2.5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="np-cost" class="{{ $labelClass }}">Cost / unit (₦)</label>
                    <input id="np-cost" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="{{ $fieldClass }} font-mono">
                </div>
                <div>
                    <label for="np-batch" class="{{ $labelClass }}">Batch no.</label>
                    <input id="np-batch" type="text" maxlength="100" autocomplete="off" class="{{ $fieldClass }} font-mono">
                </div>
                <div>
                    <label for="np-expiry" class="{{ $labelClass }}">Expiry date</label>
                    <input id="np-expiry" type="date" class="{{ $fieldClass }}">
                </div>
            </div>
        </div>

        <p class="text-xs leading-relaxed text-slate-500">
            <i class="fas fa-info-circle mr-1 text-slate-400"></i>
            You will enter the quantity in the table next. The selling price, photo and description are added later in Inventory, when you list it for sale.
        </p>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="rounded-lg px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</button>
            <button type="submit" id="new-product-save" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                <i class="fas fa-plus text-xs"></i> <span id="new-product-save-label">Save and add to delivery</span>
            </button>
        </div>
    </form>
</x-modal>
@endsection

@push('scripts')
<script>
(function () {
    const products = @json($productData);
    const byId = new Map(products.map((product) => [product.id, product]));
    const lastDeliveries = @json($lastDeliveries);
    const fieldClass = @json($fieldClass);
    const rowGrid = @json($rowGrid);
    const quickProductUrl = @json(route('vendor.stock.quick-product'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const form = document.getElementById('receive-form');
    const searchInput = document.getElementById('product-search');
    const pickerEl = document.getElementById('picker');
    const panelEl = document.getElementById('picker-panel');
    const chevronEl = document.getElementById('picker-chevron');
    const listEl = document.getElementById('product-list');
    const listEmptyEl = document.getElementById('product-list-empty');
    const linesEl = document.getElementById('lines');
    const linesEmptyEl = document.getElementById('lines-empty');
    const submitBtn = document.getElementById('receive-submit');
    const saveHint = document.getElementById('save-hint');
    const supplierInput = document.getElementById('supplier-name');
    const lowStockBtn = document.getElementById('low-stock-btn');
    const repeatBtn = document.getElementById('repeat-delivery-btn');

    let nextIndex = 0;
    let activeIndex = -1; // dropdown row highlighted with the arrow keys

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const money = (amount) => '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const lineFor = (productId) => linesEl.querySelector(`[data-product-id="${productId}"]`);
    const isOut = (product) => product.stock <= 0;
    const isLow = (product) => product.stock <= product.threshold;

    // ---------- Product dropdown ----------

    const isOpen = () => !panelEl.classList.contains('hidden');

    function openPanel() {
        if (isOpen()) return;
        panelEl.classList.remove('hidden');
        chevronEl.classList.add('rotate-180');
        searchInput.setAttribute('aria-expanded', 'true');
        renderList();
    }

    function closePanel() {
        panelEl.classList.add('hidden');
        chevronEl.classList.remove('rotate-180');
        searchInput.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    }

    function visibleProducts() {
        const needle = searchInput.value.trim().toLowerCase();
        if (needle === '') return products;

        return products.filter((product) =>
            product.name.toLowerCase().includes(needle)
            || (product.dosage || '').toLowerCase().includes(needle)
            || (product.sku || '').toLowerCase().includes(needle)
            || (product.barcode || '').toLowerCase().includes(needle)
        );
    }

    function renderList() {
        const found = visibleProducts();
        const typed = searchInput.value.trim();

        listEl.innerHTML = found.map((product, index) => {
            const added = !!lineFor(product.id);
            const highlighted = index === activeIndex;
            const stock = isOut(product)
                ? '<span class="font-medium text-rose-600">Out of stock</span>'
                : `<span class="${isLow(product) ? 'font-medium text-amber-600' : 'text-slate-500'}">${product.stock} in stock</span>`;

            return `
                <button type="button" role="option" aria-selected="${highlighted}" data-id="${product.id}" class="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-sm ${highlighted ? 'bg-emerald-50' : 'hover:bg-slate-50'}">
                    <span class="min-w-0 truncate">
                        <span class="font-medium text-slate-900">${escapeHtml(product.name)}</span>${product.dosage ? '<span class="text-slate-500"> · ' + escapeHtml(product.dosage) + '</span>' : ''}
                    </span>
                    <span class="flex flex-shrink-0 items-center gap-2 text-xs">
                        ${added ? '<span class="font-medium text-emerald-700"><i class="fas fa-check text-[10px]"></i> Added</span>' : ''}
                        ${product.listed ? '' : '<span class="rounded bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700">Not listed</span>'}
                        ${stock}
                    </span>
                </button>`;
        }).join('');

        const nothing = found.length === 0;
        listEl.classList.toggle('hidden', nothing);
        listEmptyEl.classList.toggle('hidden', !nothing);
        if (nothing) {
            listEmptyEl.textContent = products.length === 0
                ? 'Nothing in stock yet. Add your first stock below.'
                : `Nothing matches “${typed}”. If it is new, add it as new stock below.`;
        }

        document.getElementById('new-product-label').textContent = typed ? `Add “${typed}” as new stock` : 'Add new stock';

        const activeRow = listEl.querySelector('[aria-selected="true"]');
        if (activeRow) activeRow.scrollIntoView({ block: 'nearest' });
    }

    function pick(productId, options = {}) {
        searchInput.value = '';
        closePanel();
        addLine(productId, options);
    }

    listEl.addEventListener('click', (event) => {
        const button = event.target.closest('[data-id]');
        if (button) pick(button.dataset.id);
    });

    searchInput.addEventListener('focus', openPanel);
    searchInput.addEventListener('click', openPanel);
    searchInput.addEventListener('input', () => { activeIndex = -1; openPanel(); renderList(); });

    document.getElementById('picker-toggle').addEventListener('click', () => {
        if (isOpen()) { closePanel(); } else { openPanel(); searchInput.focus(); }
    });

    document.addEventListener('mousedown', (event) => {
        if (isOpen() && !pickerEl.contains(event.target)) closePanel();
    });

    searchInput.addEventListener('keydown', (event) => {
        const found = visibleProducts();

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            openPanel();
            activeIndex = event.key === 'ArrowDown' ? Math.min(found.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
            renderList();
            return;
        }
        if (event.key === 'Escape' && isOpen()) { event.stopPropagation(); closePanel(); return; }
        if (event.key === 'Tab') { closePanel(); return; }
        if (event.key !== 'Enter') return;

        // A scanner types the code and presses Enter: that adds the product
        // and leaves the cursor here, ready for the next scan.
        const query = searchInput.value.trim().toLowerCase();
        const exact = query === '' ? null : products.find((product) => (product.barcode || '').toLowerCase() === query || (product.sku || '').toLowerCase() === query);

        if (exact) pick(exact.id, { keepFocusOnSearch: true });
        else if (activeIndex >= 0 && found[activeIndex]) pick(found[activeIndex].id);
        else if (query !== '' && found.length === 1) pick(found[0].id);
    });

    // ---------- Delivery rows ----------

    function addLine(productId, values = {}) {
        const product = byId.get(Number(productId));
        if (!product) return;

        // Picking or scanning a product that is already listed adds one
        // more unit instead of a second row.
        const existing = lineFor(product.id);
        if (existing) {
            const quantity = existing.querySelector('.line-qty');
            if (!values.silent) quantity.value = (parseInt(quantity.value, 10) || 0) + 1;
            flash(existing);
            if (!values.keepFocusOnSearch && !values.silent) { quantity.focus(); quantity.select(); }
            recalculate();
            return;
        }

        const index = nextIndex++;
        const name = `items[${index}]`;
        const mobileLabel = 'mb-1 block text-[11px] font-medium text-slate-500 xl:hidden';
        const removeBtn = (extra) => `<button type="button" class="line-remove ${extra} h-8 w-8 flex-shrink-0 items-center justify-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" title="Remove from this delivery" aria-label="Remove ${escapeHtml(product.name)} from this delivery"><i class="fas fa-times text-sm"></i></button>`;

        const row = document.createElement('div');
        row.dataset.productId = product.id;
        row.className = `grid grid-cols-2 gap-x-3 gap-y-2.5 px-4 py-3 transition-colors duration-700 xl:items-center xl:py-2 ${rowGrid}`;
        row.innerHTML = `
            <input type="hidden" name="${name}[medication_id]" value="${product.id}">
            <div class="col-span-2 flex items-start justify-between gap-2 xl:col-span-1">
                <div class="min-w-0">
                    <p class="text-sm font-medium leading-snug text-slate-900">${escapeHtml(product.name)}</p>
                    <p class="mt-0.5 truncate text-xs text-slate-500">${escapeHtml(product.dosage || product.category)}</p>
                </div>
                ${removeBtn('flex xl:hidden')}
            </div>
            <div class="col-span-2 text-sm xl:col-span-1">
                <span class="text-xs text-slate-500 xl:hidden">In stock: </span><span class="font-mono text-slate-700">${product.stock}</span><span class="line-after font-mono font-semibold text-emerald-700"></span>
            </div>
            <div>
                <label class="${mobileLabel}">Qty received</label>
                <div class="flex items-stretch overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-100">
                    <button type="button" tabindex="-1" class="line-step px-2.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800" data-step="-1" aria-label="One less">−</button>
                    <input type="number" name="${name}[quantity]" min="1" step="1" required inputmode="numeric" placeholder="0" value="${escapeHtml(values.quantity ?? 1)}" aria-label="Quantity of ${escapeHtml(product.name)} received" class="line-qty w-full min-w-0 bg-transparent py-2 text-center font-mono text-sm font-semibold text-slate-900 focus:outline-none">
                    <button type="button" tabindex="-1" class="line-step px-2.5 text-slate-400 hover:bg-slate-100 hover:text-slate-800" data-step="1" aria-label="One more">+</button>
                </div>
            </div>
            <div>
                <label class="${mobileLabel}">Cost / unit (₦)</label>
                <input type="number" name="${name}[unit_cost]" min="0" step="0.01" inputmode="decimal" value="${escapeHtml(values.unit_cost ?? product.cost ?? '')}" placeholder="Optional" aria-label="Cost per unit" class="line-cost ${fieldClass} font-mono">
            </div>
            <div>
                <label class="${mobileLabel}">Batch no.</label>
                <input type="text" name="${name}[batch_number]" maxlength="100" value="${escapeHtml(values.batch_number ?? '')}" placeholder="Optional" aria-label="Batch number" class="${fieldClass} font-mono">
            </div>
            <div>
                <label class="${mobileLabel}">Expiry date</label>
                <input type="date" name="${name}[expiry_date]" value="${escapeHtml(values.expiry_date ?? '')}" aria-label="Expiry date" class="${fieldClass}">
            </div>
            <div class="col-span-2 text-sm xl:col-span-1 xl:text-right">
                <span class="text-xs text-slate-500 xl:hidden">Line total: </span><span class="line-total font-mono font-semibold text-slate-900">—</span>
            </div>
            ${removeBtn('hidden xl:flex')}`;

        // Newest row sits right under the search box, so it is always in view.
        linesEl.prepend(row);

        row.querySelectorAll('.line-remove').forEach((button) => button.addEventListener('click', () => { row.remove(); recalculate(); }));
        row.querySelectorAll('.line-qty, .line-cost').forEach((input) => input.addEventListener('input', recalculate));
        row.querySelectorAll('.line-step').forEach((button) => button.addEventListener('click', () => {
            const quantity = row.querySelector('.line-qty');
            quantity.value = Math.max(1, (parseInt(quantity.value, 10) || 0) + parseInt(button.dataset.step, 10));
            recalculate();
        }));

        if (!values.silent) {
            flash(row);
            if (!values.keepFocusOnSearch) {
                const quantity = row.querySelector('.line-qty');
                quantity.focus();
                quantity.select();
            }
        }

        recalculate();
    }

    function flash(row) {
        row.classList.add('bg-emerald-50');
        setTimeout(() => row.classList.remove('bg-emerald-50'), 900);
    }

    function recalculate() {
        const rows = Array.from(linesEl.children);
        let units = 0;
        let cost = 0;
        let missingQuantity = 0;

        rows.forEach((row) => {
            const product = byId.get(Number(row.dataset.productId));
            const quantityInput = row.querySelector('.line-qty');
            const quantity = Math.max(0, parseInt(quantityInput.value, 10) || 0);
            const costValue = row.querySelector('.line-cost').value;
            const unitCost = parseFloat(costValue) || 0;

            units += quantity;
            cost += quantity * unitCost;
            if (quantity < 1) missingQuantity++;

            quantityInput.parentElement.classList.toggle('border-amber-400', quantity < 1);
            row.querySelector('.line-after').textContent = quantity > 0 ? ` → ${product.stock + quantity}` : '';
            row.querySelector('.line-total').textContent = quantity > 0 && costValue !== '' ? money(quantity * unitCost) : '—';
        });

        linesEmptyEl.classList.toggle('hidden', rows.length > 0);
        document.getElementById('sum-products').textContent = rows.length;
        document.getElementById('sum-units').textContent = units.toLocaleString('en-NG');
        document.getElementById('sum-cost').textContent = money(cost);

        submitBtn.disabled = rows.length === 0 || missingQuantity > 0;
        saveHint.textContent = rows.length === 0
            ? ''
            : (missingQuantity > 0 ? `Enter a quantity for ${missingQuantity} more ${missingQuantity === 1 ? 'product' : 'products'}` : '');

        refreshShortcuts();
        if (isOpen()) renderList();
    }

    // Enter inside the table must never save the delivery by accident. In a
    // quantity box it jumps back to the search box, ready for the next product.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.target.tagName !== 'INPUT') return;
        event.preventDefault();
        if (event.target.classList.contains('line-qty')) searchInput.focus();
    });

    // ---------- Shortcuts ----------

    function refreshShortcuts() {
        const lowNotAdded = products.filter((product) => isLow(product) && !lineFor(product.id));
        lowStockBtn.classList.toggle('hidden', lowNotAdded.length === 0);
        lowStockBtn.classList.toggle('inline-flex', lowNotAdded.length > 0);
        document.getElementById('low-stock-label').textContent = `Add ${lowNotAdded.length} low-stock ${lowNotAdded.length === 1 ? 'product' : 'products'}`;

        const previous = lastDeliveries[supplierInput.value.trim().toLowerCase()];
        const repeatable = previous ? previous.items.filter((item) => byId.has(item.medication_id) && !lineFor(item.medication_id)) : [];
        repeatBtn.classList.toggle('hidden', repeatable.length === 0);
        repeatBtn.classList.toggle('inline-flex', repeatable.length > 0);
        if (previous) {
            document.getElementById('repeat-delivery-label').textContent = `Repeat last delivery from ${previous.supplier} (${previous.date}, ${repeatable.length} ${repeatable.length === 1 ? 'product' : 'products'})`;
        }
    }

    // Low-stock products come in with the quantity left blank: the page
    // cannot know how many arrived, so it asks instead of guessing.
    lowStockBtn.addEventListener('click', () => {
        products.filter((product) => isLow(product) && !lineFor(product.id))
            .reverse()
            .forEach((product) => addLine(product.id, { quantity: '', silent: true }));
        const firstEmpty = linesEl.querySelector('.line-qty');
        if (firstEmpty) firstEmpty.focus();
    });

    repeatBtn.addEventListener('click', () => {
        const previous = lastDeliveries[supplierInput.value.trim().toLowerCase()];
        if (!previous) return;
        previous.items.slice().reverse()
            .filter((item) => byId.has(item.medication_id) && !lineFor(item.medication_id))
            .forEach((item) => addLine(item.medication_id, { quantity: item.quantity, unit_cost: item.unit_cost ?? '', silent: true }));
    });

    supplierInput.addEventListener('input', refreshShortcuts);

    document.getElementById('more-details-toggle').addEventListener('click', (event) => {
        document.getElementById('more-details').classList.remove('hidden');
        event.currentTarget.classList.add('hidden');
        document.getElementById('supplier-phone').focus();
    });

    // ---------- New product popup ----------

    const newProductForm = document.getElementById('new-product-form');
    const newProductError = document.getElementById('new-product-error');
    const newProductSave = document.getElementById('new-product-save');
    const newProductSaveLabel = document.getElementById('new-product-save-label');
    const field = (id) => document.getElementById('np-' + id);

    document.getElementById('new-product-btn').addEventListener('click', () => {
        closePanel();
        newProductForm.reset();
        newProductError.classList.add('hidden');

        // Carry over what was typed in the search box: a scanned code is
        // almost certainly the barcode, anything else the name.
        const typed = searchInput.value.trim();
        if (/^\d{6,}$/.test(typed)) field('barcode').value = typed;
        else field('name').value = typed;

        CuraModal.open('new-product-modal');
        setTimeout(() => (field('name').value ? field('category') : field('name')).focus(), 350);
    });

    // A scanner ends with Enter; in the barcode box that must not save the form.
    field('barcode').addEventListener('keydown', (event) => { if (event.key === 'Enter') event.preventDefault(); });

    newProductForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        newProductError.classList.add('hidden');
        newProductSave.disabled = true;
        newProductSaveLabel.textContent = 'Saving…';

        try {
            const response = await fetch(quickProductUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({
                    name: field('name').value.trim(),
                    category: field('category').value.trim(),
                    dosage: field('dosage').value.trim(),
                    barcode: field('barcode').value.trim(),
                    sku: field('sku').value.trim(),
                    cost_price: field('cost').value,
                }),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.product) {
                const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
                throw new Error(firstError || data.message || 'The product could not be saved. Please try again.');
            }

            products.push(data.product);
            products.sort((a, b) => a.name.localeCompare(b.name));
            byId.set(data.product.id, data.product);

            searchInput.value = '';
            CuraModal.close('new-product-modal');
            // Batch and expiry belong to this delivery, so they go on its row.
            addLine(data.product.id, { batch_number: field('batch').value.trim(), expiry_date: field('expiry').value });
        } catch (error) {
            newProductError.textContent = error.message;
            newProductError.classList.remove('hidden');
        } finally {
            newProductSave.disabled = false;
            newProductSaveLabel.textContent = 'Save and add to delivery';
        }
    });

    // ---------- Tabs ----------

    function showTab(name) {
        document.querySelectorAll('[data-tab-panel]').forEach((panel) => panel.classList.toggle('hidden', panel.dataset.tabPanel !== name));

        document.querySelectorAll('.tab-btn').forEach((button) => {
            const active = button.dataset.tab === name;
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.classList.toggle('border-emerald-600', active);
            button.classList.toggle('text-emerald-700', active);
            button.classList.toggle('border-transparent', !active);
            button.classList.toggle('text-slate-500', !active);
            button.classList.toggle('hover:text-slate-800', !active);
        });

        // Keep the tab in the address so a refresh or a shared link lands on it.
        const url = new URL(location.href);
        if (name === 'new') url.searchParams.delete('tab');
        else url.searchParams.set('tab', name);
        history.replaceState(null, '', url);
    }

    document.querySelectorAll('.tab-btn').forEach((button) => button.addEventListener('click', () => showTab(button.dataset.tab)));
    document.querySelectorAll('[data-go-tab]').forEach((button) => button.addEventListener('click', () => showTab(button.dataset.goTab)));


    // ---------- Stock list search ----------

    const stockSearch = document.getElementById('stock-list-search');
    if (stockSearch) {
        const stockRows = Array.from(document.querySelectorAll('[data-stock-row]'));
        stockSearch.addEventListener('input', () => {
            const needle = stockSearch.value.trim().toLowerCase();
            let visible = 0;
            stockRows.forEach((row) => {
                const show = needle === '' || row.dataset.search.includes(needle);
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });
            document.getElementById('stock-list-no-match').classList.toggle('hidden', visible > 0);
        });
    }

    // ---------- Start ----------

    const restored = @json($restoredLines);
    restored.slice().reverse().forEach((line) => addLine(line.medication_id, { ...line, silent: true }));

    const preselected = @json($preselectedProduct);
    if (restored.length === 0 && preselected) addLine(preselected, { silent: true });

    recalculate();
})();
</script>
@endpush
