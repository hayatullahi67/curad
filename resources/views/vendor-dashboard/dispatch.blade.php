{{--
    Logistics: delivery orders in three stages. One stage is on screen at a
    time, chosen with the dropdown above the table. Each stage is a table,
    one row per delivery; "View" opens everything about that delivery.
--}}
@extends('layouts.app')

@php
    // Each stage: key, title, what it means, icon, colours when chosen, its deliveries, and what to say when empty.
    // Which stage a delivery is in, from its status.
    $stageOf = fn ($fulfillment) => ['ready' => 'waiting', 'dispatched' => 'on_the_way'][$fulfillment->status] ?? 'delivered';
    $stagePills = [
        'waiting' => ['Waiting for rider', 'bg-amber-50 text-amber-700 ring-amber-600/15'],
        'on_the_way' => ['On the way', 'bg-sky-50 text-sky-700 ring-sky-600/15'],
        'delivered' => ['Delivered', 'bg-emerald-50 text-emerald-700 ring-emerald-600/15'],
    ];

    // Every delivery together, the most recent movement first.
    $all = $waiting->concat($onTheWay)->concat($delivered)
        ->sortByDesc(fn ($fulfillment) => $fulfillment->completed_at ?? $fulfillment->dispatched_at ?? $fulfillment->ready_at ?? $fulfillment->created_at)
        ->values();

    $lanes = [
        ['all', 'All deliveries', 'Everything waiting, on the way and delivered today. Newest first.', 'fa-layer-group', 'text-slate-600', 'bg-slate-500', $all, 'fa-truck-fast', 'No deliveries yet', 'When you accept a delivery order it appears here.'],
        ['waiting', 'Waiting for rider', 'Accepted. Hand each one to its rider.', 'fa-box-open', 'text-amber-600', 'bg-amber-500', $waiting, 'fa-box-open', 'Nothing is waiting for a rider', 'When you accept a delivery order it appears here, ready to hand over.'],
        ['on_the_way', 'On the way', 'With the rider. The customer marks it delivered.', 'fa-motorcycle', 'text-sky-600', 'bg-sky-500', $onTheWay, 'fa-motorcycle', 'No delivery is on the road', 'Orders you hand to a rider stay here until the customer confirms them.'],
        ['delivered', 'Delivered today', 'The customer confirmed these today.', 'fa-house-circle-check', 'text-emerald-600', 'bg-emerald-500', $delivered, 'fa-house-circle-check', 'No deliveries confirmed yet today', 'Deliveries the customer has marked as delivered today show here.'],
    ];

    // Open on everything; the dropdown narrows it to one stage.
    $start = 'all';
    $input = 'w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
@endphp

