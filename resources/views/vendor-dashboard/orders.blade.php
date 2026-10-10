@extends('layouts.app')

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $tones = [
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-600/15',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
        'slate' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
    ];
    $tabUrl = fn (string $status) => route('vendor.orders', array_filter(['status' => $status === 'open' ? null : $status, 'search' => $search ?: null, 'branch' => $scope->param()]));
    $primary = 'inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700';
    $secondary = 'inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:border-rose-300 hover:text-rose-700';
@endphp

@section('title', 'Online Orders')
@section('header_title', 'Online Orders')

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-500">
            Orders customers placed on the marketplace for {{ $scope->label() }}. Accept, then hand over.
            @unless ($canManage) <span class="font-semibold text-slate-700">You can view these orders but not change them.</span> @endunless
        </p>
        <a href="{{ route('vendor.dispatch') }}" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
            <i class="fas fa-motorcycle text-xs text-slate-400"></i> Logistics
        </a>
    </div>

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->first() }}</p>
        </div>
    @endif

    @include('vendor-dashboard.partials.branch-filter', ['route' => 'vendor.orders', 'keep' => array_filter(['status' => $tab === 'open' ? null : $tab, 'search' => $search ?: null])])

    {{-- ============ Key numbers (same whatever tab is open) ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-300">New orders</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-bell text-sm"></i></span>
            </div>
            <p class="relative mt-3 text-[26px] font-bold leading-none sm:text-[28px]">{{ $stats['new'] }}</p>
            <p class="relative mt-2 text-xs text-slate-400">{{ $stats['new'] ? 'Waiting for your answer' : 'Nothing waiting' }}</p>
        </div>

        @foreach ([
            ['Accepted', $stats['accepted'], 'Waiting for the rider or the customer', 'fa-box-open', 'bg-emerald-50 text-emerald-600'],
            ['With rider', $stats['with_rider'], 'Waiting for the customer to confirm', 'fa-motorcycle', 'bg-sky-50 text-sky-600'],
            ['Completed today', $stats['completed_today'], $money($stats['sales_today']) . ' sold online', 'fa-circle-check', 'bg-slate-100 text-slate-500'],
        ] as [$label, $value, $hint, $icon, $iconStyle])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between">
                    <p class="text-[13px] font-medium text-slate-500">{{ $label }}</p>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg {{ $iconStyle }}"><i class="fas {{ $icon }} text-sm"></i></span>
                </div>
                <p class="mt-3 text-[26px] font-bold leading-none text-slate-900 sm:text-[28px]">{{ $value }}</p>
                <p class="mt-2 truncate text-xs text-slate-500">{{ $hint }}</p>
            </div>
        @endforeach
    </section>

    {{-- ============ Tabs and search ============ --}}
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-100 px-2 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Order status">
            @foreach ($tabs as $value => $label)
                <a href="{{ $tabUrl($value) }}"
                   class="relative flex flex-shrink-0 items-center gap-2 px-3.5 pb-3 pt-2 text-sm font-semibold transition {{ $tab === $value ? 'text-emerald-700' : 'text-slate-500 hover:text-slate-900' }}"
                   @if ($tab === $value) aria-current="page" @endif>
                    {{ $label }}
                    <span class="rounded-md px-1.5 py-0.5 text-xs {{ $tab === $value ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$value] }}</span>
                    @if ($tab === $value)
                        <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-emerald-600"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('vendor.orders') }}" class="flex gap-2 p-3">
            @if ($tab !== 'open')<input type="hidden" name="status" value="{{ $tab }}">@endif
            @if ($scope->param())<input type="hidden" name="branch" value="{{ $scope->param() }}">@endif
            <div class="relative min-w-0 flex-1">
                <label for="order-search" class="sr-only">Search orders</label>
                <i class="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-slate-400"></i>
                <input id="order-search" type="search" name="search" value="{{ $search }}" placeholder="Order number, customer, phone or product" class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-700 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">
            </div>
            <button type="submit" class="h-10 flex-shrink-0 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Search</button>
            @if ($search !== '')
                <a href="{{ route('vendor.orders', array_filter(['status' => $tab === 'open' ? null : $tab, 'branch' => $scope->param()])) }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:border-slate-300 hover:text-slate-900" aria-label="Clear search"><i class="fas fa-xmark"></i></a>
            @endif
        </form>
    </section>

    {{-- ============ Orders ============ --}}
    @if ($fulfillments->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas {{ $search !== '' ? 'fa-magnifying-glass' : 'fa-inbox' }}"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">
                {{ $search !== '' ? 'No order matches that search' : ($tab === 'open' ? 'You are all caught up' : 'No orders here') }}
            </h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">
                {{ $search !== '' ? 'Check the spelling, or search by the order number on the customer’s phone.' : ($counts['all'] === 0 ? 'When a customer orders your products on the marketplace, the order appears here.' : 'Nothing needs your attention in this group right now.') }}
            </p>
        </div>
    @else
        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($fulfillments as $fulfillment)
                @php
                    $order = $fulfillment->order;
                    $isDelivery = $order->isDelivery();
                    $paidOnline = $order->payment_method === 'wallet';
                    $unitCount = (int) $fulfillment->items->sum('quantity');
                    $hoursLeft = $fulfillment->status === 'awaiting_confirmation'
                        ? max(0, $confirmationHours - (int) $fulfillment->created_at->diffInHours(now()))
                        : null;
                @endphp
                <article class="flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm {{ $fulfillment->status === 'awaiting_confirmation' ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }}">
                    {{-- Who and what kind --}}
                    <header class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 p-4 sm:px-5 sm:pt-5">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <span class="font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</span>
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tones[$fulfillment->statusTone()] }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $fulfillment->statusLabel() }}
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $fulfillment->created_at->format('M j · g:i A') }} ({{ $fulfillment->created_at->diffForHumans() }})
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @if ($scope->showsSeveral())
                                <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white" title="The branch this order is for">
                                    <i class="fas fa-store text-[10px] text-emerald-300"></i><span class="truncate">{{ $fulfillment->pharmacy?->name }}</span>
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                <i class="fas {{ $isDelivery ? 'fa-motorcycle' : 'fa-store' }} text-[10px]"></i>{{ $isDelivery ? 'Delivery' : 'Pickup' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold {{ $paidOnline ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                <i class="fas {{ $paidOnline ? 'fa-circle-check' : 'fa-hand-holding-dollar' }} text-[10px]"></i>
                                {{ $paidOnline ? 'Paid online' : ($isDelivery ? 'Pays on delivery' : 'Pays at counter') }}
                            </span>
                        </div>
                    </header>

                    <div class="flex items-center gap-3 border-y border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-5">
                        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-emerald-700 ring-1 ring-slate-200">{{ strtoupper(mb_substr($order->user->name ?? 'C', 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $order->user->name ?? 'Customer' }}</p>
                            @if ($order->contact_phone)
                                <a href="tel:{{ $order->contact_phone }}" class="text-xs font-medium text-emerald-700 hover:text-emerald-800"><i class="fas fa-phone mr-1 text-[10px]"></i>{{ $order->contact_phone }}</a>
                            @endif
                        </div>
                        @if ($hoursLeft !== null)
                            <span class="flex-shrink-0 rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"><i class="fas fa-clock mr-1"></i>{{ $hoursLeft > 0 ? $hoursLeft . 'h left to answer' : 'Answer now' }}</span>
                        @endif
                    </div>

                    {{-- Items --}}
                    <ul class="divide-y divide-slate-100 px-4 sm:px-5">
                        @foreach ($fulfillment->items as $item)
                            <li class="flex items-center gap-3 py-3">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-slate-900 text-sm font-bold text-white">{{ $item->quantity }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $item->product_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $item->dosage ?: $money($item->unit_price) . ' each' }}</p>
                                </div>
                                <p class="text-sm font-semibold text-slate-900">{{ $money($item->line_total) }}</p>
                            </li>
                        @endforeach
                    </ul>

                    @if ($order->customer_note)
                        <p class="mx-4 mb-3 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600 sm:mx-5"><i class="fas fa-comment-dots mr-1.5 text-slate-400"></i>{{ $order->customer_note }}</p>
                    @endif

                    @if ($fulfillment->reject_reason)
                        <p class="mx-4 mb-3 rounded-xl bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800 sm:mx-5"><i class="fas fa-circle-info mr-1.5"></i>{{ $fulfillment->status === 'rejected' ? 'Rejected' : 'Cancelled' }}: {{ $fulfillment->reject_reason }}</p>
                    @endif

                    {{-- Money and actions --}}
                    <footer class="mt-auto flex flex-col gap-3 border-t border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <p class="text-lg font-extrabold leading-tight text-slate-900">{{ $money($fulfillment->subtotal) }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $unitCount }} {{ Str::plural('item', $unitCount) }} ·
                                @if (! $fulfillment->isActive())
                                    not charged
                                @elseif ($paidOnline)
                                    {{ $money($fulfillment->payout_amount) }} to your wallet
                                @elseif ($isDelivery)
                                    rider collects from the customer
                                @else
                                    collect at the counter
                                @endif
                            </p>
                        </div>

                        @if ($canManage)
                            <div class="flex flex-wrap gap-2">
                                @if ($fulfillment->status === 'awaiting_confirmation')
                                    <button type="button" class="{{ $secondary }}" data-reject-url="{{ route('vendor.orders.reject', $fulfillment) }}" data-order="{{ $order->order_number }}">Reject</button>
                                    <form action="{{ route('vendor.orders.accept', $fulfillment) }}" method="POST" data-once>
                                        @csrf
                                        @if ($scope->param())<input type="hidden" name="branch" value="{{ $scope->param() }}">@endif
                                        <button type="submit" class="{{ $primary }}"><i class="fas fa-check text-xs"></i> Accept order</button>
                                    </form>
                                @elseif ($fulfillment->status === 'ready' && ! $isDelivery)
                                    <button type="button" class="{{ $secondary }}" data-reject-url="{{ route('vendor.orders.reject', $fulfillment) }}" data-order="{{ $order->order_number }}">Cannot supply</button>
                                    <button type="button" class="{{ $primary }}" data-collect-url="{{ route('vendor.orders.collect', $fulfillment) }}" data-order="{{ $order->order_number }}" data-customer="{{ $order->user->name ?? 'the customer' }}" data-due="{{ $paidOnline ? '' : $money($fulfillment->subtotal) }}">
                                        <i class="fas fa-hand-holding-medical text-xs"></i> Hand over
                                    </button>
                                @elseif (in_array($fulfillment->status, ['ready', 'dispatched'], true))
                                    @if ($fulfillment->status === 'dispatched')
                                        <form action="{{ route('vendor.orders.message', $fulfillment) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="{{ $secondary }} hover:!border-emerald-300 hover:!text-emerald-700"><i class="fas fa-comment-dots text-xs"></i> Message customer</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('vendor.dispatch', array_filter(['branch' => $scope->param()])) }}" class="{{ $primary }}"><i class="fas fa-motorcycle text-xs"></i> {{ $fulfillment->status === 'ready' ? 'Hand over to rider' : 'See delivery' }}</a>
                                @endif
                            </div>
                        @endif
                    </footer>
                </article>
            @endforeach
        </div>

        @if ($fulfillments->hasPages())
            <nav class="flex items-center justify-between gap-3" aria-label="Pages">
                @if ($fulfillments->onFirstPage())
                    <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Previous</span>
                @else
                    <a href="{{ $fulfillments->previousPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Previous</a>
                @endif
                <p class="text-sm text-slate-500">Page <span class="font-semibold text-slate-900">{{ $fulfillments->currentPage() }}</span> of {{ $fulfillments->lastPage() }}</p>
                @if ($fulfillments->hasMorePages())
                    <a href="{{ $fulfillments->nextPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Next</a>
                @else
                    <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Next</span>
                @endif
            </nav>
        @endif
    @endif
</div>

@if ($canManage)
    {{-- Reject --}}
    <x-modal id="reject-order-modal" title="Reject this order?" max-width="md">
        <form method="POST" id="reject-order-form">
            @csrf
            <p class="text-sm leading-6 text-slate-600">Order <span id="reject-order-number" class="font-mono font-semibold text-slate-900"></span> will be cancelled for your pharmacy. The stock goes back on sale, and the customer is told and refunded if they already paid.</p>

            <label for="reject-reason" class="mb-1.5 mt-5 block text-sm font-semibold text-slate-800">Why can it not be supplied?</label>
            <div class="mb-2 flex flex-wrap gap-1.5">
                @foreach (['Out of stock', 'Product expired or damaged', 'Prescription needed', 'Pharmacy closed'] as $reason)
                    <button type="button" data-reason="{{ $reason }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700">{{ $reason }}</button>
                @endforeach
            </div>
            <input id="reject-reason" type="text" name="reason" required minlength="3" maxlength="250" placeholder="The customer sees this" class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Go back</button>
                <button type="submit" form="reject-order-form" class="h-11 rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700">Reject order</button>
            </div>
        </x-slot:footer>
    </x-modal>

    {{-- Hand over at the counter --}}
    <x-modal id="collect-order-modal" title="Hand over the order" max-width="md">
        <form method="POST" id="collect-order-form">
            @csrf
            <p class="text-sm leading-6 text-slate-600">Ask <span id="collect-customer" class="font-semibold text-slate-900"></span> for the pickup code on their order <span id="collect-order-number" class="font-mono font-semibold text-slate-900"></span>.</p>

            <p id="collect-due" class="mt-4 hidden rounded-xl bg-amber-50 px-3.5 py-3 text-sm font-semibold text-amber-800"><i class="fas fa-hand-holding-dollar mr-1.5"></i>Collect <span></span> from the customer first.</p>

            <label for="pickup-code" class="mb-1.5 mt-5 block text-sm font-semibold text-slate-800">Pickup code</label>
            <input id="pickup-code" type="text" name="pickup_code" required maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="6 characters" class="w-full rounded-xl border border-slate-300 px-3.5 py-3.5 text-center font-mono text-2xl font-bold uppercase tracking-[0.35em] text-slate-900 placeholder:text-base placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
            <p class="mt-2 text-xs text-slate-500">Stock is reduced when you confirm.</p>
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
                <button type="submit" form="collect-order-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Confirm hand-over</button>
            </div>
        </x-slot:footer>
    </x-modal>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        const rejectForm = document.getElementById('reject-order-form');
        if (!rejectForm) return;

        const reason = document.getElementById('reject-reason');
        const collectForm = document.getElementById('collect-order-form');
        const code = document.getElementById('pickup-code');

        document.querySelectorAll('[data-reject-url]').forEach(button => {
            button.addEventListener('click', () => {
                rejectForm.action = button.dataset.rejectUrl;
                document.getElementById('reject-order-number').textContent = button.dataset.order;
                reason.value = '';
                CuraModal.open('reject-order-modal');
                setTimeout(() => reason.focus(), 320);
            });
        });

        document.querySelectorAll('[data-reason]').forEach(chip => {
            chip.addEventListener('click', () => { reason.value = chip.dataset.reason; reason.focus(); });
        });

        document.querySelectorAll('[data-collect-url]').forEach(button => {
            button.addEventListener('click', () => {
                collectForm.action = button.dataset.collectUrl;
                document.getElementById('collect-order-number').textContent = button.dataset.order;
                document.getElementById('collect-customer').textContent = button.dataset.customer;

                const due = document.getElementById('collect-due');
                due.classList.toggle('hidden', !button.dataset.due);
                due.querySelector('span').textContent = button.dataset.due;

                code.value = '';
                CuraModal.open('collect-order-modal');
                setTimeout(() => code.focus(), 320);
            });
        });

        // A second click on Accept must not send twice.
        document.querySelectorAll('form[data-once], #reject-order-form, #collect-order-form').forEach(form => {
            form.addEventListener('submit', () => {
                document.querySelectorAll('button[type="submit"]').forEach(button => {
                    if (button.form === form) { button.disabled = true; button.classList.add('opacity-60', 'cursor-wait'); }
                });
            });
        });
    })();
</script>
@endpush
