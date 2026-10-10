{{-- One product and every pharmacy selling it. Shown on the public website and inside the customer's dashboard. --}}
@extends($inDashboard ? 'layouts.app' : 'layouts.site')

@php
    $inStockOffers = $offers->filter(fn ($offer) => $offer->availableStock() > 0);
    $lowestPrice = (float) ($inStockOffers->min('price') ?? $offers->min('price'));
    $pharmacyCount = $offers->pluck('pharmacy_id')->unique()->count();
    $needsPrescription = (bool) $offers->max('is_rx');
    $description = $offers->pluck('description')->filter()->first();
    $mostAvailable = (int) $offers->map->availableStock()->max();
    $maxQuantity = min((int) config('curad.marketplace.max_quantity'), max(1, $mostAvailable));
    // "Best price" only means something when the in-stock pharmacies charge different prices.
    $cheapestId = $inStockOffers->min('price') < $inStockOffers->max('price') ? $inStockOffers->sortBy('price')->first()->id : null;
    $nearestId = $here ? $inStockOffers->whereNotNull('distance_km')->sortBy('distance_km')->first()?->id : null;
    // "by road" is a real driving distance; "about" is an estimate used when the routing service could not be reached.
    $distance = fn (?float $km, bool $byRoad) => $km === null ? null : (
        $km < 1 ? 'Under 1 km away' : ($byRoad ? '' : 'about ') . number_format($km, $km < 10 ? 1 : 0) . ' km ' . ($byRoad ? 'by road' : 'away')
    );
@endphp

@section('title', $inDashboard ? $product->name : $product->name . ' — price and pharmacies | CuraD')
@section('header_title', 'Marketplace')
@section('description', "Compare prices for {$product->name} across CuraD pharmacies and see who has it in stock.")

@section('content')
    <div class="{{ $inDashboard ? 'mx-auto -mt-3 max-w-[1280px]' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8' }}">
        <nav class="flex items-center gap-2 overflow-hidden text-sm text-slate-500" aria-label="Breadcrumb">
            <a href="{{ route($marketRoute) }}" class="flex-shrink-0 hover:text-emerald-700">Marketplace</a>
            @if ($product->category)
                <i class="fas fa-chevron-right flex-shrink-0 text-[10px] text-slate-300"></i>
                <a href="{{ route($marketRoute, ['category' => $product->category]) }}" class="flex-shrink-0 hover:text-emerald-700">{{ $product->category }}</a>
            @endif
            <i class="fas fa-chevron-right flex-shrink-0 text-[10px] text-slate-300"></i>
            <span class="truncate text-slate-900">{{ $product->name }}</span>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-12">
            {{-- Photo --}}
            <div class="{{ $inDashboard ? '' : 'lg:sticky lg:top-24' }} lg:self-start">
                <div class="relative aspect-square overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 sm:aspect-[4/3] lg:aspect-square">
                    <div class="absolute inset-0 flex items-center justify-center text-slate-300">
                        <i class="fas {{ \App\Models\Medication::categoryIcon($product->category) }} text-7xl"></i>
                    </div>
                    @php $photo = $product->photo_url ?? $offers->map->photo_url->filter()->first(); @endphp
                    @if ($photo)
                        <img src="{{ $photo }}" alt="{{ $product->name }}" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                    @endif
                </div>
            </div>

            {{-- Details, buy box and offers --}}
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($product->category)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ $product->category }}</span>
                    @endif
                    @if ($needsPrescription)
                        <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white"><i class="fas fa-file-prescription mr-1"></i>Prescription needed</span>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-extrabold leading-tight tracking-tight text-slate-900 sm:text-3xl">{{ $product->name }}</h1>
                @if ($product->dosage)
                    <p class="mt-1.5 text-slate-600">{{ $product->dosage }}</p>
                @endif

                <div class="mt-5 flex flex-wrap items-end gap-x-4 gap-y-2">
                    <p class="text-3xl font-extrabold text-slate-900">
                        @if ($offers->count() > 1 && $offers->max('price') > $lowestPrice)<span class="text-base font-medium text-slate-500">from </span>@endif&#8358;{{ number_format($lowestPrice, 2) }}
                    </p>
                    @if ($inStockOffers->isNotEmpty())
                        <p class="mb-1 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>In stock at {{ $inStockOffers->pluck('pharmacy_id')->unique()->count() }} of {{ $pharmacyCount }} {{ Str::plural('pharmacy', $pharmacyCount) }}</p>
                    @else
                        <p class="mb-1 inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500"><span class="h-2 w-2 rounded-full bg-slate-400"></span>Out of stock everywhere right now</p>
                    @endif
                </div>

                @if ($description)
                    <p class="mt-5 max-w-2xl text-[15px] leading-7 text-slate-600">{{ $description }}</p>
                @endif

                @if ($needsPrescription)
                    <div class="mt-5 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                        <i class="fas fa-circle-info mt-0.5 text-amber-600"></i>
                        <p>This is a prescription medicine. The pharmacy will ask to see a valid prescription before handing it over.</p>
                    </div>
                @endif

                {{-- Buy box --}}
                @if ($inStockOffers->isNotEmpty())
                    <form action="{{ route('cart.add') }}" method="POST" class="mt-6 rounded-2xl border border-slate-200 {{ $inDashboard ? 'bg-white shadow-sm' : 'bg-slate-50' }} p-4 sm:p-5">
                        @csrf
                        <input type="hidden" name="medication_id" value="{{ $inStockOffers->first()->id }}">

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div>
                                <label for="quantity" class="mb-1.5 block text-sm font-semibold text-slate-800">Quantity</label>
                                <div class="inline-flex h-12 items-center rounded-xl border border-slate-300 bg-white" data-stepper>
                                    <button type="button" data-step="-1" aria-label="One less" class="flex h-full w-11 items-center justify-center rounded-l-xl text-slate-600 transition-colors hover:bg-slate-100"><i class="fas fa-minus text-xs"></i></button>
                                    <input id="quantity" type="number" name="quantity" value="1" min="1" max="{{ $maxQuantity }}" inputmode="numeric" class="h-full w-12 border-0 bg-transparent p-0 text-center text-[15px] font-semibold text-slate-900 [appearance:textfield] focus:outline-none focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                    <button type="button" data-step="1" aria-label="One more" class="flex h-full w-11 items-center justify-center rounded-r-xl text-slate-600 transition-colors hover:bg-slate-100"><i class="fas fa-plus text-xs"></i></button>
                                </div>
                            </div>

                            <div class="grid flex-1 grid-cols-2 gap-3">
                                <button type="submit" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-emerald-600 bg-white px-4 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50">
                                    <i class="fas fa-bag-shopping text-xs"></i> Add to cart
                                </button>
                                <button type="submit" name="buy_now" value="1" class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                                    Buy now <i class="fas fa-arrow-right text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-xs leading-5 text-slate-500"><i class="fas fa-circle-info mr-1"></i>At checkout CuraD picks the pharmacy for you, keeping your whole order in as few places as possible. You see every pharmacy and the full total before you pay.</p>
                    </form>
                @endif

                {{-- Keep an eye on it --}}
                @auth
                    <form action="{{ route('user.watchlist.toggle') }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="medication_id" value="{{ $watching?->medication_id ?? $product->id }}">
                        <input type="hidden" name="notify_on_restock" value="1">
                        <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold {{ $watching ? 'text-emerald-700 hover:text-emerald-800' : 'text-slate-600 hover:text-slate-900' }}">
                            <i class="{{ $watching ? 'fas' : 'far' }} fa-bookmark"></i>{{ $watching ? 'On your watchlist. Remove it' : 'Add to my watchlist' }}
                        </button>
                    </form>
                @endauth

                <div class="mt-8 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Pharmacies that have it</h2>
                        <p class="mt-1 text-sm text-slate-600">In-stock pharmacies first, {{ $here ? 'closest to you at the top' : 'cheapest at the top' }}.</p>
                    </div>
                    @unless ($here)
                        <a href="{{ route($marketRoute) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-location-crosshairs mr-1 text-xs"></i>Turn on “Near me” to see distances</a>
                    @endunless
                </div>

                <ul class="mt-4 space-y-3">
                    @foreach ($offers as $offer)
                        @php
                            $pharmacy = $offer->pharmacy;
                            $stock = $offer->availableStock();
                            $isCheapest = $offer->id === $cheapestId;
                            $isNearest = $offer->id === $nearestId;
                            $place = collect([$pharmacy->address, $pharmacy->city, $pharmacy->state])->filter()->unique()->implode(', ');
                            $hasPin = filled($pharmacy->latitude) && filled($pharmacy->longitude);
                            $mapTarget = $hasPin ? $pharmacy->latitude . ',' . $pharmacy->longitude : ($place ? urlencode($pharmacy->name . ', ' . $place) : null);
                            $isSaved = in_array($pharmacy->id, $savedPharmacyIds, true);
                        @endphp
                        <li class="rounded-2xl border bg-white p-4 sm:p-5 {{ $loop->first && $stock > 0 && $offers->count() > 1 ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-slate-200' }}">
                            <div class="flex items-start gap-3.5">
                                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500"><i class="fas fa-store"></i></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                                        <p class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 font-semibold text-slate-900">
                                            <span class="break-words">{{ $pharmacy->displayName() }}</span>
                                            @if ($isNearest)
                                                <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-semibold text-white">Nearest to you</span>
                                            @endif
                                            @if ($isCheapest)
                                                <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[11px] font-semibold text-white">Best price</span>
                                            @endif
                                        </p>
                                        <p class="text-lg font-extrabold text-slate-900">&#8358;{{ number_format((float) $offer->price, 2) }}</p>
                                    </div>
                                    @if ($place)
                                        <p class="mt-1 text-sm leading-5 text-slate-600"><i class="fas fa-location-dot mr-1.5 text-xs text-slate-400"></i>{{ $place }}@if ($offer->distance_km !== null)<span class="font-semibold text-slate-900"> · {{ $distance($offer->distance_km, (bool) $offer->distance_by_road) }}</span>@endif</p>
                                    @endif
                                    <p class="mt-1.5 text-sm font-medium">
                                        @if ($stock > 5)
                                            <span class="text-emerald-700">In stock</span>
                                        @elseif ($stock > 0)
                                            <span class="text-amber-700">Only {{ $stock }} left</span>
                                        @else
                                            <span class="text-slate-500">Out of stock</span>
                                        @endif
                                        @if ($pharmacy->operating_hours)
                                            <span class="font-normal text-slate-400"> · </span><span class="font-normal text-slate-600">{{ $pharmacy->operating_hours }}</span>
                                        @endif
                                    </p>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-semibold">
                                        @if ($mapTarget)
                                            <a href="https://www.google.com/maps/search/?api=1&query={{ $mapTarget }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-800"><i class="fas fa-map-location-dot text-xs"></i>View on Google Maps</a>
                                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $mapTarget }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-800"><i class="fas fa-diamond-turn-right text-xs"></i>Get directions</a>
                                        @endif
                                        @auth
                                            <form action="{{ route('user.pharmacies.toggle', $pharmacy) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 {{ $isSaved ? 'text-emerald-700 hover:text-emerald-800' : 'text-slate-500 hover:text-slate-900' }}">
                                                    <i class="{{ $isSaved ? 'fas' : 'far' }} fa-heart text-xs"></i>{{ $isSaved ? 'Saved' : 'Save pharmacy' }}
                                                </button>
                                            </form>
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <section class="mt-14 border-t border-slate-200 pt-10">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">More in {{ $product->category }}</h2>
                    <a href="{{ route($marketRoute, ['category' => $product->category]) }}" class="flex-shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-800">See all <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 {{ $inDashboard ? 'xl:grid-cols-4' : 'lg:grid-cols-4' }}">
                    @foreach ($related as $item)
                        @include('partials.product-card', ['product' => $item])
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        // Plus and minus buttons beside a quantity box.
        document.querySelectorAll('[data-stepper]').forEach(stepper => {
            const input = stepper.querySelector('input');

            stepper.querySelectorAll('[data-step]').forEach(button => {
                button.addEventListener('click', () => {
                    const min = Number(input.min) || 1;
                    const max = Number(input.max) || 99;
                    input.value = Math.min(max, Math.max(min, (Number(input.value) || min) + Number(button.dataset.step)));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });
        });
    </script>
@endpush
