{{-- The marketplace catalogue. Shown on the public website and, with the same contents, inside the customer's dashboard. --}}
@extends($inDashboard ? 'layouts.app' : 'layouts.site')

@php
    $filtersOn = $search !== '' || $category !== '' || $inStockOnly || $within;
    // Builds a marketplace link that keeps the current filters, changing only what is passed in.
    $link = fn (array $change) => route($marketRoute, array_filter(
        array_merge(['q' => $search, 'category' => $category, 'sort' => $sort === 'newest' ? null : $sort, 'in_stock' => $inStockOnly ? 1 : null, 'within' => $within], $change),
        fn ($value) => $value !== null && $value !== ''
    ));
    $chip = 'flex-shrink-0 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors';
    $chipOn = 'border-emerald-600 bg-emerald-600 text-white';
    $chipOff = 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:text-emerald-700';
@endphp

@section('title', $inDashboard ? 'Marketplace' : (($search !== '' ? "“{$search}” — " : ($category !== '' ? "{$category} — " : '')) . 'Marketplace | CuraD'))
@section('header_title', 'Marketplace')

@section('content')
@if ($inDashboard)<div class="mx-auto -mt-3 max-w-[1280px]">@endif

    <section class="{{ $inDashboard ? 'rounded-2xl border border-slate-200 bg-white shadow-sm' : 'border-b border-slate-200 bg-gradient-to-b from-emerald-50 to-white' }}">
        <div class="{{ $inDashboard ? 'p-4 sm:p-6' : 'mx-auto max-w-7xl px-4 pb-6 pt-8 sm:px-6 sm:pt-10 lg:px-8' }}">
            @unless ($inDashboard)
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Marketplace</h1>
                <p class="mb-5 mt-1.5 text-sm text-slate-600 sm:text-base">Medicines and health products from CuraD pharmacies. No account needed to browse.</p>
            @endunless

            <form action="{{ route($marketRoute) }}" method="GET" role="search" class="flex flex-col gap-2.5 sm:flex-row">
                @if ($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
                @if ($inStockOnly)<input type="hidden" name="in_stock" value="1">@endif
                @if ($within)<input type="hidden" name="within" value="{{ $within }}">@endif

                <div class="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-slate-300 bg-white px-3.5 shadow-sm focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
                    <label for="market-search" class="sr-only">Search products</label>
                    <i class="fas fa-magnifying-glass text-slate-400"></i>
                    <input id="market-search" type="search" name="q" value="{{ $search }}" autocomplete="off" placeholder="Search by product name, strength or category" class="min-w-0 flex-1 border-0 bg-transparent py-3 text-[15px] placeholder-slate-400 focus:outline-none focus:ring-0">
                </div>

                <div class="flex gap-2.5">
                    <label for="market-sort" class="sr-only">Sort by</label>
                    <select id="market-sort" name="sort" onchange="this.form.submit()" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm font-medium text-slate-700 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100 sm:flex-none">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="flex-shrink-0 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Search</button>
                </div>
            </form>

            {{-- ============ Near me ============ --}}
            <div class="mt-4 rounded-xl border {{ $here ? 'border-emerald-200 bg-emerald-50/60' : 'border-slate-200 bg-slate-50' }} p-3"
                 data-near-me data-url="{{ $link(['lat' => 'LATITUDE', 'lng' => 'LONGITUDE', 'sort' => 'nearest']) }}">
                @if ($here)
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        <p class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800"><i class="fas fa-location-crosshairs"></i>Showing road distance from where you are</p>
                        <div class="ml-auto flex items-center gap-3 text-sm font-semibold">
                            <button type="button" data-near-button class="text-emerald-700 hover:text-emerald-800"><i class="fas fa-rotate mr-1 text-xs"></i>Update my location</button>
                            <a href="{{ $link(['near' => 'off']) }}" class="text-slate-500 hover:text-slate-900">Turn off</a>
                        </div>
                    </div>
                    <div class="no-scrollbar -mx-3 mt-2.5 flex gap-2 overflow-x-auto px-3 pb-0.5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="group" aria-label="How far to look">
                        <a href="{{ $link(['within' => null]) }}" class="{{ $chip }} {{ $within ? $chipOff : $chipOn }}">Any distance</a>
                        @foreach ($distances as $distance)
                            <a href="{{ $link(['within' => $distance]) }}" class="{{ $chip }} {{ $within === $distance ? $chipOn : $chipOff }}">Within {{ $distance }} km</a>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <p class="flex min-w-0 flex-1 items-start gap-2.5 text-sm leading-5 text-slate-600">
                            <i class="fas fa-location-dot mt-0.5 text-emerald-600"></i>
                            <span><span class="font-semibold text-slate-900">Find it close to you.</span> Share your location to see the nearest pharmacies first and how far each one is by road.</span>
                        </p>
                        <button type="button" data-near-button class="inline-flex h-10 flex-shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">
                            <i class="fas fa-location-crosshairs text-xs"></i> Near me
                        </button>
                    </div>
                @endif
                <p data-near-status class="mt-2 hidden text-sm text-rose-600" role="alert"></p>
            </div>

            @if ($categories->isNotEmpty())
                <div class="no-scrollbar -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
                    <a href="{{ $link(['category' => null]) }}" class="{{ $chip }} {{ $category === '' ? $chipOn : $chipOff }}">All</a>
                    @foreach ($categories as $item)
                        <a href="{{ $link(['category' => $item->name]) }}" class="flex items-center gap-1.5 {{ $chip }} {{ $category === $item->name ? $chipOn : $chipOff }}">
                            <i class="fas {{ $item->icon }} text-xs opacity-70"></i>{{ $item->name }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="{{ $inDashboard ? 'mt-5' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8' }}">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600">
                <span class="font-semibold text-slate-900">{{ number_format($products->total()) }}</span>
                {{ Str::plural('product', $products->total()) }}
                @if ($search !== '') for <span class="font-semibold text-slate-900">“{{ $search }}”</span>@endif
                @if ($category !== '') in <span class="font-semibold text-slate-900">{{ $category }}</span>@endif
                @if ($within) within <span class="font-semibold text-slate-900">{{ $within }} km</span> of you @endif
            </p>

            <div class="flex items-center gap-3">
                @if ($filtersOn)
                    <a href="{{ route($marketRoute) }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900"><i class="fas fa-xmark mr-1 text-xs"></i>Clear filters</a>
                @endif
                <a href="{{ $link(['in_stock' => $inStockOnly ? null : 1]) }}" role="switch" aria-checked="{{ $inStockOnly ? 'true' : 'false' }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                    <span class="relative inline-flex h-5 w-9 flex-shrink-0 rounded-full transition-colors {{ $inStockOnly ? 'bg-emerald-600' : 'bg-slate-300' }}">
                        <span class="absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all {{ $inStockOnly ? 'left-[18px]' : 'left-0.5' }}"></span>
                    </span>
                    In stock only
                </a>
            </div>
        </div>

        @if ($products->isNotEmpty())
            <div class="mt-5 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 {{ $inDashboard ? 'xl:grid-cols-4' : 'lg:grid-cols-4' }}">
                @foreach ($products as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>

            @if ($products->hasPages())
                <nav class="mt-10 flex items-center justify-between gap-3 border-t border-slate-200 pt-6" aria-label="Pages">
                    @if ($products->onFirstPage())
                        <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Previous</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" rel="prev" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:border-emerald-300 hover:text-emerald-700"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Previous</a>
                    @endif

                    <p class="text-sm text-slate-600">Page <span class="font-semibold text-slate-900">{{ $products->currentPage() }}</span> of {{ $products->lastPage() }}</p>

                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" rel="next" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:border-emerald-300 hover:text-emerald-700">Next<i class="fas fa-arrow-right ml-1.5 text-xs"></i></a>
                    @else
                        <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Next<i class="fas fa-arrow-right ml-1.5 text-xs"></i></span>
                    @endif
                </nav>
            @endif
        @else
            <div class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-xl text-slate-400 shadow-sm ring-1 ring-slate-200"><i class="fas {{ $filtersOn ? 'fa-magnifying-glass' : 'fa-box-open' }}"></i></span>
                @if ($within)
                    <h2 class="mt-5 text-lg font-semibold text-slate-900">Nothing within {{ $within }} km of you</h2>
                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Try a wider distance. Delivery can bring an order from a pharmacy further away.</p>
                    <a href="{{ $link(['within' => null]) }}" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">Look at any distance</a>
                @elseif ($filtersOn)
                    <h2 class="mt-5 text-lg font-semibold text-slate-900">Nothing matched your search</h2>
                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Check the spelling, try the generic name of the medicine, or remove a filter.</p>
                    <a href="{{ route($marketRoute) }}" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">Show all products</a>
                @else
                    <h2 class="mt-5 text-lg font-semibold text-slate-900">No products yet</h2>
                    <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Pharmacies are still adding their stock. Check back soon.</p>
                @endif
            </div>
        @endif
    </section>

@if ($inDashboard)</div>@endif
@endsection

@push('scripts')
    <script>
        // "Near me": ask the device where it is, then reload the list sorted by distance.
        document.querySelectorAll('[data-near-me]').forEach(box => {
            const status = box.querySelector('[data-near-status]');
            const say = message => { status.textContent = message; status.classList.remove('hidden'); };

            box.querySelectorAll('[data-near-button]').forEach(button => {
                button.addEventListener('click', () => {
                    if (!navigator.geolocation) {
                        say('This browser cannot share a location.');
                        return;
                    }

                    const label = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> Finding you…';

                    navigator.geolocation.getCurrentPosition(position => {
                        location.href = box.dataset.url
                            .replace('LATITUDE', position.coords.latitude.toFixed(5))
                            .replace('LONGITUDE', position.coords.longitude.toFixed(5));
                    }, problem => {
                        button.disabled = false;
                        button.innerHTML = label;
                        say(problem.code === 1
                            ? 'Location is blocked for this site. Allow it in your browser settings, then press the button again.'
                            : 'We could not read your location. Check that location is switched on, then try again.');
                    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
                });
            });
        });
    </script>
@endpush
