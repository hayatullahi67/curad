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

    $isDelivery = $order->isDelivery();
    $delivery = $order->delivery;
    $active = $order->fulfillments->filter->isActive();
    $dropped = $order->fulfillments->reject->isActive();
    $cancelled = $order->status === 'cancelled';
    $payOnCollection = $order->payment_method === 'pay_on_collection';

    // The four steps of the journey, and how many are behind us.
    $steps = $isDelivery
        ? [['fa-receipt', 'Order placed'], ['fa-clipboard-check', 'Confirmed'], ['fa-motorcycle', 'On the way'], ['fa-house-circle-check', 'Delivered']]
        : [['fa-receipt', 'Order placed'], ['fa-clipboard-check', 'Confirmed'], ['fa-box-open', 'Ready for pickup'], ['fa-circle-check', 'Collected']];
    $done = match ($order->status) {
        'awaiting_confirmation', 'partially_confirmed' => 1,
        'processing' => 2,
        'ready_for_pickup', 'dispatched' => 3,
        'completed', 'partially_fulfilled' => 4,
        default => 0,
    };

    $headline = match ($order->status) {
        'awaiting_confirmation' => 'Waiting for the pharmacy to confirm your order.',
        'partially_confirmed' => 'Some pharmacies have confirmed. Waiting for the rest.',
        'processing' => $isDelivery ? 'Confirmed. Waiting for the rider to collect your order.' : 'Confirmed. The pharmacy is getting your order ready.',
        'ready_for_pickup' => 'Your order is ready. Show the pickup code at the counter.',
        'dispatched' => $delivery?->status === 'in_transit' ? 'The rider is on the way. Mark the order as delivered when you receive it.' : 'The rider is collecting your items.',
        'completed' => $isDelivery ? 'Delivered. Thank you for ordering with CuraD.' : 'Collected. Thank you for ordering with CuraD.',
        'partially_fulfilled' => 'Completed, but part of the order could not be supplied.',
        default => 'This order was cancelled.',
    };
@endphp

@section('title', 'Order ' . $order->order_number)
@section('header_title', 'Order ' . $order->order_number)

