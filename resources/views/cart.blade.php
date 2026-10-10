@extends('layouts.site')

@php
    $pharmacyCount = count($plan['groups']);
    $unavailable = $plan['unavailable'];
    $unitCount = (int) $items->sum('quantity');
@endphp

@section('title', 'Your cart | CuraD')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-10 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Your cart</h1>
                @if ($items->isNotEmpty())
                    <p class="mt-1 text-sm text-slate-600">{{ $unitCount }} {{ Str::plural('item', $unitCount) }} from {{ $items->count() }} {{ Str::plural('product', $items->count()) }}</p>
                @endif
            </div>
            <a href="{{ route('marketplace') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Keep shopping</a>
        </div>

        @if ($items->isEmpty())
            <div class="mt-8 rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white text-2xl text-emerald-600 shadow-sm ring-1 ring-slate-200"><i class="fas fa-bag-shopping"></i></span>
                <h2 class="mt-5 text-lg font-semibold text-slate-900">Your cart is empty</h2>
                <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Add medicines and health products from any pharmacy. You can mix them freely; CuraD works out who supplies what.</p>
                <a href="{{ route('marketplace') }}" class="mt-6 inline-flex rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Browse the marketplace</a>
            </div>
        @else
            <div class="mt-6 grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start xl:grid-cols-[minmax(0,1fr)_24rem]">
                {{-- Items --}}
                <div class="space-y-3">
                    @if ($unavailable)
                        <div class="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                            <p>{{ count($unavailable) === 1 ? 'One product' : count($unavailable) . ' products' }} in your cart cannot be supplied right now. Lower the quantity or remove {{ count($unavailable) === 1 ? 'it' : 'them' }} to continue. You are never charged for something that is not available.</p>
                        </div>
                    @endif

                    @foreach ($items as $item)
                        @php
                            $line = $prices[$item->product_key] ?? null;
                            $product = $item->medication;
                            $missing = array_key_exists($item->product_key, $unavailable);
                        @endphp
                        <article class="flex gap-3.5 rounded-2xl border bg-white p-3.5 sm:gap-5 sm:p-4 {{ $missing ? 'border-amber-300' : 'border-slate-200' }}">
                            <a href="{{ $product ? route('marketplace.show', $product->id) : route('marketplace') }}" class="relative h-20 w-20 flex-shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:h-24 sm:w-24">
                                <span class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="fas {{ \App\Models\Medication::categoryIcon($product?->category) }} text-2xl"></i></span>
                                @if ($product?->photo_url)
                                    <img src="{{ $product->photo_url }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover {{ $missing ? 'opacity-50 grayscale' : '' }}">
                                @endif
                            </a>

                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="line-clamp-2 text-sm font-semibold leading-5 text-slate-900 sm:text-[15px]">{{ $product->name ?? ucfirst($item->product_key) }}</h2>
                                        @if ($product?->dosage)
                                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $product->dosage }}</p>
                                        @endif
                                    </div>
                                    <form action="{{ route('cart.remove', $item) }}" method="POST" class="flex-shrink-0">
                                        @csrf @method('DELETE')
                                        <button type="submit" aria-label="Remove {{ $product->name ?? 'product' }}" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-trash-can text-sm"></i></button>
                                    </form>
                                </div>

                                @if ($missing)
                                    <p class="mt-1.5 text-xs font-semibold text-amber-700"><i class="fas fa-circle-exclamation mr-1"></i>No pharmacy has {{ $item->quantity > 1 ? $item->quantity . ' of these' : 'this' }} in stock right now</p>
                                @elseif ($line)
                                    <p class="mt-1.5 truncate text-xs text-slate-500"><i class="fas fa-store mr-1 text-[10px] text-slate-400"></i>{{ $line['offer']->pharmacy->displayName() }}</p>
                                @endif

                                <div class="mt-auto flex flex-wrap items-end justify-between gap-2 pt-3">
                                    <form action="{{ route('cart.update', $item) }}" method="POST" data-quantity-form>
                                        @csrf @method('PATCH')
                                        <label for="quantity-{{ $item->id }}" class="sr-only">Quantity</label>
                                        <div class="inline-flex h-10 items-center rounded-xl border border-slate-300 bg-white" data-stepper>
                                            <button type="button" data-step="-1" aria-label="One less" class="flex h-full w-9 items-center justify-center rounded-l-xl text-slate-600 transition-colors hover:bg-slate-100"><i class="fas fa-minus text-[10px]"></i></button>
                                            <input id="quantity-{{ $item->id }}" type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $maxQuantity }}" inputmode="numeric" class="h-full w-10 border-0 bg-transparent p-0 text-center text-sm font-semibold text-slate-900 [appearance:textfield] focus:outline-none focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                                            <button type="button" data-step="1" aria-label="One more" class="flex h-full w-9 items-center justify-center rounded-r-xl text-slate-600 transition-colors hover:bg-slate-100"><i class="fas fa-plus text-[10px]"></i></button>
                                        </div>
                                        <noscript><button type="submit" class="ml-2 text-xs font-semibold text-emerald-700 underline">Update</button></noscript>
                                    </form>

                                    @if ($line)
                                        <div class="text-right">
                                            <p class="text-base font-bold text-slate-900">&#8358;{{ number_format($line['line_total'], 2) }}</p>
                                            @if ($item->quantity > 1)
                                                <p class="text-xs text-slate-500">&#8358;{{ number_format($line['unit_price'], 2) }} each</p>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Summary --}}
                <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
                    <h2 class="text-base font-bold text-slate-900">Order summary</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Products</dt>
                            <dd class="font-semibold text-slate-900">&#8358;{{ number_format($plan['subtotal'], 2) }}</dd>
                        </div>
                        @if ($plan['service_fee'] > 0)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-600">Service fee</dt>
                                <dd class="font-semibold text-slate-900">&#8358;{{ number_format($plan['service_fee'], 2) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-600">Delivery</dt>
                            <dd class="text-slate-500">Chosen at checkout</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-200 pt-4">
                        <p class="text-sm font-semibold text-slate-900">Total so far</p>
                        <p class="text-xl font-extrabold text-slate-900">&#8358;{{ number_format($plan['subtotal'] + $plan['service_fee'], 2) }}</p>
                    </div>

                    @if ($pharmacyCount > 0)
                        <p class="mt-4 flex gap-2.5 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600">
                            <i class="fas fa-store mt-0.5 text-emerald-600"></i>
                            <span>
                                @if ($pharmacyCount === 1)
                                    Everything can come from <span class="font-semibold text-slate-900">one pharmacy</span>.
                                @else
                                    This order needs <span class="font-semibold text-slate-900">{{ $pharmacyCount }} pharmacies</span>. It is still one order and one payment.
                                @endif
                            </span>
                        </p>
                    @endif

                    @if ($unavailable || $pharmacyCount === 0)
                        <span class="mt-5 flex h-12 w-full cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 text-sm font-semibold text-slate-500">Fix the cart to continue</span>
                    @else
                        <a href="{{ route('checkout') }}" class="mt-5 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                            Go to checkout <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                        @guest
                            <p class="mt-3 text-center text-xs text-slate-500"><i class="fas fa-lock mr-1"></i>You will sign in or create a free account first. Your cart is kept.</p>
                        @endguest
                    @endif
                </aside>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-stepper]').forEach(stepper => {
            const input = stepper.querySelector('input');
            const form = stepper.closest('[data-quantity-form]');
            let timer;

            // Saves shortly after the last tap, so tapping + three times sends one update.
            function save() {
                clearTimeout(timer);
                timer = setTimeout(() => form && form.submit(), 450);
            }

            stepper.querySelectorAll('[data-step]').forEach(button => {
                button.addEventListener('click', () => {
                    const min = Number(input.min) || 1;
                    const max = Number(input.max) || 99;
                    const next = Math.min(max, Math.max(min, (Number(input.value) || min) + Number(button.dataset.step)));
                    if (next === Number(input.value)) return;
                    input.value = next;
                    save();
                });
            });

            input.addEventListener('change', save);
        });
    </script>
@endpush
