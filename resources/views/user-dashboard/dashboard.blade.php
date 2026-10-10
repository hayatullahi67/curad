@extends('layouts.app')

@section('title', 'Overview')
@section('header_title', 'Overview')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = $user->first_name ?: explode(' ', (string) $user->full_name)[0];
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $tones = [
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
    ];
    $productRoute = 'user.marketplace.show';
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Hello, and the one thing people come here to do ============ --}}
    <section class="relative overflow-hidden rounded-2xl bg-slate-900 px-5 py-6 text-white shadow-sm sm:px-7 sm:py-8">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-emerald-500/25 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 h-56 w-56 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative max-w-2xl">
            <p class="text-sm font-medium text-emerald-300">{{ now()->format('l, F j') }}</p>
            <h1 class="mt-1.5 break-words text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-300">Search every CuraD pharmacy at once, compare prices, and collect or get it delivered.</p>

            <form action="{{ route('user.marketplace') }}" method="GET" role="search" class="mt-5 flex flex-col gap-2.5 sm:flex-row">
                <div class="flex min-w-0 flex-1 items-center gap-2.5 rounded-xl bg-white px-3.5">
                    <label for="overview-search" class="sr-only">Search products</label>
                    <i class="fas fa-magnifying-glass text-slate-400"></i>
                    <input id="overview-search" type="search" name="q" autocomplete="off" placeholder="What do you need? e.g. paracetamol" class="min-w-0 flex-1 border-0 bg-transparent py-3 text-[15px] text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0">
                </div>
                <button type="submit" class="inline-flex h-12 flex-shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 text-[15px] font-semibold text-white transition hover:bg-emerald-400">Search</button>
            </form>

            <p class="mt-3.5 flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold">
                <a href="{{ route('user.marketplace', $hasLocation ? ['sort' => 'nearest'] : []) }}" class="inline-flex items-center gap-2 text-emerald-300 hover:text-emerald-200"><i class="fas fa-location-crosshairs text-xs"></i>{{ $hasLocation ? 'Pharmacies nearest to me' : 'Find pharmacies near me' }}</a>
                <a href="{{ route('user.marketplace') }}" class="inline-flex items-center gap-2 text-slate-300 hover:text-white"><i class="fas fa-store text-xs"></i>Browse everything</a>
            </p>
        </div>
    </section>

    {{-- ============ At a glance ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['Orders in progress', number_format($counts['active']), $counts['done'] . ' completed', 'fa-box', $counts['active'] ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500', route('user.orders')],
            ['Wallet balance', $money($user->wallet_balance), 'Pay for orders at checkout', 'fa-wallet', 'bg-emerald-50 text-emerald-600', route('user.history')],
            ['Watchlist', number_format($counts['watchlist']), 'Products you are watching', 'fa-bookmark', 'bg-sky-50 text-sky-600', route('user.watchlist')],
            ['Saved pharmacies', number_format($counts['saved']), 'Find them again quickly', 'fa-heart', 'bg-rose-50 text-rose-500', route('user.pharmacies')],
        ] as [$label, $number, $hint, $icon, $iconStyle, $url])
            <a href="{{ $url }}" class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-[13px] font-medium text-slate-500">{{ $label }}</p>
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $iconStyle }}"><i class="fas {{ $icon }} text-sm"></i></span>
                </div>
                <p class="mt-3 truncate text-[22px] font-bold leading-none text-slate-900 min-[380px]:text-[26px]" title="{{ $number }}">{{ $number }}</p>
                <p class="mt-2 truncate text-xs text-slate-500">{{ $hint }}</p>
            </a>
        @endforeach
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">

        {{-- ============ Orders in progress ============ --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-6">
                <h2 class="text-base font-bold text-slate-900">Orders in progress</h2>
                <a href="{{ route('user.orders') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">All orders <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </header>

            @forelse ($activeOrders as $order)
                @php
                    $pharmacies = $order->fulfillments->pluck('pharmacy.name')->filter()->unique();
                    $ready = ! $order->isDelivery() && $order->fulfillments->contains('status', 'ready');
                @endphp
                <a href="{{ route('user.orders.show', $order) }}" class="group flex items-center gap-3 border-b border-slate-100 px-4 py-4 transition last:border-b-0 hover:bg-slate-50 sm:px-6">
                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl {{ $ready ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' }}"><i class="fas {{ $order->isDelivery() ? 'fa-motorcycle' : 'fa-store' }}"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                            <span class="font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $tones[$order->statusTone()] ?? $tones['slate'] }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $order->statusLabel() }}
                            </span>
                        </p>
                        <p class="mt-1 truncate text-sm text-slate-600">{{ $order->items->pluck('product_name')->take(2)->implode(', ') }}@if ($order->items->count() > 2) and {{ $order->items->count() - 2 }} more @endif</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }} · {{ $pharmacies->count() === 1 ? $pharmacies->first() : $pharmacies->count() . ' pharmacies' }}@if ($ready) · <span class="font-semibold text-emerald-700">Ready to collect</span>@endif</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <p class="text-sm font-bold text-slate-900">{{ $money($order->currentTotal() ?: $order->total) }}</p>
                        <i class="fas fa-chevron-right mt-1 text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600"></i>
                    </div>
                </a>
            @empty
                <div class="px-6 py-12 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-box-open"></i></span>
                    <h3 class="mt-4 text-sm font-semibold text-slate-900">Nothing on the way</h3>
                    <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">When you place an order, you can follow it here from the pharmacy to your hands.</p>
                    <a href="{{ route('user.marketplace') }}" class="mt-5 inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"><i class="fas fa-store text-xs"></i>Shop the marketplace</a>
                </div>
            @endforelse
        </section>

        {{-- ============ Latest updates ============ --}}
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-6">
                <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">Latest updates
                    @if ($counts['notifications'])<span class="rounded-full bg-rose-500 px-1.5 py-0.5 text-[11px] font-bold text-white">{{ $counts['notifications'] }}</span>@endif
                </h2>
                <a href="{{ route('user.notifications') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">See all</a>
            </header>

            @forelse ($notifications as $notification)
                <a href="{{ route('user.notifications') }}" class="flex gap-3 border-b border-slate-100 px-4 py-3.5 transition last:border-b-0 hover:bg-slate-50 sm:px-6">
                    <span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-200' : 'bg-emerald-500' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm {{ $notification->read_at ? 'font-medium text-slate-700' : 'font-semibold text-slate-900' }}">{{ $notification->title }}</span>
                        <span class="mt-0.5 line-clamp-2 block text-[13px] leading-5 text-slate-500">{{ $notification->message }}</span>
                        <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="px-6 py-12 text-center text-sm text-slate-500">No updates yet. Order news will show here.</p>
            @endforelse

            @if ($counts['messages'])
                <a href="{{ route('user.chat') }}" class="flex items-center gap-3 border-t border-slate-100 bg-emerald-50/60 px-4 py-3 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 sm:px-6">
                    <i class="fas fa-comment-dots"></i>
                    <span class="flex-1">{{ $counts['messages'] }} new {{ Str::plural('message', $counts['messages']) }} from a pharmacy</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </a>
            @endif
        </section>
    </div>

    {{-- ============ New on the marketplace ============ --}}
    @if ($fresh->isNotEmpty())
        <section>
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">New on the marketplace</h2>
                    <p class="mt-0.5 text-sm text-slate-500">In stock right now{{ $hasLocation ? ', with the distance to the closest pharmacy' : '' }}.</p>
                </div>
                <a href="{{ route('user.marketplace') }}" class="flex-shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-800">See all <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-4">
                @foreach ($fresh as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
