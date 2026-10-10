@extends('layouts.app')

@section('title', 'Saved pharmacies')
@section('header_title', 'Saved Pharmacies')

@section('content')
<div class="mx-auto -mt-3 max-w-[1100px] space-y-5">

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-500">Pharmacies you saved, with how to reach them.</p>

        @if ($savedCount > 3 || $search !== '')
            <form action="{{ route('user.pharmacies') }}" method="GET" role="search" class="flex h-10 w-full items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100 sm:w-72">
                <label for="saved-search" class="sr-only">Search saved pharmacies</label>
                <i class="fas fa-magnifying-glass text-sm text-slate-400"></i>
                <input id="saved-search" type="search" name="search" value="{{ $search }}" placeholder="Search by name or area" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm placeholder-slate-400 focus:outline-none focus:ring-0">
            </form>
        @endif
    </div>

    @if ($pharmacies->isNotEmpty())
        <div class="grid gap-3 sm:gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($pharmacies as $pharmacy)
                @php
                    $place = $pharmacy->fullAddress();
                    $hasPin = filled($pharmacy->latitude) && filled($pharmacy->longitude);
                    $mapTarget = $hasPin ? $pharmacy->latitude . ',' . $pharmacy->longitude : ($place ? urlencode($pharmacy->name . ', ' . $place) : null);
                @endphp
                <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-start gap-3 p-4 sm:p-5">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-store"></i></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="break-words text-[15px] font-bold leading-5 text-slate-900">{{ $pharmacy->displayName() }}</h2>
                            <p class="mt-1 inline-flex items-center gap-1.5 text-xs font-semibold {{ $pharmacy->is_active ? 'text-emerald-700' : 'text-slate-500' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $pharmacy->is_active ? 'Taking online orders' : 'Not taking online orders now' }}
                            </p>
                        </div>
                        <form action="{{ route('user.pharmacies.toggle', $pharmacy) }}" method="POST" class="flex-shrink-0">
                            @csrf
                            <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg text-rose-500 transition hover:bg-rose-50" aria-label="Remove {{ $pharmacy->name }} from saved pharmacies" title="Remove from saved">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                    </div>

                    <dl class="space-y-2 px-4 pb-4 text-sm text-slate-600 sm:px-5">
                        @if ($place)
                            <div class="flex gap-2.5"><dt class="w-4 flex-shrink-0 text-center text-slate-400"><i class="fas fa-location-dot text-xs"></i><span class="sr-only">Address</span></dt><dd class="min-w-0 break-words">{{ $place }}</dd></div>
                        @endif
                        @if ($pharmacy->operating_hours)
                            <div class="flex gap-2.5"><dt class="w-4 flex-shrink-0 text-center text-slate-400"><i class="fas fa-clock text-xs"></i><span class="sr-only">Opening hours</span></dt><dd class="min-w-0 break-words">{{ $pharmacy->operating_hours }}</dd></div>
                        @endif
                        @if ($pharmacy->phone)
                            <div class="flex gap-2.5"><dt class="w-4 flex-shrink-0 text-center text-slate-400"><i class="fas fa-phone text-xs"></i><span class="sr-only">Phone</span></dt><dd><a href="tel:{{ $pharmacy->phone }}" class="font-medium text-slate-900 hover:text-emerald-700">{{ $pharmacy->phone }}</a></dd></div>
                        @endif
                    </dl>

                    @if ($mapTarget)
                        <div class="mt-auto grid grid-cols-2 gap-2 border-t border-slate-100 bg-slate-50/70 p-3">
                            <a href="https://www.google.com/maps/search/?api=1&query={{ $mapTarget }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900"><i class="fas fa-map-location-dot text-xs text-slate-400"></i>Map</a>
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $mapTarget }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-slate-900 text-sm font-semibold text-white transition hover:bg-slate-800"><i class="fas fa-diamond-turn-right text-xs"></i>Directions</a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>

        @if ($pharmacies->hasPages())
            <nav class="flex items-center justify-between gap-3" aria-label="Pages">
                @if ($pharmacies->onFirstPage())
                    <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Previous</span>
                @else
                    <a href="{{ $pharmacies->previousPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Previous</a>
                @endif
                <p class="text-sm text-slate-500">Page {{ $pharmacies->currentPage() }} of {{ $pharmacies->lastPage() }}</p>
                @if ($pharmacies->hasMorePages())
                    <a href="{{ $pharmacies->nextPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Next</a>
                @else
                    <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Next</span>
                @endif
            </nav>
        @endif
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas {{ $search !== '' ? 'fa-magnifying-glass' : 'fa-heart' }}"></i></span>
            @if ($search !== '')
                <h2 class="mt-5 text-lg font-semibold text-slate-900">No saved pharmacy matches “{{ $search }}”</h2>
                <a href="{{ route('user.pharmacies') }}" class="mt-6 inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-slate-300">Show all saved pharmacies</a>
            @else
                <h2 class="mt-5 text-lg font-semibold text-slate-900">No saved pharmacies yet</h2>
                <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Open a product and press “Save pharmacy” beside any pharmacy that sells it. You will find its address, phone and directions here.</p>
                <a href="{{ route('user.marketplace') }}" class="mt-6 inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"><i class="fas fa-store text-xs"></i>Browse the marketplace</a>
            @endif
        </div>
    @endif
</div>
@endsection
