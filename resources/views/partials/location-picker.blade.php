{{--
    Where a pharmacy or branch is: a search box that suggests addresses as
    you type, "Use my location", and a map with a pin that can be dragged.
    Whichever way the place is chosen, the pin moves and the address fields
    of the form it sits in are filled in (the inputs marked
    data-place="address|city|state").

    Needs: $latitude, $longitude (may be empty). Include
    partials.location-picker-script once on the page.
--}}
@php $hasPin = filled($latitude) && filled($longitude); @endphp
<div data-location-picker class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <input type="hidden" name="latitude" value="{{ $latitude }}" data-latitude>
    <input type="hidden" name="longitude" value="{{ $longitude }}" data-longitude>

    <div class="space-y-3 p-3.5 sm:p-4">
        {{-- Search, and the shortcut for someone standing in the shop --}}
        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative min-w-0 flex-1">
                <label class="sr-only" for="picker-search-{{ $pickerId = uniqid() }}">Search for the street or area</label>
                <i class="fas fa-magnifying-glass pointer-events-none absolute left-3.5 top-1/2 z-10 -translate-y-1/2 text-sm text-slate-400"></i>
                <input id="picker-search-{{ $pickerId }}" type="search" data-picker-search placeholder="Search for the street or area"
                    data-address-suggest data-latitude="[data-latitude]" data-longitude="[data-longitude]" data-city="[data-place=city]" data-state="[data-place=state]"
                    class="h-11 w-full rounded-xl border border-slate-300 bg-white pl-10 pr-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
            </div>
            <button type="button" data-use-location class="inline-flex h-11 flex-shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                <i class="fas fa-location-crosshairs"></i> Use my location
            </button>
        </div>

        <p class="flex items-start gap-2 text-xs leading-5 text-slate-500" data-location-status aria-live="polite">
            <span>{{ $hasPin ? 'Pinned. Drag the pin if it is not on the right building.' : 'Search for the address, or press “Use my location” while you are inside the shop.' }}</span>
        </p>
    </div>

    {{-- The map: confirms the spot, and lets the pin be moved onto the exact building --}}
    <div class="relative border-t border-slate-200">
        <div data-map class="z-0 h-56 w-full bg-slate-100 sm:h-64" role="application" aria-label="Map. Click or drag the pin to set the position."></div>
        <p data-map-hint class="pointer-events-none absolute inset-x-3 bottom-3 z-[500] rounded-lg bg-slate-900/85 px-3 py-2 text-center text-xs font-medium text-white backdrop-blur {{ $hasPin ? 'hidden' : '' }}">
            Search above, or tap the map where the building is.
        </p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-slate-100 bg-slate-50/70 px-3.5 py-2.5 sm:px-4">
        <p class="text-xs leading-5 text-slate-500"><i class="fas fa-hand-pointer mr-1 text-slate-400"></i>Drag the pin onto the exact building.</p>
        <a data-google-check href="{{ $hasPin ? 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude : '#' }}" target="_blank" rel="noopener"
            class="{{ $hasPin ? 'inline-flex' : 'hidden' }} items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
            <i class="fas fa-arrow-up-right-from-square text-[10px]"></i> Check on Google Maps
        </a>
    </div>
</div>