@section('title', 'Logistics')
@section('header_title', 'Logistics')

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-4 sm:space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="max-w-2xl text-sm leading-6 text-slate-500">Delivery orders from {{ $scope->label() }}. Hand each one to its rider; the customer marks it delivered.</p>
        <a href="{{ route('vendor.orders', array_filter(['branch' => $scope->param()])) }}" class="inline-flex h-10 flex-shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-900">
            <i class="fas fa-bag-shopping text-xs text-slate-400"></i> Online Orders
        </a>
    </div>

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->first() }}</p>
        </div>
    @endif

    @include('vendor-dashboard.partials.branch-filter', ['route' => 'vendor.dispatch', 'keep' => [], 'waiting' => $waitingByBranch])

    @if ($preparing)
        <a href="{{ route('vendor.orders', array_filter(['status' => 'awaiting_confirmation', 'branch' => $scope->param()])) }}" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:border-amber-300">
            <i class="fas fa-bell text-amber-600"></i>
            <span class="min-w-0 flex-1"><span class="font-semibold">{{ $preparing }} new delivery {{ Str::plural('order', $preparing) }}</span> {{ $preparing === 1 ? 'is' : 'are' }} waiting to be accepted on Online Orders.</span>
            <i class="fas fa-arrow-right flex-shrink-0 text-xs"></i>
        </a>
    @endif

    {{-- ============ Which stage to show ============ --}}
    <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:gap-3 sm:p-4">
        <label for="lane-select" class="flex-shrink-0 text-sm font-semibold text-slate-800"><i class="fas fa-filter mr-1.5 text-xs text-slate-400"></i>Show</label>
        <div class="relative sm:w-80">
            <select id="lane-select" class="h-11 w-full cursor-pointer appearance-none rounded-xl border border-slate-300 bg-white pl-3.5 pr-10 text-sm font-semibold text-slate-900 transition focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
                @foreach ($lanes as [$lane, $title, $subtitle, $icon, $iconColour, $bar, $cards])
                    <option value="{{ $lane }}" data-about="{{ $subtitle }}" @selected($lane === $start)>{{ $title }} ({{ $cards->count() }})</option>
                @endforeach
            </select>
            <i class="fas fa-chevron-down pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
        </div>
        <p id="lane-about" class="min-w-0 text-sm leading-5 text-slate-500" aria-live="polite">{{ collect($lanes)->firstWhere(0, $start)[2] }}</p>
    </div>

    {{-- ============ The deliveries of the chosen stage, as a table ============ --}}
    @foreach ($lanes as [$lane, $title, $subtitle, $icon, $iconColour, $bar, $cards, $emptyIcon, $emptyTitle, $emptyText])
        <section id="lane-{{ $lane }}" data-lane-panel="{{ $lane }}" aria-label="{{ $title }}" class="{{ $lane === $start ? '' : 'hidden' }}">

            @if ($cards->isNotEmpty())
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {{-- Column names: shown from tablet width up --}}
                    <div class="hidden grid-cols-[minmax(0,1.1fr)_minmax(0,1.6fr)_minmax(0,1.2fr)_minmax(0,1fr)_auto] gap-4 border-b border-slate-100 bg-slate-50 px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:grid">
                        <span>Order</span>
                        <span>Customer</span>
                        <span>Rider</span>
                        <span>To collect</span>
                        <span class="w-[13.5rem] text-right">Action</span>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($cards as $fulfillment)
                            @php
                                $rowLane = $lane === 'all' ? $stageOf($fulfillment) : $lane;
                                $order = $fulfillment->order;
                                $delivery = $order->delivery;
                                $unitCount = (int) $fulfillment->items->sum('quantity');
                                $recipient = $delivery?->recipient_name ?: ($order->user->full_name ?? 'Customer');
                                $payOnDelivery = $order->payment_method === 'pay_on_collection';
                                $when = match ($rowLane) {
                                    'waiting' => 'Accepted ' . $fulfillment->ready_at?->diffForHumans(),
                                    'on_the_way' => 'Left ' . $fulfillment->dispatched_at?->diffForHumans(),
                                    default => 'Delivered ' . $fulfillment->completed_at?->format('g:i A'),
                                };
                            @endphp
                            <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 px-4 py-4 transition hover:bg-slate-50/70 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.6fr)_minmax(0,1.2fr)_minmax(0,1fr)_auto] md:px-5 md:py-3.5" data-row="{{ $fulfillment->id }}">
                                {{-- Order --}}
                                <div class="min-w-0">
                                    <p class="whitespace-nowrap font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ $when }} · {{ $unitCount }} {{ Str::plural('item', $unitCount) }}</p>
                                    @if ($lane === 'all')
                                        <p class="mt-1.5 mr-1 inline-flex items-center gap-1.5 rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1 ring-inset {{ $stagePills[$rowLane][1] }}"><span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $stagePills[$rowLane][0] }}</p>
                                    @endif
                                    @if ($scope->showsSeveral())
                                        <p class="mt-1.5 inline-flex max-w-full items-center gap-1.5 rounded-md bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold text-white" title="The branch this delivery leaves from">
                                            <i class="fas fa-store text-[8px] text-emerald-300"></i><span class="truncate">{{ $fulfillment->pharmacy?->name }}</span>
                                        </p>
                                    @endif
                                </div>

                                {{-- On a phone the amount sits beside the order number --}}
                                <p class="text-right text-sm font-bold text-slate-900 md:hidden">{{ $payOnDelivery ? '₦' . number_format((float) $order->currentTotal(), 2) : 'Paid' }}</p>

                                {{-- Customer --}}
                                <div class="col-span-2 min-w-0 md:col-span-1">
                                    <p class="truncate text-sm font-semibold text-slate-900"><i class="fas fa-user mr-1.5 text-[11px] text-slate-400 md:hidden"></i>{{ $recipient }}</p>
                                    @if ($delivery?->address)
                                        <p class="mt-0.5 truncate text-[13px] text-slate-500" title="{{ $delivery->address }}">{{ $delivery->address }}</p>
                                    @endif
                                </div>

                                {{-- Rider --}}
                                <div class="col-span-2 min-w-0 md:col-span-1">
                                    @if ($delivery?->rider_name)
                                        <p class="truncate text-sm font-medium text-slate-900"><i class="fas fa-motorcycle mr-1.5 text-[11px] text-slate-400"></i>{{ $delivery->rider_name }}</p>
                                        <a href="tel:{{ $delivery->rider_phone }}" class="mt-0.5 inline-block text-[13px] font-semibold text-emerald-700 hover:text-emerald-800">{{ $delivery->rider_phone }}</a>
                                    @else
                                        <p class="text-sm text-slate-400"><i class="fas fa-motorcycle mr-1.5 text-[11px] md:hidden"></i>No rider yet</p>
                                    @endif
                                </div>

                                {{-- To collect --}}
                                <div class="hidden min-w-0 md:block">
                                    @if ($payOnDelivery)
                                        <p class="text-sm font-bold text-slate-900">₦{{ number_format((float) $order->currentTotal(), 2) }}</p>
                                        <p class="mt-0.5 text-xs text-amber-700">{{ $rowLane === 'delivered' ? 'Collected by rider' : 'Rider collects' }}</p>
                                    @else
                                        <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700"><i class="fas fa-circle-check text-xs"></i>Paid online</p>
                                    @endif
                                </div>

                                {{-- Action --}}
                                <div class="col-span-2 flex gap-2 md:col-span-1 md:w-[13.5rem] md:justify-end">
                                    <button type="button" data-details-open="{{ $fulfillment->id }}" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 md:flex-none">
                                        <i class="fas fa-eye text-xs text-slate-400"></i> View
                                    </button>
                                    @if ($rowLane === 'waiting')
                                        <button type="button" class="inline-flex h-10 flex-[2] items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 md:flex-none"
                                                data-handover-url="{{ route('vendor.dispatch.handover', $fulfillment) }}" data-order="{{ $order->order_number }}"
                                                data-rider-name="{{ $delivery?->rider_name }}" data-rider-phone="{{ $delivery?->rider_phone }}">
                                            <i class="fas fa-motorcycle text-xs"></i> Hand over
                                        </button>
                                    @elseif ($rowLane === 'on_the_way')
                                        <form action="{{ route('vendor.orders.message', $fulfillment) }}" method="POST" class="flex flex-[2] md:flex-none">
                                            @csrf
                                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-white px-3.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                                                <i class="fas fa-comment-dots text-xs"></i> Message
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                {{-- Everything about this delivery, shown when "View" is pressed --}}
                                <template data-details="{{ $fulfillment->id }}">
                                    @include('vendor-dashboard.partials.delivery-card', ['fulfillment' => $fulfillment, 'lane' => $rowLane])
                                </template>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas {{ $emptyIcon }}"></i></span>
                    <h2 class="mt-5 text-base font-semibold text-slate-900">{{ $emptyTitle }}</h2>
                    <p class="mx-auto mt-1.5 max-w-sm text-sm leading-6 text-slate-500">{{ $emptyText }}</p>
                </div>
            @endif
        </section>
    @endforeach
