@extends('layouts.site')

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-emerald-50 via-white to-white">
        <div class="pointer-events-none absolute -right-32 -top-40 h-[30rem] w-[30rem] rounded-full bg-emerald-200/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -left-40 top-52 h-80 w-80 rounded-full bg-emerald-100/60 blur-3xl" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-4 pb-16 pt-12 sm:px-6 sm:pt-16 lg:grid-cols-[1.05fr_0.95fr] lg:gap-10 lg:px-8 lg:pb-24 lg:pt-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-semibold text-emerald-800 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Real stock from real pharmacies
                </p>

                <h1 class="mt-5 text-4xl font-extrabold leading-[1.08] tracking-tight text-slate-900 sm:text-5xl lg:text-[3.5rem]">
                    Find your medicine.<br>
                    <span class="text-emerald-600">Compare pharmacies.</span>
                </h1>

                <p class="mt-5 max-w-xl text-base leading-7 text-slate-600 sm:text-lg">
                    Search medicines and everyday health products, see which pharmacies have them and at what price, then order from the one that suits you.
                </p>

                <form action="{{ route('marketplace') }}" method="GET" role="search" class="mt-8 flex max-w-xl items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/5 focus-within:border-emerald-400 focus-within:ring-4 focus-within:ring-emerald-100">
                    <label for="hero-search" class="sr-only">Search products</label>
                    <i class="fas fa-magnifying-glass ml-3 text-slate-400"></i>
                    <input id="hero-search" type="search" name="q" autocomplete="off" placeholder="Search paracetamol, vitamin C, diapers…" class="min-w-0 flex-1 border-0 bg-transparent px-2 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0">
                    <button type="submit" class="flex-shrink-0 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 sm:px-6">
                        <span class="hidden sm:inline">Search</span>
                        <i class="fas fa-arrow-right sm:hidden"></i>
                    </button>
                </form>

                @if ($categories->isNotEmpty())
                    <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-slate-500">Popular:</span>
                        @foreach ($categories->take(4) as $item)
                            <a href="{{ route('marketplace', ['category' => $item->name]) }}" class="rounded-full border border-slate-200 bg-white px-3 py-1 font-medium text-slate-700 transition-colors hover:border-emerald-300 hover:text-emerald-700">{{ $item->name }}</a>
                        @endforeach
                    </div>
                @endif

                <dl class="mt-10 grid max-w-md grid-cols-3 gap-6 border-t border-slate-200 pt-6">
                    <div>
                        <dt class="text-xs font-medium text-slate-500">Products online</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($stats['products']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">{{ Str::plural('Pharmacy', $stats['pharmacies']) }}</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-slate-900">{{ number_format($stats['pharmacies']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-slate-500">To browse</dt>
                        <dd class="mt-1 text-2xl font-extrabold text-slate-900">Free</dd>
                    </div>
                </dl>
            </div>

            {{-- Hero visual: a product page in miniature, built from real products. --}}
            <div class="relative mx-auto w-full max-w-md lg:max-w-none" aria-hidden="true">
                @php $heroProduct = $featured->first(); @endphp
                <div class="relative rounded-3xl border border-slate-200 bg-white p-4 shadow-2xl shadow-emerald-900/10 sm:p-5">
                    <div class="flex items-center gap-2 rounded-xl bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                        <i class="fas fa-magnifying-glass text-xs"></i>
                        <span class="truncate">{{ $heroProduct->name ?? 'Paracetamol 500mg' }}</span>
                    </div>

                    <div class="mt-4 flex gap-4">
                        <div class="relative h-24 w-24 flex-shrink-0 overflow-hidden rounded-2xl bg-emerald-50 sm:h-28 sm:w-28">
                            <div class="absolute inset-0 flex items-center justify-center text-emerald-300"><i class="fas fa-pills text-3xl"></i></div>
                            @if ($heroProduct?->photo_url)
                                <img src="{{ $heroProduct->photo_url }}" alt="" onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">{{ $heroProduct->category ?? 'OTC Medications' }}</p>
                            <p class="mt-1 line-clamp-2 font-semibold leading-5 text-slate-900">{{ $heroProduct->name ?? 'Paracetamol 500mg' }}</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">&#8358;{{ number_format((float) ($heroProduct->min_price ?? 1500), 2) }}</p>
                        </div>
                    </div>

                    <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Available at</p>
                    <div class="mt-2 space-y-2">
                        @foreach ([['Closest to you', 'In stock', 'emerald'], ['Lowest price', 'In stock', 'emerald'], ['Open late', 'Few left', 'amber']] as [$label, $state, $tone])
                            <div class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5">
                                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><i class="fas fa-store text-sm"></i></span>
                                <div class="min-w-0 flex-1">
                                    <div class="h-2.5 w-28 rounded-full bg-slate-200"></div>
                                    <p class="mt-1.5 text-xs text-slate-500">{{ $label }}</p>
                                </div>
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $tone === 'amber' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $state }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="absolute -bottom-12 -left-3 hidden items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-xl shadow-slate-900/10 sm:flex">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white"><i class="fas fa-check"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Stock you can trust</p>
                        <p class="text-xs text-slate-500">Straight from the pharmacy's till</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    @if ($categories->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Shop by category</h2>
                    <p class="mt-2 text-sm text-slate-600 sm:text-base">Medicines, and the everyday things a pharmacy sells too.</p>
                </div>
                <a href="{{ route('marketplace') }}" class="hidden flex-shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-800 sm:inline">Browse everything <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                @foreach ($categories->take(8) as $item)
                    <a href="{{ route('marketplace', ['category' => $item->name]) }}" class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 transition hover:border-emerald-300 hover:shadow-md hover:shadow-slate-900/5 sm:p-4">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 transition-colors group-hover:bg-emerald-600 group-hover:text-white">
                            <i class="fas {{ $item->icon }}"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold leading-5 text-slate-900">{{ $item->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $item->count }} {{ Str::plural('product', $item->count) }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured products --}}
    <section class="border-y border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">New on the marketplace</h2>
                    <p class="mt-2 text-sm text-slate-600 sm:text-base">In stock right now at a CuraD pharmacy.</p>
                </div>
                <a href="{{ route('marketplace') }}" class="flex-shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-800">See all <i class="fas fa-arrow-right ml-1 text-xs"></i></a>
            </div>

            @if ($featured->isNotEmpty())
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        @include('partials.product-card', ['product' => $product])
                    @endforeach
                </div>
            @else
                <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="fas fa-box-open"></i></span>
                    <p class="mt-4 font-semibold text-slate-900">Products are on their way</p>
                    <p class="mt-1 text-sm text-slate-600">Pharmacies are still adding their stock. Check back soon.</p>
                </div>
            @endif
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold text-emerald-700">How it works</p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">From search to the counter in three steps</h2>
        </div>

        <ol class="mt-10 grid gap-5 md:grid-cols-3">
            @foreach ([
                ['fa-magnifying-glass', 'Search', 'Type the name of a medicine or product. You do not need an account to look.'],
                ['fa-scale-balanced', 'Compare', 'See every pharmacy that has it, with their price, address and whether it is in stock.'],
                ['fa-bag-shopping', 'Order', 'Sign in, order from the pharmacy you picked, and collect it knowing it is there.'],
            ] as $index => [$icon, $title, $text])
                <li class="relative rounded-2xl border border-slate-200 bg-white p-6">
                    <span class="absolute right-5 top-5 text-4xl font-extrabold text-slate-100">{{ $index + 1 }}</span>
                    <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30"><i class="fas {{ $icon }}"></i></span>
                    <h3 class="relative mt-5 text-lg font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="relative mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- For pharmacies --}}
    <section id="for-pharmacies" class="bg-slate-900">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
            <div>
                <p class="text-sm font-semibold text-emerald-400">For pharmacies</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Run the shop and sell online from one system</h2>
                <p class="mt-4 max-w-xl text-base leading-7 text-slate-300">
                    CuraD is the till, the stock book and the online shop in one. A sale at the counter and an order from the marketplace come out of the same stock, so the numbers always agree.
                </p>

                <ul class="mt-8 grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    @foreach ([
                        ['fa-cash-register', 'Point of sale', 'Fast till with barcode search, split payments and printed receipts.'],
                        ['fa-boxes-stacked', 'Stock and expiry', 'Receive stock, track batches and get low-stock and expiry warnings.'],
                        ['fa-user-shield', 'Staff and roles', 'Give each staff member their own login, and a role that decides what they can do.'],
                        ['fa-chart-line', 'Reports', 'Sales by day, staff, product and payment method, with a full activity log.'],
                    ] as [$icon, $title, $text])
                        <li class="flex gap-3.5">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400"><i class="fas {{ $icon }}"></i></span>
                            <span>
                                <span class="block font-semibold text-white">{{ $title }}</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-400">{{ $text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('register', ['as' => 'pharmacy']) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-400">
                        Register your pharmacy <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-700 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-slate-800">Sign in</a>
                </div>
            </div>

            {{-- A till in miniature --}}
            <div class="relative mx-auto w-full max-w-lg" aria-hidden="true">
                <div class="rounded-3xl border border-slate-700 bg-slate-800 p-4 shadow-2xl sm:p-5">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-white">Current sale</p>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-400"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>POS</span>
                    </div>

                    <div class="mt-4 space-y-2.5">
                        @foreach ([['Paracetamol 500mg', '2', '1,000.00'], ['Vitamin C 1000mg', '1', '3,500.00'], ['Digital thermometer', '1', '4,200.00']] as [$name, $qty, $amount])
                            <div class="flex items-center gap-3 rounded-xl bg-slate-900/60 px-3 py-2.5">
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-slate-700 text-xs font-bold text-slate-300">{{ $qty }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ $name }}</span>
                                <span class="text-sm font-semibold text-white">&#8358;{{ $amount }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex items-center justify-between border-t border-slate-700 pt-4">
                        <span class="text-sm text-slate-400">Total</span>
                        <span class="text-2xl font-extrabold text-white">&#8358;8,700.00</span>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs font-semibold">
                        <span class="rounded-lg bg-emerald-500 py-2.5 text-white">Cash</span>
                        <span class="rounded-lg bg-slate-700 py-2.5 text-slate-300">Card</span>
                        <span class="rounded-lg bg-slate-700 py-2.5 text-slate-300">Transfer</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Why CuraD --}}
    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach ([
                ['fa-circle-check', 'Stock that is really there', 'What you see is what the pharmacy has on its shelf, because the marketplace and the shop counter share one stock record.'],
                ['fa-tags', 'Prices side by side', 'When more than one pharmacy sells a product, you see all their prices on one page before you choose.'],
                ['fa-store', 'Pick up or get it delivered', 'Collect your order at the pharmacy, or have a rider bring it. You see the delivery fee before you pay.'],
            ] as [$icon, $title, $text])
                <div class="rounded-2xl bg-slate-50 p-6 sm:p-7">
                    <i class="fas {{ $icon }} text-xl text-emerald-600"></i>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 overflow-hidden rounded-3xl bg-emerald-600 px-6 py-10 text-center sm:px-12 sm:py-14">
            <h2 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">Looking for something today?</h2>
            <p class="mx-auto mt-3 max-w-xl text-emerald-50">Search the marketplace now. Create a free account when you are ready to order.</p>
            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('marketplace') }}" class="rounded-xl bg-white px-6 py-3.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50">Browse the marketplace</a>
                @guest
                    <a href="{{ route('register') }}" class="rounded-xl border border-emerald-300/60 px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700">Create a free account</a>
                @endguest
            </div>
        </div>
    </section>
@endsection
