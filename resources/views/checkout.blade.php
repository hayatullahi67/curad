@extends('layouts.site')

@php
    $isDelivery = $options['method'] === 'delivery';
    $groups = $plan['groups'];
    $pharmacyCount = count($groups);
    $located = $options['latitude'] !== null && $options['longitude'] !== null;
    $blocked = $plan['unavailable'] || $pharmacyCount === 0;
    $walletEnough = $walletBalance >= $plan['total'];
    $walletUsable = $walletEnough && $hasPin;
    $canPlace = ! $blocked && $deliveryReady;
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $input = 'w-full rounded-xl border border-slate-300 bg-white px-3.5 py-3 text-[15px] text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
@endphp

@section('title', 'Checkout | CuraD')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Checkout</h1>
                <p class="mt-1 text-sm text-slate-600">Nothing is charged until you press “Place order”.</p>
            </div>
            <a href="{{ route('cart') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Back to cart</a>
        </div>

        @if ($errors->any())
            <div class="mt-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_25rem]">
            <div class="space-y-6">
                {{-- 1. Pickup or delivery --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
                    <h2 class="flex items-center gap-3 text-base font-bold text-slate-900">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">1</span>
                        How do you want to get it?
                    </h2>

                    <form action="{{ route('checkout.options') }}" method="POST" id="options-form" class="mt-4">
                        @csrf
                        <input type="hidden" name="latitude" id="latitude" value="{{ $options['latitude'] }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ $options['longitude'] }}">

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ([
                                ['pickup', 'fa-store', 'Pick up at the pharmacy', 'Free. Collect it yourself with a pickup code.'],
                                ['delivery', 'fa-motorcycle', 'Deliver to me', 'A rider brings it to your address. Delivery fee applies.'],
                            ] as [$value, $icon, $label, $hint])
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="method" value="{{ $value }}" class="peer sr-only" data-auto-submit @checked($options['method'] === $value)>
                                    <span class="flex h-full gap-3 rounded-xl border border-slate-300 bg-white p-4 transition hover:border-slate-400 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:ring-1 peer-checked:ring-emerald-600 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100">
                                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 ring-1 ring-slate-200"><i class="fas {{ $icon }}"></i></span>
                                        <span class="min-w-0 pr-5">
                                            <span class="block text-sm font-semibold text-slate-900">{{ $label }}</span>
                                            <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $hint }}</span>
                                        </span>
                                    </span>
                                    <i class="fas fa-circle-check absolute right-3.5 top-3.5 hidden text-emerald-600 peer-checked:block"></i>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            @if ($isDelivery)
                                <div class="sm:col-span-2">
                                    <label for="address" class="mb-1.5 block text-sm font-semibold text-slate-800">Delivery address</label>
                                    <textarea id="address" name="address" rows="2" required maxlength="500" placeholder="Start typing your street or area, then pick it from the list" data-address-suggest data-latitude="#latitude" data-longitude="#longitude" class="{{ $input }}">{{ $options['address'] }}</textarea>
                                    <p class="mt-1.5 text-xs leading-5 text-slate-500">Pick your address from the list and the delivery fee is worked out straight away. Not in the list? Press “Use my location” below to fill it in from where you are, or type it in full.</p>
                                </div>
                            @else
                                <input type="hidden" name="address" value="{{ $options['address'] }}">
                            @endif

                            <div>
                                <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-800">Phone number</label>
                                <input id="phone" type="tel" name="phone" value="{{ $options['phone'] }}" inputmode="tel" autocomplete="tel" maxlength="30" placeholder="08012345678" class="{{ $input }}" @if ($isDelivery) required @endif>
                                <p class="mt-1.5 text-xs text-slate-500">{{ $isDelivery ? 'The rider calls this number.' : 'The pharmacy calls this number if there is a question.' }}</p>
                            </div>

                            <div>
                                <label for="note" class="mb-1.5 block text-sm font-semibold text-slate-800">Note <span class="font-normal text-slate-400">(optional)</span></label>
                                <input id="note" type="text" name="note" value="{{ $options['note'] }}" maxlength="500" placeholder="{{ $isDelivery ? 'Landmark, gate colour, floor…' : 'Anything the pharmacy should know' }}" class="{{ $input }}">
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 rounded-xl bg-slate-50 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                            <p class="flex items-start gap-2.5 text-xs leading-5 text-slate-600">
                                <i class="fas {{ $located ? 'fa-location-crosshairs text-emerald-600' : 'fa-location-dot text-slate-400' }} mt-0.5"></i>
                                <span id="location-text">
                                    @if ($located)
                                        <span class="font-semibold text-slate-900">Your location is pinned.</span> {{ $isDelivery ? 'The delivery fee uses the real distance and driving time.' : 'The nearest pharmacies are preferred.' }}
                                    @else
                                        {{ $isDelivery ? 'Use your location to fill in your address and get an exact delivery fee. Without it a standard fee is used.' : 'Pin your location and CuraD prefers the pharmacies nearest to you.' }}
                                    @endif
                                </span>
                            </p>
                            <div class="flex flex-shrink-0 gap-2">
                                @if ($located)
                                    <button type="button" id="clear-location" class="h-10 rounded-lg px-3 text-xs font-semibold text-slate-500 transition-colors hover:bg-slate-200 hover:text-slate-900">Remove</button>
                                @endif
                                <button type="button" id="use-location" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3.5 text-xs font-semibold text-slate-700 transition-colors hover:border-emerald-400 hover:text-emerald-700">
                                    <i class="fas fa-location-crosshairs"></i> {{ $located ? 'Update my location' : 'Use my location' }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-end">
                            <button type="submit" class="h-11 rounded-xl border border-emerald-600 px-5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50">Save these details</button>
                        </div>
                    </form>
                </section>

                {{-- 2. Who supplies what --}}
                <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
                    <h2 class="flex items-center gap-3 text-base font-bold text-slate-900">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">2</span>
                        {{ $isDelivery ? 'Where the rider collects from' : 'Where you collect from' }}
                    </h2>

                    @if ($plan['unavailable'])
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                            <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1.5 text-amber-600"></i>Not available right now</p>
                            <ul class="mt-2 list-inside list-disc space-y-0.5">
                                @foreach ($plan['unavailable'] as $key => $quantity)
                                    <li>{{ $names[$key] ?? ucfirst($key) }} × {{ $quantity }}</li>
                                @endforeach
                            </ul>
                            <p class="mt-2">You are not charged for these. <a href="{{ route('cart') }}" class="font-semibold underline">Change your cart</a> to continue.</p>
                        </div>
                    @endif

                    @if ($pharmacyCount > 1)
                        <div class="mt-4 flex gap-3 rounded-xl border p-4 text-sm {{ $isDelivery ? 'border-sky-200 bg-sky-50 text-sky-900' : 'border-amber-200 bg-amber-50 text-amber-900' }}">
                            <i class="fas {{ $isDelivery ? 'fa-route text-sky-600' : 'fa-triangle-exclamation text-amber-600' }} mt-0.5"></i>
                            <p>
                                @if ($isDelivery)
                                    No single pharmacy has everything, so one rider collects from <span class="font-semibold">{{ $pharmacyCount }} pharmacies</span> in the order below and brings it all to you in one trip.
                                @else
                                    No single pharmacy has everything. You will need to collect from <span class="font-semibold">{{ $pharmacyCount }} pharmacies</span>, each with its own pickup code. Choose delivery if you would rather have it all brought to you.
                                @endif
                            </p>
                        </div>
                    @endif

                    <ol class="mt-4 space-y-4">
                        @foreach ($groups as $index => $group)
                            @php
                                $pharmacy = $group['pharmacy'];
                                $place = collect([$pharmacy->address, $pharmacy->city, $pharmacy->state])->filter()->unique()->implode(', ');
                                $mapQuery = $pharmacy->latitude && $pharmacy->longitude ? $pharmacy->latitude . ',' . $pharmacy->longitude : $pharmacy->name . ' ' . $place;
                            @endphp
                            <li class="overflow-hidden rounded-xl border border-slate-200">
                                <div class="flex items-start gap-3 bg-slate-50 p-4">
                                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-white text-sm font-bold text-emerald-700 ring-1 ring-slate-200">{{ $pharmacyCount > 1 ? $index + 1 : '' }}@if ($pharmacyCount === 1)<i class="fas fa-store"></i>@endif</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-slate-900">
                                            <span class="break-words">{{ $pharmacy->displayName() }}</span>
                                        </p>
                                        @if ($place)
                                            <p class="mt-0.5 text-sm leading-5 text-slate-600">{{ $place }}</p>
                                        @endif
                                        <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold">
                                            @isset($group['distance_km'])
                                                <span class="text-slate-500"><i class="fas fa-person-walking mr-1"></i>{{ ($group['distance_by_road'] ?? false) ? $group['distance_km'] . ' km by road from you' : 'About ' . $group['distance_km'] . ' km from you' }}</span>
                                            @endisset
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($mapQuery) }}" target="_blank" rel="noopener" class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-map-location-dot mr-1"></i>Open in maps</a>
                                        </p>
                                    </div>
                                </div>

                                <ul class="divide-y divide-slate-100 px-4">
                                    @foreach ($group['lines'] as $line)
                                        <li class="flex items-center gap-3 py-3">
                                            <span class="relative h-11 w-11 flex-shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                                <span class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="fas fa-pills text-sm"></i></span>
                                                @if ($line['offer']->photo_url)
                                                    <img src="{{ $line['offer']->photo_url }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                                                @endif
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-slate-900">{{ $line['offer']->name }}</p>
                                                <p class="text-xs text-slate-500">{{ $line['quantity'] }} × {{ $money($line['unit_price']) }}@if ($line['offer']->is_rx) · <span class="font-semibold text-slate-700">Prescription needed</span>@endif</p>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-900">{{ $money($line['line_total']) }}</p>
                                        </li>
                                    @endforeach
                                </ul>

                                @if ($pharmacyCount > 1)
                                    <p class="flex items-center justify-between border-t border-slate-100 bg-white px-4 py-2.5 text-xs text-slate-500">
                                        <span>From this pharmacy</span>
                                        <span class="font-semibold text-slate-900">{{ $money($group['subtotal']) }}</span>
                                    </p>
                                @endif
                            </li>
                        @endforeach

                        @if ($isDelivery && $pharmacyCount > 0)
                            <li class="flex items-start gap-3 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/60 p-4">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white"><i class="fas fa-house text-sm"></i></span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">Delivered to you</p>
                                    <p class="mt-0.5 break-words text-sm text-slate-600">{{ $options['address'] ?: 'Add your delivery address above.' }}</p>
                                    @if ($plan['delivery']['distance_km'])
                                        <p class="mt-1 text-xs font-semibold text-slate-500"><i class="fas fa-route mr-1"></i>{{ ($plan['delivery']['by_road'] ?? false) ? $plan['delivery']['distance_km'] . ' km by road, about ' . $plan['delivery']['minutes'] . ' min for the rider' : 'About ' . $plan['delivery']['distance_km'] . ' km, ' . $plan['delivery']['minutes'] . ' min for the rider' }}</p>
                                    @endif
                                </div>
                            </li>
                        @endif
                    </ol>
                </section>
            </div>

            {{-- 3. Pay --}}
            <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
                <h2 class="flex items-center gap-3 text-base font-bold text-slate-900">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-bold text-white">3</span>
                    Review and pay
                </h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-600">Products</dt>
                        <dd class="font-semibold text-slate-900">{{ $money($plan['subtotal']) }}</dd>
                    </div>
                    @if ($plan['service_fee'] > 0)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Service fee</dt>
                            <dd class="font-semibold text-slate-900">{{ $money($plan['service_fee']) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-start justify-between gap-3">
                        <dt class="text-slate-600">
                            Delivery
                            @if ($isDelivery && $plan['delivery'] && ! $plan['delivery']['exact'])
                                <span class="block text-xs text-slate-400">Standard fee (no location pinned)</span>
                            @elseif ($isDelivery && $plan['delivery'])
                                <span class="block text-xs text-slate-400">{{ $plan['delivery']['distance_km'] }} km · about {{ $plan['delivery']['minutes'] }} min</span>
                                @if ($plan['delivery']['multiplier'] > 1)
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-md bg-amber-50 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800" title="Deliveries cost more at busy times">
                                        <i class="fas fa-bolt text-[9px]"></i>{{ $plan['delivery']['period'] === 'very_busy' ? 'Very busy time' : 'Busy time' }} · ×{{ rtrim(rtrim(number_format($plan['delivery']['multiplier'], 2), '0'), '.') }}
                                    </span>
                                @endif
                            @endif
                        </dt>
                        <dd class="font-semibold {{ $isDelivery ? 'text-slate-900' : 'text-emerald-700' }}">{{ $isDelivery ? $money($plan['delivery_fee']) : 'Free pickup' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                    <p class="text-sm font-semibold text-slate-900">Total to pay</p>
                    <p class="text-2xl font-extrabold text-slate-900">{{ $money($plan['total']) }}</p>
                </div>

                <form action="{{ route('checkout.place') }}" method="POST" id="place-form" class="mt-5">
                    @csrf
                    <input type="hidden" name="signature" value="{{ $plan['signature'] }}">

                    <fieldset>
                        <legend class="text-sm font-semibold text-slate-800">How will you pay?</legend>

                        <div class="mt-2.5 space-y-2.5">
                            <label class="relative block cursor-pointer">
                                <input type="radio" name="payment_method" value="pay_on_collection" class="peer sr-only" checked>
                                <span class="flex gap-3 rounded-xl border border-slate-300 p-3.5 transition hover:border-slate-400 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:ring-1 peer-checked:ring-emerald-600 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100">
                                    <i class="fas fa-hand-holding-dollar mt-0.5 w-5 text-center text-emerald-600"></i>
                                    <span class="min-w-0 pr-5">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $isDelivery ? 'Pay on delivery' : 'Pay at the pharmacy' }}</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $isDelivery ? 'Pay the rider when your order arrives.' : ($pharmacyCount > 1 ? 'Pay each pharmacy for its items when you collect.' : 'Pay when you collect your order.') }}</span>
                                    </span>
                                </span>
                                <i class="fas fa-circle-check absolute right-3.5 top-3.5 hidden text-emerald-600 peer-checked:block"></i>
                            </label>

                            <label class="relative block {{ $walletUsable ? 'cursor-pointer' : 'cursor-not-allowed' }}">
                                <input type="radio" name="payment_method" value="wallet" class="peer sr-only" @disabled(! $walletUsable)>
                                <span class="flex gap-3 rounded-xl border p-3.5 transition {{ $walletUsable ? 'border-slate-300 hover:border-slate-400' : 'border-slate-200 bg-slate-50' }} peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:ring-1 peer-checked:ring-emerald-600 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100">
                                    <i class="fas fa-wallet mt-0.5 w-5 text-center {{ $walletUsable ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                                    <span class="min-w-0 pr-5">
                                        <span class="block text-sm font-semibold {{ $walletUsable ? 'text-slate-900' : 'text-slate-500' }}">CuraD wallet</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-slate-500">
                                            Balance {{ $money($walletBalance) }}.
                                            @if (! $walletEnough)
                                                Not enough for this order.
                                            @elseif (! $hasPin)
                                                <a href="{{ route('user.profile') }}" class="font-semibold text-emerald-700 underline">Set a wallet PIN</a> to pay this way.
                                            @else
                                                Paid now, straight to the pharmacy.
                                            @endif
                                        </span>
                                    </span>
                                </span>
                                <i class="fas fa-circle-check absolute right-3.5 top-3.5 hidden text-emerald-600 peer-checked:block"></i>
                            </label>
                        </div>

                        <div id="pin-field" class="mt-3 hidden">
                            <label for="pin" class="mb-1.5 block text-sm font-semibold text-slate-800">Wallet PIN</label>
                            <input id="pin" type="password" name="pin" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••" class="{{ $input }} text-center tracking-[0.5em]">
                        </div>
                    </fieldset>

                    @if ($canPlace)
                        <button type="submit" id="place-button" class="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                            Place order · {{ $money($plan['total']) }}
                        </button>
                    @else
                        <span class="mt-5 flex h-12 w-full cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-3 text-center text-sm font-semibold text-slate-500">
                            {{ $blocked ? 'Fix the cart to continue' : 'Save your delivery address and phone first' }}
                        </span>
                    @endif
                </form>

                <ul class="mt-4 space-y-2 text-xs leading-5 text-slate-500">
                    <li class="flex gap-2"><i class="fas fa-box mt-1 text-slate-400"></i><span>Your items are held for you as soon as you place the order.</span></li>
                    <li class="flex gap-2"><i class="fas fa-rotate-left mt-1 text-slate-400"></i><span>You can cancel for free until the pharmacy accepts it.</span></li>
                </ul>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.address-suggest-script')
    <script>
        (function () {
            const optionsForm = document.getElementById('options-form');

            // Choosing an address from the list saves it at once, so the pharmacies and the delivery fee update.
            document.getElementById('address')?.addEventListener('address:picked', () => {
                optionsForm.noValidate = true;
                optionsForm.submit();
            });

            // Switching between pickup and delivery reloads the plan and total at once.
            optionsForm.querySelectorAll('[data-auto-submit]').forEach(radio => {
                radio.addEventListener('change', () => {
                    optionsForm.noValidate = true;
                    optionsForm.submit();
                });
            });

            const useLocation = document.getElementById('use-location');
            const locationText = document.getElementById('location-text');

            useLocation.addEventListener('click', () => {
                if (!navigator.geolocation) {
                    locationText.textContent = 'This browser cannot share a location. A standard delivery fee is used instead.';
                    return;
                }

                useLocation.disabled = true;
                useLocation.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Finding you…';

                navigator.geolocation.getCurrentPosition(position => {
                    const latitude = position.coords.latitude.toFixed(6);
                    const longitude = position.coords.longitude.toFixed(6);
                    const save = () => { optionsForm.noValidate = true; optionsForm.submit(); };

                    document.getElementById('latitude').value = latitude;
                    document.getElementById('longitude').value = longitude;
                    useLocation.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Finding your address…';

                    // The spot is turned into a street address and put in the address box,
                    // where it can still be corrected. If no address is found, the pin is kept anyway.
                    fetch(`{{ route('location.lookup') }}?lat=${latitude}&lng=${longitude}`, { headers: { Accept: 'application/json' } })
                        .then(response => response.ok ? response.json() : null)
                        .then(found => {
                            const address = found ? [found.address, found.city, found.state].filter((part, index, parts) => part && parts.indexOf(part) === index).join(', ') : '';
                            if (address) optionsForm.elements.address.value = address;
                        })
                        .catch(() => {})
                        .finally(save);
                }, () => {
                    useLocation.disabled = false;
                    useLocation.innerHTML = '<i class="fas fa-location-crosshairs"></i> Try again';
                    locationText.textContent = 'We could not read your location. Allow location access in your browser and try again, or carry on without it.';
                }, { enableHighAccuracy: true, timeout: 12000 });
            });

            const clearLocation = document.getElementById('clear-location');
            if (clearLocation) {
                clearLocation.addEventListener('click', () => {
                    document.getElementById('latitude').value = '';
                    document.getElementById('longitude').value = '';
                    optionsForm.noValidate = true;
                    optionsForm.submit();
                });
            }

            // The PIN box only shows for wallet payments.
            const placeForm = document.getElementById('place-form');
            const pinField = document.getElementById('pin-field');
            const pin = document.getElementById('pin');

            function showPin() {
                const wallet = placeForm.querySelector('[name="payment_method"]:checked')?.value === 'wallet';
                pinField.classList.toggle('hidden', !wallet);
                pin.required = wallet;
            }

            placeForm.querySelectorAll('[name="payment_method"]').forEach(radio => radio.addEventListener('change', showPin));
            showPin();

            // One click places one order.
            placeForm.addEventListener('submit', () => {
                const button = document.getElementById('place-button');
                if (!button) return;
                button.disabled = true;
                button.classList.add('cursor-wait', 'opacity-70');
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Placing your order…';
            });
        })();
    </script>
@endpush
