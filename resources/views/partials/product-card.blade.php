{{--
    One product in the catalogue. $product is a grouped row from
    MarketplaceController::products(). Pass $productRoute to keep the shopper
    inside the dashboard marketplace.
--}}
@php
    $inStock = (int) $product->total_stock > 0;
    $pharmacyCount = (int) $product->pharmacy_count;
    $priceVaries = (float) $product->max_price > (float) $product->min_price;
    $km = isset($product->nearest_km) ? (float) $product->nearest_km : null;
@endphp
<a href="{{ route($productRoute ?? 'marketplace.show', $product->id) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white transition duration-200 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-lg hover:shadow-slate-900/5">
    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
        <div class="absolute inset-0 flex items-center justify-center text-slate-300">
            <i class="fas {{ \App\Models\Medication::categoryIcon($product->category) }} text-4xl"></i>
        </div>
        @if ($product->photo_url)
            <img src="{{ $product->photo_url }}" alt="{{ $product->name }}" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
        @endif

        <div class="absolute left-2.5 top-2.5 flex flex-wrap gap-1.5">
            @if ($product->is_rx)
                <span class="rounded-full bg-slate-900/85 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur">Prescription</span>
            @endif
            @unless ($inStock)
                <span class="rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 ring-1 ring-slate-200">Out of stock</span>
            @endunless
        </div>

        @if ($km !== null)
            <span class="absolute bottom-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-white/95 px-2 py-1 text-[11px] font-semibold text-slate-800 shadow-sm ring-1 ring-slate-200">
                <i class="fas fa-location-dot text-[10px] text-emerald-600"></i>{{ $km < 1 ? 'Under 1 km' : number_format($km, $km < 10 ? 1 : 0) . ' km' }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-3.5 sm:p-4">
        @if ($product->category)
            <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{{ $product->category }}</p>
        @endif
        <h3 class="mt-1 line-clamp-2 text-sm font-semibold leading-5 text-slate-900 sm:text-[15px]">{{ $product->name }}</h3>
        @if ($product->dosage)
            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $product->dosage }}</p>
        @endif

        <div class="mt-auto pt-3">
            <p class="text-base font-bold text-slate-900 sm:text-lg">
                @if ($priceVaries)<span class="text-xs font-medium text-slate-500">from </span>@endif&#8358;{{ number_format((float) $product->min_price, 2) }}
            </p>
            <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                <i class="fas fa-store text-[10px] text-slate-400"></i>
                {{ $pharmacyCount }} {{ Str::plural('pharmacy', $pharmacyCount) }}
                @if ($inStock)
                    <span class="ml-auto inline-flex items-center gap-1 font-medium text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>In stock</span>
                @endif
            </p>
        </div>
    </div>
</a>