</div>

{{-- Details of one delivery --}}
<x-modal id="details-modal" title="Delivery details" max-width="lg">
    <div id="details-body"></div>
</x-modal>

{{-- Hand to rider --}}
<x-modal id="handover-modal" title="Hand over to rider" max-width="md">
    <form method="POST" id="handover-form">
        @csrf
        <p class="text-sm leading-6 text-slate-600">Order <span id="handover-order" class="font-mono font-semibold text-slate-900"></span> leaves your shelf now. The customer is sent the rider's name and number, and marks the order delivered when it arrives.</p>
        <p id="handover-known" class="mt-3 hidden rounded-xl bg-sky-50 px-3.5 py-2.5 text-sm text-sky-900"><i class="fas fa-circle-info mr-1.5 text-sky-600"></i>Another pharmacy already gave this order to the rider below. Hand your package to the same rider.</p>

        @if ($recentRiders->isNotEmpty())
            <div id="rider-choices" class="mt-5">
                <p class="text-sm font-semibold text-slate-800">Riders you used before</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($recentRiders as $rider)
                        <button type="button" data-rider-pick data-name="{{ $rider->rider_name }}" data-phone="{{ $rider->rider_phone }}"
                            class="flex min-w-0 items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left text-sm transition hover:border-emerald-400 hover:bg-emerald-50 aria-pressed:border-emerald-500 aria-pressed:bg-emerald-50" aria-pressed="false">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><i class="fas fa-motorcycle text-xs"></i></span>
                            <span class="min-w-0"><span class="block truncate font-semibold text-slate-900">{{ $rider->rider_name }}</span><span class="block truncate text-xs text-slate-500">{{ $rider->rider_phone }}</span></span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <label for="rider-name" class="mb-1.5 mt-5 block text-sm font-semibold text-slate-800">Rider's name</label>
        <input id="rider-name" type="text" name="rider_name" required maxlength="120" autocomplete="off" placeholder="e.g. Musa Ibrahim" class="{{ $input }}">

        <label for="rider-phone" class="mb-1.5 mt-4 block text-sm font-semibold text-slate-800">Rider's phone number</label>
        <input id="rider-phone" type="tel" name="rider_phone" required maxlength="30" inputmode="tel" autocomplete="off" placeholder="08012345678" class="{{ $input }}">
    </form>
    <x-slot:footer>
        <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
            <button type="submit" form="handover-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Confirm hand-over</button>
        </div>
    </x-slot:footer>
