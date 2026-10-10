@extends('layouts.app')

@section('title', 'Watchlist')
@section('header_title', 'Watchlist')

@section('content')
<div class="mx-auto -mt-3 max-w-[1100px] space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">Products you are keeping an eye on, with today’s price and who has them in stock.</p>
        <a href="{{ route('user.marketplace') }}" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
            <i class="fas fa-plus text-xs"></i> Add from the marketplace
        </a>
    </div>

    @if ($items->isNotEmpty())
        <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
            @foreach ($items as $item)
                @php
                    $product = $item->medication;
                    $live = $item->live;
                    $onSale = $live && $live->product_id;
                @endphp
                <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex gap-3.5 p-4">
                        <span class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl bg-slate-100">
                            <span class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="fas {{ \App\Models\Medication::categoryIcon($product?->category) }} text-xl"></i></span>
                            @if ($product?->photo_url)
                                <img src="{{ $product->photo_url }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            @if ($product?->category)
                                <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{{ $product->category }}</p>
                            @endif
                            <h2 class="line-clamp-2 text-sm font-semibold leading-5 text-slate-900">{{ $product?->name ?? 'This product was removed' }}</h2>
                            @if ($product?->dosage)
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $product->dosage }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-100 px-4 py-3">
                        @if ($onSale)
                            <div class="min-w-0">
                                <p class="text-base font-bold text-slate-900">&#8358;{{ number_format((float) $live->price, 2) }}</p>
                                @if ($live->in_stock)
                                    <p class="mt-0.5 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>In stock at {{ $live->pharmacies }} {{ Str::plural('pharmacy', $live->pharmacies) }}</p>
                                @else
                                    <p class="mt-0.5 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Out of stock for now</p>
                                @endif
                            </div>
                        @else
                            <p class="text-xs font-medium text-slate-500">Not on the marketplace right now</p>
                        @endif

                        <div class="flex flex-shrink-0 items-center gap-2">
                            <form action="{{ route('user.watchlist.destroy', $item->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" aria-label="Remove {{ $product?->name }} from watchlist" title="Remove">
                                    <i class="fas fa-trash-can text-sm"></i>
                                </button>
                            </form>
                            @if ($onSale)
                                <a href="{{ route('user.marketplace.show', $live->product_id) }}" class="inline-flex h-10 items-center gap-2 rounded-xl {{ $live->in_stock ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'border border-slate-200 text-slate-700 hover:border-slate-300' }} px-3.5 text-sm font-semibold transition">
                                    {{ $live->in_stock ? 'Buy' : 'View' }} <i class="fas fa-arrow-right text-xs"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas fa-bookmark"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">Your watchlist is empty</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Open any product and press “Add to my watchlist”. It is handy for medicines you buy often or one that is out of stock.</p>
            <a href="{{ route('user.marketplace') }}" class="mt-6 inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"><i class="fas fa-store text-xs"></i>Browse the marketplace</a>
        </div>
    @endif
</div>
@endsection