@section('content')
<div class="mx-auto -mt-3 max-w-[1100px] space-y-5">

    <a href="{{ route('user.orders') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-900"><i class="fas fa-arrow-left text-xs"></i> All orders</a>

    {{-- Status and progress --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4 p-4 sm:p-6">
            <div class="min-w-0">
                <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                    <span class="font-mono text-lg font-bold text-slate-900">{{ $order->order_number }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tones[$order->statusTone()] }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $order->statusLabel() }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        <i class="fas {{ $isDelivery ? 'fa-motorcycle' : 'fa-store' }} text-[10px]"></i>{{ $isDelivery ? 'Delivery' : 'Pickup' }}
                    </span>
                </p>
                <p class="mt-2 text-[15px] font-medium text-slate-900">{{ $headline }}</p>
                <p class="mt-1 text-xs text-slate-500">Placed {{ $order->placed_at?->format('l, M j, Y · g:i A') }}</p>
            </div>

            @if ($order->canBeCancelledByCustomer())
                <button type="button" data-cura-modal-open="cancel-order-modal" class="inline-flex h-10 flex-shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:border-rose-300 hover:text-rose-700">
                    <i class="fas fa-xmark text-xs"></i> Cancel order
                </button>
            @endif
        </div>

        @unless ($cancelled)
            <ol class="grid grid-cols-4 border-t border-slate-100 bg-slate-50/60 px-2 py-5 sm:px-6">
                @foreach ($steps as $index => [$icon, $label])
                    @php $reached = $index < $done; $current = $index === $done; @endphp
                    <li class="relative flex flex-col items-center text-center">
                        @if ($index > 0)
                            <span class="absolute right-1/2 top-5 h-0.5 w-full -translate-y-1/2 {{ $reached ? 'bg-emerald-500' : 'bg-slate-200' }}" aria-hidden="true"></span>
                        @endif
                        <span class="relative flex h-10 w-10 items-center justify-center rounded-full text-sm {{ $reached ? 'bg-emerald-600 text-white' : ($current ? 'bg-white text-emerald-600 ring-2 ring-emerald-500' : 'bg-white text-slate-300 ring-1 ring-slate-200') }}">
                            <i class="fas {{ $reached ? 'fa-check' : $icon }}"></i>
                        </span>
                        <span class="mt-2 px-1 text-[11px] font-semibold leading-4 sm:text-xs {{ $reached || $current ? 'text-slate-900' : 'text-slate-400' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
        @endunless
    </section>

    @if ($dropped->isNotEmpty() && ! $cancelled)
        <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
            <p>
                {{ $dropped->pluck('pharmacy.name')->implode(' and ') }} could not supply {{ $dropped->count() === 1 ? 'its' : 'their' }} part of this order.
                {{ $order->payment_method === 'wallet' ? $money($order->refunded_total) . ' has been returned to your wallet.' : 'You will not be charged for those items.' }}
                The rest of your order carries on.
            </p>
        </div>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[minmax(0,1fr)_21rem] lg:items-start">
        <div class="space-y-5">

            {{-- Delivery --}}
            @if ($isDelivery && $delivery)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900">Delivery</h2>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $delivery->statusLabel() }}</span>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-location-dot"></i></span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500">Delivering to</p>
                                <p class="mt-0.5 break-words text-sm font-medium text-slate-900">{{ $delivery->address }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $delivery->recipient_phone }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><i class="fas fa-motorcycle"></i></span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500">Rider</p>
                                @if ($delivery->rider_name)
                                    <p class="mt-0.5 text-sm font-medium text-slate-900">{{ $delivery->rider_name }}</p>
                                    <a href="tel:{{ $delivery->rider_phone }}" class="mt-0.5 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-phone text-[10px]"></i>{{ $delivery->rider_phone }}</a>
                                @else
                                    <p class="mt-0.5 text-sm text-slate-500">Shown here once the pharmacy hands over your order</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($delivery->status === 'in_transit')
                        <div class="mt-5 flex flex-col gap-4 rounded-xl bg-slate-900 p-4 text-white sm:flex-row sm:items-center sm:justify-between sm:p-5">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">Has your package arrived?</p>
                                <p class="mt-1 text-xs leading-5 text-slate-300">Press the button only when the package is in your hands. It tells the pharmacy the order is complete.</p>
                            </div>
                            <form action="{{ route('user.orders.received', $order) }}" method="POST" onsubmit="return confirm('Have you received this order? This cannot be undone.')" class="flex-shrink-0">
                                @csrf
                                <button type="submit" class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 text-[15px] font-semibold text-white transition hover:bg-emerald-400 sm:w-auto">
                                    <i class="fas fa-check text-xs"></i> Mark as delivered
                                </button>
                            </form>
                        </div>
                    @endif

                    @if ($delivery->stops->count() > 1)
                        <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Rider's route</p>
                        <ol class="mt-2 space-y-2">
                            @foreach ($delivery->stops as $stop)
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $stop->status === 'collected' ? 'bg-emerald-600 text-white' : ($stop->status === 'skipped' ? 'bg-slate-100 text-slate-400' : 'bg-white text-slate-600 ring-1 ring-slate-300') }}">
                                        @if ($stop->status === 'collected')<i class="fas fa-check text-[10px]"></i>@else{{ $stop->sequence }}@endif
                                    </span>
                                    <span class="min-w-0 flex-1 truncate {{ $stop->status === 'skipped' ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $stop->pharmacy?->name }}</span>
                                    <span class="text-xs text-slate-500">{{ ['pending' => 'Waiting', 'collected' => 'Collected', 'skipped' => 'Not supplying'][$stop->status] ?? '' }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            @endif

            {{-- One card per pharmacy --}}
            @foreach ($order->fulfillments as $fulfillment)
                @php
                    $pharmacy = $fulfillment->pharmacy;
                    $place = collect([$pharmacy?->address, $pharmacy?->city, $pharmacy?->state])->filter()->unique()->implode(', ');
                    $mapQuery = $pharmacy?->latitude && $pharmacy?->longitude ? $pharmacy->latitude . ',' . $pharmacy->longitude : ($pharmacy?->name . ' ' . $place);
                    $live = $fulfillment->isActive();
                    $showCode = ! $isDelivery && $live && $fulfillment->status !== 'completed';
                @endphp
                <section class="overflow-hidden rounded-2xl border bg-white shadow-sm {{ $live ? 'border-slate-200' : 'border-slate-200 opacity-80' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3 p-4 sm:px-6 sm:pt-6">
                        <div class="flex min-w-0 gap-3">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><i class="fas fa-store"></i></span>
                            <div class="min-w-0">
                                <h2 class="break-words text-base font-bold text-slate-900">{{ $pharmacy?->displayName() ?? 'Pharmacy' }}</h2>
                                @if ($place)
                                    <p class="mt-0.5 text-sm leading-5 text-slate-600">{{ $place }}</p>
                                @endif
                                @if ($live)
                                    <p class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs font-semibold">
                                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" rel="noopener" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-map-location-dot mr-1"></i>Directions</a>
                                        @if ($pharmacy?->phone)
                                            <a href="tel:{{ $pharmacy->phone }}" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-phone mr-1"></i>{{ $pharmacy->phone }}</a>
                                        @endif
                                        <button type="submit" form="message-pharmacy-{{ $fulfillment->id }}" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-comment-dots mr-1"></i>Message</button>
                                    </p>
                                @endif
                            </div>
                        </div>
                        <span class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $tones[$fulfillment->statusTone()] }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $fulfillment->status === 'awaiting_confirmation' ? 'Waiting to confirm' : $fulfillment->statusLabel() }}
                        </span>
                    </div>

                    <form id="message-pharmacy-{{ $fulfillment->id }}" action="{{ route('user.chat.create') }}" method="POST" class="hidden">
                        @csrf
                        <input type="hidden" name="fulfillment_id" value="{{ $fulfillment->id }}">
                    </form>

                    @unless ($live)
                        <p class="mx-4 mb-1 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600 sm:mx-6">
                            <i class="fas fa-circle-info mr-1.5 text-slate-400"></i>{{ $fulfillment->status === 'rejected' ? 'Not supplied' : 'Cancelled' }}@if ($fulfillment->reject_reason): {{ $fulfillment->reject_reason }}@endif
                        </p>
                    @endunless

                    @if ($showCode)
                        <div class="mx-4 mb-1 flex flex-col gap-1 rounded-xl p-4 sm:mx-6 sm:flex-row sm:items-center sm:justify-between {{ $fulfillment->status === 'ready' ? 'bg-emerald-600 text-white' : 'bg-slate-50 text-slate-900' }}">
                            <div>
                                <p class="text-xs font-medium {{ $fulfillment->status === 'ready' ? 'text-emerald-100' : 'text-slate-500' }}">Pickup code</p>
                                <p class="mt-0.5 font-mono text-2xl font-bold tracking-[0.3em]">{{ $fulfillment->pickup_code }}</p>
                            </div>
                            <p class="text-xs leading-5 sm:max-w-[15rem] sm:text-right {{ $fulfillment->status === 'ready' ? 'text-emerald-50' : 'text-slate-500' }}">
                                {{ $fulfillment->status === 'ready' ? 'Ready now. Show this code at the counter to collect.' : 'You will need this code when the order is ready.' }}
                                @if ($payOnCollection) Pay {{ $money($fulfillment->subtotal) }} at this pharmacy. @endif
                            </p>
                        </div>
                    @endif

                    <ul class="divide-y divide-slate-100 px-4 sm:px-6">
                        @foreach ($fulfillment->items as $item)
                            <li class="flex items-center gap-3 py-3">
                                <span class="relative h-12 w-12 flex-shrink-0 overflow-hidden rounded-xl bg-slate-100">
                                    <span class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="fas fa-pills text-sm"></i></span>
                                    @if ($item->photo_url)
                                        <img src="{{ $item->photo_url }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover {{ $live ? '' : 'grayscale' }}">
                                    @endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium {{ $live ? 'text-slate-900' : 'text-slate-500 line-through' }}">{{ $item->product_name }}</p>
                                    <p class="text-xs text-slate-500">{{ $item->quantity }} × {{ $money($item->unit_price) }}@if ($item->dosage) · {{ $item->dosage }}@endif</p>
                                </div>
                                <p class="text-sm font-semibold {{ $live ? 'text-slate-900' : 'text-slate-400 line-through' }}">{{ $money($item->line_total) }}</p>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        {{-- Money --}}
        <aside class="space-y-5 lg:sticky lg:top-0">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-bold text-slate-900">Payment</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">Products</dt>
                        <dd class="font-semibold text-slate-900">{{ $money($order->subtotal) }}</dd>
                    </div>
                    @if ($order->service_fee > 0)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Service fee</dt>
                            <dd class="font-semibold text-slate-900">{{ $money($order->service_fee) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">
                            Delivery
                            @if ($isDelivery && $delivery?->distance_km)
                                <span class="block text-xs text-slate-400">{{ $delivery->distance_km }} km{{ $delivery->duration_minutes ? ' · about ' . $delivery->duration_minutes . ' min' : '' }}{{ $delivery->fee_multiplier > 1 ? ' · busy time ×' . rtrim(rtrim(number_format($delivery->fee_multiplier, 2), '0'), '.') : '' }}</span>
                            @endif
                        </dt>
                        <dd class="font-semibold {{ $isDelivery ? 'text-slate-900' : 'text-emerald-700' }}">{{ $isDelivery ? $money($order->delivery_fee) : 'Free pickup' }}</dd>
                    </div>
                    @if ($dropped->isNotEmpty())
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">{{ $order->payment_method === 'wallet' ? 'Refunded' : 'Not supplied' }}</dt>
                            <dd class="font-semibold text-rose-600">− {{ $money($order->total - $currentTotal) }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-900">{{ $order->payment_status === 'paid' || $order->payment_method === 'wallet' ? 'Total' : 'To pay' }}</p>
                    <p class="text-xl font-extrabold text-slate-900">{{ $money($currentTotal) }}</p>
                </div>

                <div class="mt-4 flex items-center justify-between rounded-xl bg-slate-50 px-3.5 py-3 text-sm">
                    <span class="flex items-center gap-2 text-slate-600"><i class="fas {{ $order->payment_method === 'wallet' ? 'fa-wallet' : 'fa-hand-holding-dollar' }} text-slate-400"></i>{{ $order->paymentLabel() }}</span>
                    <span class="font-semibold {{ $order->payment_status === 'paid' ? 'text-emerald-700' : 'text-slate-900' }}">{{ $cancelled && $order->payment_status === 'pending' ? 'Nothing to pay' : $order->paymentStatusLabel() }}</span>
                </div>

                @if ($payOnCollection && $order->isOpen())
                    <p class="mt-3 text-xs leading-5 text-slate-500">
                        @if ($isDelivery)
                            Pay the rider {{ $money($currentTotal) }} when your order arrives.
                        @elseif ($active->count() > 1)
                            Pay each pharmacy for its own items when you collect.
                        @else
                            Pay at the pharmacy when you collect.
                        @endif
                    </p>
                @endif
            </section>

            @if ($order->customer_note)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-slate-900">Your note</h2>
                    <p class="mt-2 break-words text-sm leading-6 text-slate-600">{{ $order->customer_note }}</p>
                </section>
            @endif
        </aside>
    </div>
</div>

@if ($order->canBeCancelledByCustomer())
    <x-modal id="cancel-order-modal" title="Cancel this order?" max-width="md">
        <form action="{{ route('user.orders.cancel', $order) }}" method="POST" id="cancel-order-form">
            @csrf
            <p class="text-sm leading-6 text-slate-600">
                The items go back on the shelf for other customers.
                {{ $order->payment_method === 'wallet' ? $money($currentTotal) . ' will be returned to your wallet straight away.' : 'You have not paid anything, so there is nothing to refund.' }}
            </p>
            <label for="cancel-reason" class="mb-1.5 mt-5 block text-sm font-semibold text-slate-800">Reason <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="cancel-reason" type="text" name="reason" maxlength="250" placeholder="e.g. Ordered by mistake" class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Keep my order</button>
                <button type="submit" form="cancel-order-form" class="h-11 rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700">Yes, cancel it</button>
            </div>
        </x-slot:footer>
    </x-modal>
@endif
@endsection