</x-modal>

@endsection

@push('scripts')
<script>
    (function () {
        // ---- The dropdown chooses which stage is on screen
        const chooser = document.getElementById('lane-select');
        const about = document.getElementById('lane-about');
        const panels = [...document.querySelectorAll('[data-lane-panel]')];

        function show(lane, remember) {
            chooser.value = lane;
            about.textContent = chooser.selectedOptions[0].dataset.about;
            panels.forEach(panel => panel.classList.toggle('hidden', panel.dataset.lanePanel !== lane));
            if (remember) history.replaceState(null, '', '#' + lane);
        }

        chooser.addEventListener('change', () => show(chooser.value, true));

        // A link such as /vendor/dispatch#on_the_way opens straight on that stage.
        const asked = location.hash.slice(1);
        if ([...chooser.options].some(option => option.value === asked)) show(asked, false);

        // ---- "View": the full details of one delivery
        const detailsBody = document.getElementById('details-body');

        function openDetails(id) {
            const details = document.querySelector(`template[data-details="${id}"]`);
            if (!details) return;

            detailsBody.replaceChildren(details.content.cloneNode(true));
            CuraModal.open('details-modal');
        }

        // ---- Handing an order to a rider
        const handoverForm = document.getElementById('handover-form');
        const riderName = document.getElementById('rider-name');
        const riderPhone = document.getElementById('rider-phone');
        const riderChoices = document.getElementById('rider-choices');
        const picks = [...document.querySelectorAll('[data-rider-pick]')];
        const markPick = () => picks.forEach(pick => pick.setAttribute('aria-pressed',
            pick.dataset.name === riderName.value && pick.dataset.phone === riderPhone.value ? 'true' : 'false'));

        function openHandover(button) {
            const known = Boolean(button.dataset.riderName);

            handoverForm.action = button.dataset.handoverUrl;
            document.getElementById('handover-order').textContent = button.dataset.order;
            document.getElementById('handover-known').classList.toggle('hidden', !known);
            riderName.value = button.dataset.riderName || '';
            riderPhone.value = button.dataset.riderPhone || '';
            riderName.readOnly = riderPhone.readOnly = known;
            // The order already has its rider: there is nothing to choose.
            riderChoices?.classList.toggle('hidden', known);
            markPick();

            // The same button also sits inside the details window: close that first.
            if (button.closest('#details-modal')) CuraModal.close('details-modal');
            CuraModal.open('handover-modal');
            if (!known) setTimeout(() => riderName.focus(), 320);
        }

        // One listener for the whole page, so buttons inside the details window work too.
        document.addEventListener('click', event => {
            const handover = event.target.closest('[data-handover-url]');
            if (handover) { openHandover(handover); return; }

            const view = event.target.closest('[data-details-open]');
            if (view) openDetails(view.dataset.detailsOpen);
        });

        // One tap fills in a rider used before.
        picks.forEach(choice => {
            choice.addEventListener('click', () => {
                riderName.value = choice.dataset.name;
                riderPhone.value = choice.dataset.phone;
                markPick();
            });
        });
        [riderName, riderPhone].forEach(field => field.addEventListener('input', markPick));

        // One click, one hand-over.
        handoverForm.addEventListener('submit', () => {
            document.querySelectorAll('button[type="submit"]').forEach(button => {
                if (button.form === handoverForm) { button.disabled = true; button.classList.add('opacity-60', 'cursor-wait'); }
            });
        });
    })();
</script>
@endpush
