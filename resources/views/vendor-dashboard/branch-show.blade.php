@extends('layouts.app')

@section('title', $branch->name)
@section('header_title', $branch->name)

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $openOrders = (int) (($orders['awaiting_confirmation'] ?? 0) + ($orders['confirmed'] ?? 0) + ($orders['ready'] ?? 0));
    $activeStaff = $staff->where('is_active', true);
    $mapQuery = $branch->latitude && $branch->longitude ? $branch->latitude . ',' . $branch->longitude : $branch->name . ' ' . $branch->fullAddress();

    // Icon and colour for each kind of activity (the part of the action before the dot).
    $activityStyles = [
        'account' => ['fa-user-shield', 'bg-emerald-50 text-emerald-600'],
        'sale' => ['fa-receipt', 'bg-sky-50 text-sky-600'],
        'stock' => ['fa-boxes-stacked', 'bg-teal-50 text-teal-600'],
        'product' => ['fa-pills', 'bg-violet-50 text-violet-600'],
        'staff' => ['fa-user-group', 'bg-amber-50 text-amber-600'],
        'shift' => ['fa-cash-register', 'bg-slate-100 text-slate-600'],
        'order' => ['fa-bag-shopping', 'bg-indigo-50 text-indigo-600'],
        'branch' => ['fa-store', 'bg-emerald-50 text-emerald-600'],
    ];

    // Opens a page of this branch: switches to it first when the owner is working somewhere else.
    $card = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    $heading = 'text-base font-bold text-slate-900';
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    <a href="{{ route('vendor.branches') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-900"><i class="fas fa-arrow-left text-xs"></i> All branches</a>

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- ============ Who and where ============ --}}
    <section class="{{ $card }} p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-4">
                <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl text-xl {{ $branch->is_main ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30' : 'bg-slate-100 text-slate-500' }}"><i class="fas fa-store"></i></span>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                        <span class="break-words text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">{{ $branch->name }}</span>
                        @if ($branch->is_main)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Main branch</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $branch->is_active ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $branch->is_active ? 'Taking online orders' : 'Offline on marketplace' }}
                        </span>
                    </p>
                    <p class="mt-1.5 text-sm text-slate-600">{{ $branch->fullAddress() ?: 'No address yet' }}</p>
                    <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
                        @if ($branch->phone)
                            <a href="tel:{{ $branch->phone }}" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-phone mr-1"></i>{{ $branch->phone }}</a>
                        @endif
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" rel="noopener" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-map-location-dot mr-1"></i>Open in maps</a>
                        @if ($branch->operating_hours)
                            <span class="font-medium text-slate-500"><i class="fas fa-clock mr-1"></i>{{ $branch->operating_hours }}</span>
                        @endif
                        @unless ($branch->latitude && $branch->longitude)
                            <span class="font-medium text-amber-700"><i class="fas fa-triangle-exclamation mr-1"></i>Not pinned on the map yet</span>
                        @endunless
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($isCurrent)
                    <span class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-100 px-4 text-sm font-semibold text-slate-600"><i class="fas fa-location-dot text-xs"></i> You are working here</span>
                @else
                    <form action="{{ route('vendor.branches.switch', $branch) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800"><i class="fas fa-right-to-bracket text-xs"></i> Work in this branch</button>
                    </form>
                @endif
                <button type="button" data-cura-modal-open="edit-branch-modal" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300"><i class="fas fa-pen text-xs text-slate-400"></i> Edit</button>
                <form action="{{ route('vendor.branches.toggle', $branch) }}" method="POST" onsubmit="return confirm('{{ $branch->is_active ? 'Stop this branch taking online orders? Its till keeps working.' : 'Put this branch back on the marketplace?' }}')">
                    @csrf
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition {{ $branch->is_active ? 'hover:border-rose-300 hover:text-rose-700' : 'hover:border-emerald-300 hover:text-emerald-700' }}">
                        <i class="fas {{ $branch->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs text-slate-400"></i> {{ $branch->is_active ? 'Take offline' : 'Put online' }}
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- ============ Money ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <p class="relative text-[13px] font-medium text-slate-300">Sales today</p>
            <p class="relative mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] sm:text-[28px]" title="{{ $money($today['amount']) }}">{{ $money($today['amount']) }}</p>
            <p class="relative mt-2 text-xs text-slate-400">{{ $today['count'] }} {{ Str::plural('sale', $today['count']) }}</p>
        </div>
        @foreach ([['Last 7 days', $week], ['This month', $month]] as [$label, $period])
            <div class="{{ $card }} p-4 sm:p-5">
                <p class="text-[13px] font-medium text-slate-500">{{ $label }}</p>
                <p class="mt-3 truncate text-[22px] font-bold leading-none text-slate-900 min-[380px]:text-[26px] sm:text-[28px]" title="{{ $money($period['amount']) }}">{{ $money($period['amount']) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $period['count'] }} {{ Str::plural('sale', $period['count']) }}</p>
            </div>
        @endforeach
        <div class="{{ $card }} p-4 sm:p-5">
            <p class="text-[13px] font-medium text-slate-500">Stock value</p>
            <p class="mt-3 truncate text-[22px] font-bold leading-none text-slate-900 min-[380px]:text-[26px] sm:text-[28px]" title="{{ $money($stock->value) }}">{{ $money($stock->value) }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">{{ number_format($stock->units) }} units · {{ $stock->products }} {{ Str::plural('product', $stock->products) }}</p>
        </div>
    </section>

    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] xl:items-start">
        <div class="space-y-5">

            {{-- ============ Sales, day by day ============ --}}
            <section class="{{ $card }} p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="{{ $heading }}">Sales, last 14 days</h2>
                    <p class="flex items-center gap-4 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Till</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-slate-800"></span>Online</span>
                    </p>
                </div>

                @if ($days->sum(fn ($day) => $day->till + $day->online) > 0)
                    <div class="mt-5 flex h-44 items-end gap-1 sm:gap-2" role="img" aria-label="Bar chart of daily sales for the last 14 days">
                        @foreach ($days as $day)
                            @php
                                $total = $day->till + $day->online;
                                $tillHeight = $total > 0 ? max(2, $day->till / $peak * 100) : 0;
                                $onlineHeight = $day->online > 0 ? max(2, $day->online / $peak * 100) : 0;
                            @endphp
                            <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" tabindex="0">
                                <div class="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-lg group-hover:block group-focus:block">
                                    {{ $day->date->format('D, M j') }} · {{ $money($total) }}
                                </div>
                                @if ($total > 0)
                                    @if ($day->online > 0)<div class="w-full rounded-t-md bg-slate-800" style="height: {{ $onlineHeight }}%"></div>@endif
                                    @if ($day->till > 0)<div class="w-full bg-emerald-500 {{ $day->online > 0 ? '' : 'rounded-t-md' }}" style="height: {{ $tillHeight }}%"></div>@endif
                                @else
                                    <div class="h-0.5 w-full rounded-full bg-slate-200"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex gap-1 sm:gap-2">
                        @foreach ($days as $index => $day)
                            <span class="min-w-0 flex-1 text-center text-[10px] font-medium {{ $day->date->isToday() ? 'text-emerald-700' : 'text-slate-400' }}">{{ $index % 2 === 1 || $day->date->isToday() ? $day->date->format('j') : '' }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-5 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">No sales in the last 14 days.</p>
                @endif
            </section>

            {{-- ============ What has been happening ============ --}}
            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-3 p-4 pb-3 sm:px-6 sm:pt-6">
                    <h2 class="{{ $heading }}">What has been happening</h2>
                    @if ($isCurrent)
                        <a href="{{ route('vendor.activity') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Full activity log <i class="fas fa-arrow-right ml-0.5 text-xs"></i></a>
                    @endif
                </div>

                @if ($activity->isEmpty())
                    <p class="mx-4 mb-4 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500 sm:mx-6 sm:mb-6">Nothing recorded for this branch yet.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($activity as $log)
                            @php [$icon, $tone] = $activityStyles[$log->group()] ?? ['fa-circle-dot', 'bg-slate-100 text-slate-500']; @endphp
                            <li class="flex items-start gap-3 px-4 py-3 sm:px-6">
                                <span class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $tone }}"><i class="fas {{ $icon }} text-xs"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm leading-6 text-slate-900">{{ $log->description }}</p>
                                    <p class="text-xs text-slate-500"><span class="font-semibold text-slate-700">{{ $log->user->full_name ?? 'System' }}</span> · {{ $log->created_at->isToday() ? $log->created_at->format('g:i A') : $log->created_at->format('M j, g:i A') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ============ Best sellers ============ --}}
            @if ($topProducts->isNotEmpty())
                <section class="{{ $card }} p-4 sm:p-6">
                    <h2 class="{{ $heading }}">Best sellers at the till, last 30 days</h2>
                    <ol class="mt-4 space-y-3">
                        @php $topAmount = max(1, (float) $topProducts->max('amount')); @endphp
                        @foreach ($topProducts as $index => $product)
                            <li>
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate font-medium text-slate-900"><span class="mr-2 text-xs font-semibold text-slate-400">{{ $index + 1 }}</span>{{ $product->product_name }}</span>
                                    <span class="flex-shrink-0 text-slate-500"><span class="font-semibold text-slate-900">{{ $money($product->amount) }}</span> · {{ (int) $product->units }} sold</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ max(3, (float) $product->amount / $topAmount * 100) }}%"></div></div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>

        <div class="space-y-5">

            {{-- ============ Right now ============ --}}
            <section class="{{ $card }} p-4 sm:p-6">
                <h2 class="{{ $heading }}">Right now</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2.5 text-slate-600"><i class="fas fa-bag-shopping w-4 text-center text-slate-400"></i>Online orders waiting</dt>
                        <dd class="font-bold {{ $openOrders ? 'text-amber-600' : 'text-slate-900' }}">{{ $openOrders }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2.5 text-slate-600"><i class="fas fa-motorcycle w-4 text-center text-slate-400"></i>With a rider</dt>
                        <dd class="font-bold text-slate-900">{{ (int) ($orders['dispatched'] ?? 0) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2.5 text-slate-600"><i class="fas fa-arrow-trend-down w-4 text-center text-slate-400"></i>Products running low</dt>
                        <dd class="font-bold {{ $stock->low ? 'text-rose-600' : 'text-slate-900' }}">{{ (int) $stock->low }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex items-center gap-2.5 text-slate-600"><i class="fas fa-calendar-xmark w-4 text-center text-slate-400"></i>Expired units on the shelf</dt>
                        <dd class="font-bold {{ $stock->expired ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($stock->expired) }}</dd>
                    </div>
                </dl>

                @unless ($isCurrent)
                    <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><i class="fas fa-circle-info mr-1 text-slate-400"></i>To sell, receive stock or handle orders for this branch, press “Work in this branch” above.</p>
                @endunless
            </section>

            {{-- ============ People ============ --}}
            <section class="{{ $card }}">
                <div class="flex items-center justify-between gap-3 p-4 pb-3 sm:px-6 sm:pt-6">
                    <h2 class="{{ $heading }}">Staff <span class="ml-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">{{ $activeStaff->count() }}</span></h2>
                    <a href="{{ route('vendor.sales-staff') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Manage <i class="fas fa-arrow-right ml-0.5 text-xs"></i></a>
                </div>

                @if ($staff->isEmpty())
                    <p class="mx-4 mb-4 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 sm:mx-6 sm:mb-6">No staff in this branch yet.<br><a href="{{ route('vendor.sales-staff') }}" class="font-semibold text-emerald-700">Add the first one</a></p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($staff as $person)
                            <li class="flex items-center gap-3 px-4 py-3 sm:px-6 {{ $person->is_active ? '' : 'opacity-50' }}">
                                <span class="relative flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-600">
                                    {{ strtoupper(mb_substr($person->full_name ?? 'S', 0, 1)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $person->full_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $roleLabel($person->staff_role) }}</p>
                                </div>
                                <span class="flex-shrink-0 text-xs font-semibold {{ $person->is_active ? 'text-emerald-700' : 'text-slate-400' }}">{{ $person->is_active ? 'Active' : 'Deactivated' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- ============ Needs attention ============ --}}
            @if ($lowStock->isNotEmpty() || $expiring->isNotEmpty())
                <section class="{{ $card }} p-4 sm:p-6">
                    <h2 class="{{ $heading }}">Needs attention</h2>

                    @if ($lowStock->isNotEmpty())
                        <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Running low</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($lowStock as $product)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate text-slate-700">{{ $product->name }}</span>
                                    <span class="flex-shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold {{ $product->stock_level <= 0 ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">{{ $product->stock_level <= 0 ? 'Out of stock' : $product->stock_level . ' left' }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($expiring->isNotEmpty())
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Expired or expiring within 90 days</p>
                        <ul class="mt-2 space-y-2">
                            @foreach ($expiring as $batch)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate text-slate-700">{{ $batch->medication->name ?? 'Product' }} <span class="text-slate-400">× {{ $batch->quantity }}</span></span>
                                    <span class="flex-shrink-0 rounded-md px-2 py-0.5 text-xs font-semibold {{ $batch->isExpired() ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700' }}">{{ $batch->isExpired() ? 'Expired ' : '' }}{{ $batch->expiry_date->format('M j, Y') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            {{-- ============ Stock moving in and out ============ --}}
            @if ($branchCount > 1)
                <section class="{{ $card }} p-4 sm:p-6">
                    <h2 class="{{ $heading }}">Stock transfers</h2>
                    @if ($transfers->isEmpty())
                        <p class="mt-4 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No stock has moved in or out of this branch yet.</p>
                    @else
                        <ul class="mt-4 space-y-3">
                            @foreach ($transfers as $transfer)
                                @php $incoming = $transfer->to_pharmacy_id === $branch->id; @endphp
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $incoming ? 'bg-emerald-50 text-emerald-600' : 'bg-sky-50 text-sky-600' }}"><i class="fas {{ $incoming ? 'fa-arrow-down' : 'fa-arrow-up' }} text-xs"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium text-slate-900">{{ (int) $transfer->items_sum_quantity }} units {{ $incoming ? 'from ' . ($transfer->from->name ?? 'a branch') : 'to ' . ($transfer->to->name ?? 'a branch') }}</p>
                                        <p class="text-xs text-slate-500">{{ $transfer->sent_at?->format('M j, g:i A') }}</p>
                                    </div>
                                    <span class="flex-shrink-0 text-xs font-semibold {{ $transfer->status === 'in_transit' ? 'text-amber-700' : ($transfer->status === 'received' ? 'text-emerald-700' : 'text-slate-400') }}">{{ $transfer->statusLabel() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>

<x-modal id="edit-branch-modal" title="Edit branch" max-width="xl">
    <form action="{{ route('vendor.branches.update', $branch) }}" method="POST" id="edit-branch-form" data-branch-form>
        @csrf @method('PUT')
        @include('vendor-dashboard.partials.branch-form', ['branch' => $branch])
    </form>
    <x-slot:footer>
        <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
            <button type="submit" form="edit-branch-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Save changes</button>
        </div>
    </x-slot:footer>
</x-modal>
@endsection

@push('scripts')
    @include('vendor-dashboard.partials.branch-form-script')
    @if ($errors->any())
        <script>document.addEventListener('DOMContentLoaded', () => CuraModal.open('edit-branch-modal'));</script>
    @endif
@endpush
