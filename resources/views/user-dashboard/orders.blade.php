@extends('layouts.app')

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $tones = [
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
    ];
@endphp

@section('title', 'My Orders')
@section('header_title', 'My Orders')

@section('content')
<div class="mx-auto -mt-3 max-w-[1100px] space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Everything you have ordered, and where each order is right now.</p>
        <a href="{{ route('user.marketplace') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
            <i class="fas fa-bag-shopping text-xs"></i> Shop the marketplace
        </a>
    </div>

    {{-- Filter tabs --}}
    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden" aria-label="Filter orders">
        @foreach ($filters as $value => $label)
            <a href="{{ route('user.orders', ['show' => $value]) }}"
               class="inline-flex h-10 flex-shrink-0 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold transition {{ $filter === $value ? 'border-emerald-600 bg-emerald-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' }}"
               @if ($filter === $value) aria-current="page" @endif>
                {{ $label }}
                <span class="rounded-md px-1.5 py-0.5 text-xs {{ $filter === $value ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$value] }}</span>
            </a>
        @endforeach
    </nav>

    @forelse ($orders as $order)
        @php
            $unitCount = (int) $order->items->sum('quantity');
            $pharmacies = $order->fulfillments->pluck('pharmacy.name')->filter()->unique();
            $readyCodes = ! $order->isDelivery() ? $order->fulfillments->where('status', 'ready') : collect();
        @endphp
        <a href="{{ route('user.orders.show', $order) }}" class="group block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                        <span class="font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tones[$order->statusTone()] }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $order->statusLabel() }}
                        </span>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">Placed {{ $order->placed_at?->format('M j, Y · g:i A') }}</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-extrabold text-slate-900">{{ $money($order->currentTotal() ?: $order->total) }}</p>
                    <p class="text-xs text-slate-500">{{ $order->paymentStatusLabel() }}</p>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="flex flex-shrink-0 -space-x-2.5">
                    @foreach ($order->items->take(4) as $item)
                        <span class="relative h-11 w-11 overflow-hidden rounded-xl bg-slate-100 ring-2 ring-white">
                            <span class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="fas fa-pills text-sm"></i></span>
                            @if ($item->photo_url)
                                <img src="{{ $item->photo_url }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                        </span>
                    @endforeach
                    @if ($order->items->count() > 4)
                        <span class="relative flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white ring-2 ring-white">+{{ $order->items->count() - 4 }}</span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-slate-900">{{ $order->items->pluck('product_name')->take(2)->implode(', ') }}@if ($order->items->count() > 2) and {{ $order->items->count() - 2 }} more @endif</p>
                    <p class="mt-0.5 truncate text-xs text-slate-500">
                        {{ $unitCount }} {{ Str::plural('item', $unitCount) }} ·
                        <i class="fas {{ $order->isDelivery() ? 'fa-motorcycle' : 'fa-store' }} mx-0.5 text-[10px]"></i>
                        {{ $order->isDelivery() ? 'Delivery' : 'Pickup' }} ·
                        {{ $pharmacies->count() === 1 ? $pharmacies->first() : $pharmacies->count() . ' pharmacies' }}
                    </p>
                </div>
                <i class="fas fa-chevron-right flex-shrink-0 text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600"></i>
            </div>

            @if ($readyCodes->isNotEmpty())
                <p class="mt-4 flex items-center gap-2.5 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-900">
                    <i class="fas fa-box-open text-emerald-600"></i>
                    <span>Ready to collect{{ $readyCodes->count() < $order->fulfillments->filter->isActive()->count() ? ' at ' . $readyCodes->pluck('pharmacy.name')->implode(', ') : '' }}. Open the order for your pickup code.</span>
                </p>
            @elseif ($order->delivery?->status === 'in_transit')
                <p class="mt-4 flex items-center gap-2.5 rounded-xl bg-sky-50 px-3.5 py-2.5 text-sm text-sky-900">
                    <i class="fas fa-motorcycle text-sky-600"></i>
                    <span>The rider is on the way. Open the order and mark it as delivered when it arrives.</span>
                </p>
            @endif
        </a>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-receipt"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">{{ $counts['all'] === 0 ? 'No orders yet' : 'Nothing here' }}</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">{{ $counts['all'] === 0 ? 'When you order from the marketplace, it shows up here with live tracking.' : 'You have no orders in this group.' }}</p>
            @if ($counts['all'] === 0)
                <a href="{{ route('user.marketplace') }}" class="mt-6 inline-flex h-11 items-center rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">Browse the marketplace</a>
            @else
                <a href="{{ route('user.orders', ['show' => 'all']) }}" class="mt-6 inline-flex h-11 items-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Show all orders</a>
            @endif
        </div>
    @endforelse

    @if ($orders->hasPages())
        <nav class="flex items-center justify-between gap-3 pt-2" aria-label="Pages">
            @if ($orders->onFirstPage())
                <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Previous</span>
            @else
                <a href="{{ $orders->previousPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Previous</a>
            @endif
            <p class="text-sm text-slate-500">Page <span class="font-semibold text-slate-900">{{ $orders->currentPage() }}</span> of {{ $orders->lastPage() }}</p>
            @if ($orders->hasMorePages())
                <a href="{{ $orders->nextPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Next</a>
            @else
                <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Next</span>
            @endif
        </nav>
    @endif
</div>
@endsection
