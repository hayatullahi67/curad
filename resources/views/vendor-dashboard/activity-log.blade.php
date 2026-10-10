@extends('layouts.app')

@section('title', 'Activity Log')
@section('header_title', 'Activity Log')

@php
    $field = 'h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';

    // Icon and colour per kind of activity (the part of the action before the dot).
    $groupStyles = [
        'account' => ['fa-user-shield', 'bg-emerald-50 text-emerald-600'],
        'sale' => ['fa-receipt', 'bg-sky-50 text-sky-600'],
        'stock' => ['fa-boxes-stacked', 'bg-teal-50 text-teal-600'],
        'product' => ['fa-pills', 'bg-violet-50 text-violet-600'],
        'staff' => ['fa-user-group', 'bg-amber-50 text-amber-600'],
        'shift' => ['fa-cash-register', 'bg-slate-100 text-slate-600'],
        'order' => ['fa-bag-shopping', 'bg-indigo-50 text-indigo-600'],
    ];

    // Some account events deserve their own icon.
    $actionStyles = [
        'account.signed_in' => ['fa-right-to-bracket', 'bg-emerald-50 text-emerald-600'],
        'account.signed_out' => ['fa-right-from-bracket', 'bg-slate-100 text-slate-500'],
        'account.sign_in_failed' => ['fa-triangle-exclamation', 'bg-rose-50 text-rose-600'],
        'account.sign_in_blocked' => ['fa-ban', 'bg-rose-50 text-rose-600'],
        'account.locked' => ['fa-lock', 'bg-rose-50 text-rose-600'],
        'account.created' => ['fa-user-plus', 'bg-emerald-50 text-emerald-600'],
        'account.pharmacy_registered' => ['fa-store', 'bg-emerald-50 text-emerald-600'],
        'account.password_reset' => ['fa-key', 'bg-amber-50 text-amber-600'],
        'account.password_changed' => ['fa-key', 'bg-amber-50 text-amber-600'],
    ];

    // A link to this page with some filters changed (null removes one), back on page 1.
    $filterUrl = fn (array $changes) => route('vendor.activity', array_filter(array_merge($filters, $changes), fn ($value) => $value !== null && $value !== ''));

    $days = $logs->getCollection()->groupBy(fn ($log) => $log->created_at->toDateString());
    $total = (int) $groupCounts->sum();
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1180px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <p class="max-w-3xl text-sm text-slate-500">
        A permanent record of what happens at {{ $pharmacy->name }}: who signed in and from which device, sales, voids and refunds, stock and product changes, staff accounts and online orders. Entries cannot be edited or deleted.
    </p>

    {{-- ============ Key numbers (same whatever filter is on) ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-300">Activity today</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-bolt text-sm"></i></span>
            </div>
            <p class="relative mt-3 text-[26px] font-bold leading-none sm:text-[28px]">{{ number_format($stats['today']) }}</p>
            <p class="relative mt-2 truncate text-xs text-slate-400">{{ number_format($total) }} recorded in all</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Sign-ins today</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fas fa-right-to-bracket text-sm"></i></span>
            </div>
            <p class="mt-3 text-[26px] font-bold leading-none text-slate-900 sm:text-[28px]">{{ number_format($stats['sign_ins_today']) }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">{{ $stats['people_today'] }} {{ Str::plural('person', $stats['people_today']) }}</p>
        </div>

        <a href="{{ $filterUrl(['group' => 'account', 'flagged' => 1, 'from' => now()->subDays(6)->toDateString(), 'to' => null, 'user' => null]) }}" class="rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md sm:p-5 {{ $stats['failed_week'] ? 'border-rose-200 hover:border-rose-300' : 'border-slate-200 hover:border-slate-300' }}">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Failed sign-ins</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $stats['failed_week'] ? 'bg-rose-50 text-rose-600' : 'bg-slate-100 text-slate-500' }}"><i class="fas fa-triangle-exclamation text-sm"></i></span>
            </div>
            <p class="mt-3 text-[26px] font-bold leading-none sm:text-[28px] {{ $stats['failed_week'] ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($stats['failed_week']) }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">Last 7 days</p>
        </a>

        <a href="{{ $filterUrl(['group' => null, 'flagged' => 1, 'from' => now()->subDays(6)->toDateString(), 'to' => null, 'user' => null]) }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow-md sm:p-5">
            <div class="flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-500">Worth a look</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><i class="fas fa-flag text-sm"></i></span>
            </div>
            <p class="mt-3 text-[26px] font-bold leading-none text-slate-900 sm:text-[28px]">{{ number_format($stats['flagged_week']) }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">Voids, refunds, password changes · 7 days</p>
        </a>
    </section>

    {{-- ============ Filters ============ --}}
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-100 px-2 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Type of activity">
            @foreach (['' => 'Everything'] + \App\Models\AuditLog::GROUPS as $value => $label)
                @php $active = (string) $filters['group'] === (string) $value; @endphp
                <a href="{{ $filterUrl(['group' => $value ?: null]) }}"
                   class="relative flex flex-shrink-0 items-center gap-2 px-3.5 pb-3 pt-2 text-sm font-semibold transition {{ $active ? 'text-emerald-700' : 'text-slate-500 hover:text-slate-900' }}"
                   @if ($active) aria-current="page" @endif>
                    {{ $label }}
                    <span class="rounded-md px-1.5 py-0.5 text-xs {{ $active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ number_format($value === '' ? $total : (int) ($groupCounts[$value] ?? 0)) }}</span>
                    @if ($active)
                        <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-emerald-600"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('vendor.activity') }}" class="grid grid-cols-2 gap-3 p-3 sm:p-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
            @if ($filters['group'])<input type="hidden" name="group" value="{{ $filters['group'] }}">@endif

            <div class="col-span-2 lg:col-span-1">
                <label for="filter-user" class="mb-1.5 block text-xs font-semibold text-slate-600">Done by</label>
                <select id="filter-user" name="user" class="{{ $field }}">
                    <option value="">Anyone</option>
                    @foreach ($people as $person)
                        <option value="{{ $person->id }}" @selected((string) $filters['user'] === (string) $person->id)>{{ $person->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-from" class="mb-1.5 block text-xs font-semibold text-slate-600">From</label>
                <input id="filter-from" type="date" name="from" value="{{ $filters['from'] }}" class="{{ $field }}">
            </div>
            <div>
                <label for="filter-to" class="mb-1.5 block text-xs font-semibold text-slate-600">To</label>
                <input id="filter-to" type="date" name="to" value="{{ $filters['to'] }}" class="{{ $field }}">
            </div>
            <div class="col-span-2 flex flex-wrap items-center gap-2 lg:col-span-1">
                <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 has-[:checked]:border-amber-300 has-[:checked]:bg-amber-50 has-[:checked]:text-amber-800">
                    <input type="checkbox" name="flagged" value="1" @checked($filters['flagged']) class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                    Worth a look only
                </label>
                <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                    <i class="fas fa-filter text-xs"></i> Apply
                </button>
                @if ($hasFilters)
                    <a href="{{ route('vendor.activity') }}" class="inline-flex h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-900">
                        <i class="fas fa-xmark text-xs"></i> Clear
                    </a>
                @endif
            </div>
        </form>
    </section>

    {{-- ============ Timeline ============ --}}
    @if ($logs->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-clipboard-list"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">{{ $hasFilters ? 'Nothing matches these filters' : 'No activity recorded yet' }}</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">
                {{ $hasFilters ? 'Try a wider date range or clear the filters.' : 'Actions are recorded from now on. Sign in, make a sale or adjust stock and it appears here.' }}
            </p>
            @if ($hasFilters)
                <a href="{{ route('vendor.activity') }}" class="mt-6 inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Clear filters</a>
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach ($days as $date => $entries)
                @php $day = \Carbon\Carbon::parse($date); @endphp
                <section>
                    <h2 class="mb-2 flex items-center gap-2 px-1 text-sm font-semibold text-slate-900">
                        {{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, M j, Y')) }}
                        <span class="rounded-md bg-slate-200/70 px-1.5 py-0.5 text-xs font-semibold text-slate-600">{{ $entries->count() }}</span>
                    </h2>

                    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        @foreach ($entries as $log)
                            @php
                                [$icon, $tone] = $actionStyles[$log->action] ?? $groupStyles[$log->group()] ?? ['fa-circle-dot', 'bg-slate-100 text-slate-500'];
                                $flagged = in_array($log->action, $sensitive, true);
                                $isAccount = $log->group() === 'account';
                                $device = $log->meta['device'] ?? null;
                            @endphp
                            <li class="flex items-start gap-3 px-4 py-3.5 sm:gap-3.5 sm:px-5 {{ $flagged ? 'bg-amber-50/40' : '' }}">
                                <span class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl {{ $tone }}"><i class="fas {{ $icon }} text-sm"></i></span>

                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-sm leading-6 text-slate-900">{{ $log->description }}</p>
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                                        <span class="font-semibold text-slate-700">{{ $log->user->full_name ?? 'System' }}</span>
                                        @if (! empty($log->meta['approved_by']) && $log->meta['approved_by'] !== ($log->user->full_name ?? null))
                                            <span>· approved by <span class="font-semibold text-slate-700">{{ $log->meta['approved_by'] }}</span></span>
                                        @endif
                                        @if ($isAccount && $device)
                                            <span class="inline-flex items-center gap-1">· <i class="fas {{ str_contains($device, 'Android') || str_contains($device, 'iPhone') ? 'fa-mobile-screen' : 'fa-display' }} text-[10px] text-slate-400"></i>{{ $device }}</span>
                                        @endif
                                        @if ($isAccount && $log->ip_address)
                                            <span class="font-mono text-[11px] text-slate-400">· {{ $log->ip_address }}</span>
                                        @endif
                                    </p>
                                </div>

                                <div class="flex flex-shrink-0 flex-col items-end gap-1.5">
                                    <time datetime="{{ $log->created_at->toIso8601String() }}" class="whitespace-nowrap text-xs font-medium text-slate-500">{{ $log->created_at->format('g:i A') }}</time>
                                    @if ($flagged)
                                        <span class="inline-flex items-center gap-1 rounded-md bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800"><i class="fas fa-flag text-[9px]"></i>Check</span>
                                    @endif
                                    @if ($log->subject_type === \App\Models\PosSale::class && $log->subject_id)
                                        <a href="{{ route('vendor.sales.show', $log->subject_id) }}" class="whitespace-nowrap text-xs font-semibold text-emerald-700 hover:text-emerald-800">Open sale</a>
                                    @elseif ($log->subject_type === \App\Models\OrderFulfillment::class)
                                        <a href="{{ route('vendor.orders', ['status' => 'all']) }}" class="whitespace-nowrap text-xs font-semibold text-emerald-700 hover:text-emerald-800">Orders</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        <nav class="flex items-center justify-between gap-3" aria-label="Pages">
            @if ($logs->onFirstPage())
                <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Newer</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Newer</a>
            @endif
            <p class="text-center text-sm text-slate-500">
                {{ number_format($logs->firstItem()) }}–{{ number_format($logs->lastItem()) }} of <span class="font-semibold text-slate-900">{{ number_format($logs->total()) }}</span>
            </p>
            @if ($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Older</a>
            @else
                <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Older</span>
            @endif
        </nav>
    @endif
</div>
@endsection
