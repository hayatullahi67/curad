{{-- One delivery on the Logistics page. $lane is waiting | on_the_way | delivered. --}}
@php
    $order = $fulfillment->order;
    $delivery = $order->delivery;
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $unitCount = (int) $fulfillment->items->sum('quantity');
    $stops = $delivery?->stops ?? collect();
    $othersPending = $stops->where('status', 'pending')->where('order_fulfillment_id', '!=', $fulfillment->id);
    $payOnDelivery = $order->payment_method === 'pay_on_collection';
    $accent = ['waiting' => 'bg-amber-400', 'on_the_way' => 'bg-sky-500', 'delivered' => 'bg-emerald-500'][$lane];
    $when = match ($lane) {
        'waiting' => 'Accepted ' . $fulfillment->ready_at?->diffForHumans(),
        'on_the_way' => 'Left ' . $fulfillment->dispatched_at?->diffForHumans(),
        default => 'Delivered ' . $fulfillment->completed_at?->format('g:i A'),
    };
    $recipient = $delivery?->recipient_name ?: ($order->user->full_name ?? 'Customer');
    $shownItems = $fulfillment->items->take(3);
    $hiddenItems = $fulfillment->items->count() - $shownItems->count();
    $iconButton = 'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700';
@endphp
<article class="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <span class="absolute inset-y-0 left-0 w-1 {{ $accent }}" aria-hidden="true"></span>

    {{-- Which order, from which branch, and when --}}
    <header class="px-4 pb-3 pt-4 sm:px-5">
        <div class="flex items-baseline justify-between gap-3">
            <p class="whitespace-nowrap font-mono text-[15px] font-bold leading-tight text-slate-900">{{ $order->order_number }}</p>
            <p class="min-w-0 truncate text-xs text-slate-500">{{ $when }}</p>
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @if (($scope ?? null)?->showsSeveral())
                <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg bg-slate-900 px-2 py-1 text-[11px] font-semibold text-white" title="The branch this delivery leaves from">
                    <i class="fas fa-store text-[9px] text-emerald-300"></i><span class="truncate">{{ $fulfillment->pharmacy?->name }}</span>
                </span>
            @endif
            <span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">{{ $unitCount }} {{ Str::plural('item', $unitCount) }}</span>
        </div>
    </header>

    <div class="flex-1 space-y-3 px-4 pb-4 sm:px-5">
        {{-- Who it is going to --}}
        <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-3">
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-emerald-700 ring-1 ring-slate-200">{{ strtoupper(mb_substr($recipient, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $recipient }}</p>
                @if ($delivery?->address)
                    <p class="mt-0.5 line-clamp-2 break-words text-[13px] leading-5 text-slate-600" title="{{ $delivery->address }}">{{ $delivery->address }}</p>
                @endif
                @if ($delivery?->distance_km)
                    <p class="mt-1 text-xs font-medium text-slate-500"><i class="fas fa-route mr-1 text-[10px] text-slate-400"></i>{{ $delivery->distance_km }} km{{ $delivery->duration_minutes ? ' · about ' . $delivery->duration_minutes . ' min' : '' }}</p>
                @endif
            </div>
            @if ($lane !== 'delivered' && $delivery)
                <div class="flex flex-shrink-0 gap-1.5">
                    @if ($delivery->recipient_phone)
                        <a href="tel:{{ $delivery->recipient_phone }}" class="{{ $iconButton }}" aria-label="Call {{ $recipient }} on {{ $delivery->recipient_phone }}" title="Call {{ $delivery->recipient_phone }}"><i class="fas fa-phone text-sm"></i></a>
                    @endif
                    <a href="{{ $delivery->mapUrl() }}" target="_blank" rel="noopener" class="{{ $iconButton }}" aria-label="Open the delivery address in maps" title="Open in maps"><i class="fas fa-map-location-dot text-sm"></i></a>
                </div>
            @endif
        </div>

        @if ($lane !== 'delivered' && $delivery)
            @if ($order->customer_note)
                <p class="flex gap-2 rounded-xl border border-slate-100 px-3 py-2.5 text-[13px] leading-5 text-slate-600"><i class="fas fa-comment-dots mt-1 text-xs text-slate-400"></i><span class="min-w-0 break-words">{{ $order->customer_note }}</span></p>
            @endif

            {{-- What goes in the bag --}}
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">In the bag</p>
                <ul class="mt-1.5 space-y-1 text-sm">
                    @foreach ($shownItems as $item)
                        <li class="flex gap-2 text-slate-700"><span class="w-7 flex-shrink-0 font-semibold text-slate-900">{{ $item->quantity }}×</span><span class="min-w-0 truncate">{{ $item->product_name }}</span></li>
                    @endforeach
                </ul>
                @if ($hiddenItems > 0)
                    <details class="group mt-1 text-sm">
                        <summary class="cursor-pointer list-none text-xs font-semibold text-emerald-700 hover:text-emerald-800 [&::-webkit-details-marker]:hidden"><span class="group-open:hidden">+ {{ $hiddenItems }} more {{ Str::plural('item', $hiddenItems) }}</span><span class="hidden group-open:inline">Show less</span></summary>
                        <ul class="mt-1 space-y-1">
                            @foreach ($fulfillment->items->slice(3) as $item)
                                <li class="flex gap-2 text-slate-700"><span class="w-7 flex-shrink-0 font-semibold text-slate-900">{{ $item->quantity }}×</span><span class="min-w-0 truncate">{{ $item->product_name }}</span></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>

            {{-- The rider's route when several pharmacies are involved --}}
            @if ($stops->count() > 1)
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Rider's route</p>
                    <ol class="mt-1.5 space-y-1.5">
                        @foreach ($stops as $stop)
                            @php $mine = $stop->order_fulfillment_id === $fulfillment->id; @endphp
                            <li class="flex items-center gap-2.5 text-xs">
                                <span class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full font-bold {{ $stop->status === 'collected' ? 'bg-emerald-600 text-white' : ($stop->status === 'skipped' ? 'bg-slate-100 text-slate-400' : 'bg-white text-slate-600 ring-1 ring-slate-300') }}">
                                    @if ($stop->status === 'collected')<i class="fas fa-check text-[9px]"></i>@else{{ $stop->sequence }}@endif
                                </span>
                                <span class="min-w-0 flex-1 truncate {{ $stop->status === 'skipped' ? 'text-slate-400 line-through' : ($mine ? 'font-semibold text-slate-900' : 'text-slate-600') }}">{{ $mine ? 'You' : $stop->pharmacy?->name }}</span>
                                <span class="text-slate-400">{{ ['pending' => 'Waiting', 'collected' => 'Collected', 'skipped' => 'Not supplying'][$stop->status] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($delivery->rider_name)
                <div class="flex items-center gap-3 rounded-xl bg-slate-900 px-3 py-2.5 text-white">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-motorcycle text-sm"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-medium text-slate-400">Rider</p>
                        <p class="truncate text-sm font-semibold">{{ $delivery->rider_name }}</p>
                    </div>
                    <a href="tel:{{ $delivery->rider_phone }}" class="inline-flex h-9 flex-shrink-0 items-center gap-1.5 rounded-lg bg-white/10 px-3 text-xs font-semibold text-emerald-300 transition hover:bg-white/20" aria-label="Call the rider on {{ $delivery->rider_phone }}">
                        <i class="fas fa-phone text-[10px]"></i>{{ $delivery->rider_phone }}
                    </a>
                </div>
            @endif

            @if ($payOnDelivery)
                <p class="flex gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-xs font-medium leading-5 text-amber-900">
                    <i class="fas fa-hand-holding-dollar mt-1 text-amber-600"></i>
                    <span>Rider collects <span class="font-bold">{{ $money($order->currentTotal()) }}</span> from the customer{{ $stops->count() > 1 ? '. ' . $money($fulfillment->subtotal) . ' of it is yours' : ' (' . $money($fulfillment->subtotal) . ' products + ' . $money($order->delivery_fee) . ' delivery)' }}.</span>
                </p>
            @endif
        @else
            <p class="flex items-center justify-between gap-3 text-sm">
                <span class="inline-flex items-center gap-1.5 font-semibold text-emerald-700"><i class="fas fa-circle-check text-xs"></i>Confirmed by the customer</span>
                <span class="font-bold text-slate-900">{{ $money($fulfillment->subtotal) }}</span>
            </p>
        @endif
    </div>

    @if ($lane === 'waiting')
        <footer class="border-t border-slate-100 bg-slate-50/70 p-3 sm:px-5">
            <button type="button"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"
                    data-handover-url="{{ route('vendor.dispatch.handover', $fulfillment) }}"
                    data-order="{{ $order->order_number }}"
                    data-rider-name="{{ $delivery?->rider_name }}"
                    data-rider-phone="{{ $delivery?->rider_phone }}">
                <i class="fas fa-motorcycle text-xs"></i> Hand over to rider
            </button>
        </footer>
    @elseif ($lane === 'on_the_way')
        <footer class="space-y-2.5 border-t border-slate-100 bg-slate-50/70 p-3 sm:px-5">
            @if ($othersPending->isNotEmpty())
                <p class="flex items-start gap-2 text-xs leading-5 text-slate-600"><i class="fas fa-circle-notch fa-spin mt-1 text-slate-400"></i><span>Rider still has to collect from {{ $othersPending->map(fn ($stop) => $stop->pharmacy?->name)->implode(', ') }}.</span></p>
            @else
                <p class="flex items-start gap-2 text-xs leading-5 text-slate-600"><i class="fas fa-hourglass-half mt-1 text-slate-400"></i><span>Your part is done. The customer marks it delivered when the package arrives.</span></p>
            @endif
            <form action="{{ route('vendor.orders.message', $fulfillment) }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-white text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                    <i class="fas fa-comment-dots text-xs"></i> Message customer
                </button>
            </form>
        </footer>
    @endif
</article>
