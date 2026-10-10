{{--
    Map position for a pharmacy or branch: a small map with a pin, "Use my
    location", and "Find the address on the map". Dropping or moving the pin
    fills in the address fields of the form it sits in (the inputs marked
    data-place="address|city|state").

    Needs: $latitude, $longitude (may be empty). Include
    partials.location-picker-script once on the page.
--}}
@php $hasPin = filled($latitude) && filled($longitude); @endphp
<div data-location-picker class="rounded-2xl border border-slate-200 bg-white">
    <input type="hidden" name="latitude" value="{{ $latitude }}" data-latitude>
    <input type="hidden" name="longitude" value="{{ $longitude }}" data-longitude>

    <div class="flex flex-col gap-3 p-3.5">
        <div class="flex min-w-0 items-start gap-2.5">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-location-dot text-sm"></i></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-slate-900">Position on the map</p>
                <p class="mt-0.5 text-xs leading-5 text-slate-500" data-location-status aria-live="polite">{{ $hasPin ? 'Pinned. Drag the pin if it is not on the right building.' : 'Not pinned yet. Delivery fees and “nearest pharmacy” need this.' }}</p>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" data-find-address class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700">
                <i class="fas fa-magnifying-glass-location"></i> Find the address
            </button>
            <button type="button" data-use-location class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3.5 text-xs font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                <i class="fas fa-location-crosshairs"></i> Use my location
            </button>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5">
            <p class="text-xs leading-5 text-slate-500"><i class="fas fa-circle-info mr-1 text-slate-400"></i>Press “Use my location” while you are inside the shop.</p>
            <a data-google-check href="{{ $hasPin ? 'https://www.google.com/maps/search/?api=1&query=' . $latitude . ',' . $longitude : '#' }}" target="_blank" rel="noopener"
                class="{{ $hasPin ? 'inline-flex' : 'hidden' }} items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                <i class="fas fa-arrow-up-right-from-square text-[10px]"></i> Check on Google Maps
            </a>
        </div>
    </div>

    <div class="relative border-t border-slate-200">
        <div data-map class="z-0 h-56 w-full rounded-b-2xl bg-slate-100 sm:h-64" role="application" aria-label="Map. Click or drag the pin to set the position."></div>
        <p data-map-hint class="pointer-events-none absolute inset-x-3 bottom-3 z-[500] rounded-lg bg-slate-900/85 px-3 py-2 text-center text-xs font-medium text-white backdrop-blur {{ $hasPin ? 'hidden' : '' }}">
            Tap the map where the building is, or use one of the buttons above.
        </p>
    </div>
</div>
