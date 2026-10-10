@php
    $siteUser = Auth::user();
    $siteDashboard = null;

    if ($siteUser) {
        $siteDashboard = match ($siteUser->role) {
            'vendor' => route('vendor.dashboard'),
            'sales_person' => route('sales.staff.dashboard'),
            default => route('user.dashboard'),
        };
    }

    $siteCartCount = \App\Models\Cart::count();

    $siteNav = [
        ['label' => 'Marketplace', 'url' => route('marketplace'), 'active' => request()->routeIs('marketplace*')],
        ['label' => 'How it works', 'url' => route('home') . '#how-it-works', 'active' => false],
        ['label' => 'For pharmacies', 'url' => route('home') . '#for-pharmacies', 'active' => false],
    ];
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.viewport')
    <title>@yield('title', 'CuraD — Medicines and health products from pharmacies near you')</title>
    <meta name="description" content="@yield('description', 'Search medicines and health products, compare prices across pharmacies and order from the one that suits you. CuraD also runs the pharmacy: point of sale, stock and reports.')">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        [id] { scroll-margin-top: 5.5rem; }
        .no-scrollbar { scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        :focus-visible { outline: 2px solid #059669; outline-offset: 2px; }
    </style>
    @stack('styles')
</head>

<body class="bg-white text-slate-900">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:bg-slate-900 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Skip to content</a>

    <header class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/85 backdrop-blur-lg">
        <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex flex-shrink-0 items-center gap-2.5" aria-label="CuraD home">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30">
                    <i class="fas fa-plus text-sm"></i>
                </span>
                <span class="text-xl font-extrabold tracking-tight text-slate-900">Cura<span class="text-emerald-600">D</span></span>
            </a>

            <nav class="ml-6 hidden items-center gap-1 md:flex" aria-label="Main">
                @foreach ($siteNav as $item)
                    <a href="{{ $item['url'] }}" class="rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $item['active'] ? 'bg-emerald-50 text-emerald-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>

            <a href="{{ route('cart') }}" class="relative ml-auto flex h-10 w-10 items-center justify-center rounded-xl text-slate-700 transition-colors hover:bg-slate-100 {{ request()->routeIs('cart', 'checkout') ? 'bg-emerald-50 text-emerald-700' : '' }}" aria-label="Cart, {{ $siteCartCount }} {{ Str::plural('item', $siteCartCount) }}">
                <i class="fas fa-bag-shopping text-lg"></i>
                @if ($siteCartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1 text-[11px] font-bold text-white ring-2 ring-white">{{ $siteCartCount > 99 ? '99+' : $siteCartCount }}</span>
                @endif
            </a>

            <div class="hidden items-center gap-2 md:flex">
                @if ($siteUser)
                    <a href="{{ $siteDashboard }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        <i class="fas fa-gauge-high text-xs"></i> My dashboard
                    </a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900">Sign out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-100">Sign in</a>
                    <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Create account</a>
                @endif
            </div>

            <button type="button" id="site-menu-toggle" class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-700 transition-colors hover:bg-slate-100 md:hidden" aria-label="Open menu" aria-expanded="false" aria-controls="site-menu">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>

        <div id="site-menu" class="hidden border-t border-slate-200/70 bg-white md:hidden">
            <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3" aria-label="Mobile">
                @foreach ($siteNav as $item)
                    <a href="{{ $item['url'] }}" class="rounded-lg px-3 py-3 text-[15px] font-medium {{ $item['active'] ? 'bg-emerald-50 text-emerald-700' : 'text-slate-700 hover:bg-slate-100' }}">{{ $item['label'] }}</a>
                @endforeach
                <div class="mt-2 grid gap-2 border-t border-slate-100 pt-3 {{ $siteUser ? '' : 'grid-cols-2' }}">
                    @if ($siteUser)
                        <a href="{{ $siteDashboard }}" class="rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white">My dashboard</a>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Sign out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-semibold text-slate-700">Sign in</a>
                        <a href="{{ route('register') }}" class="rounded-xl bg-emerald-600 px-4 py-3 text-center text-sm font-semibold text-white">Create account</a>
                    @endif
                </div>
            </nav>
        </div>
    </header>

    <main id="main">
        @if (session('success') || session('error'))
            <div class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                @if (session('success'))
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                        <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i><p>{{ session('success') }}</p>
                    </div>
                @endif
                @if (session('error'))
                    <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                        <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ session('error') }}</p>
                    </div>
                @endif
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-1">
                    <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white"><i class="fas fa-plus text-sm"></i></span>
                        <span class="text-xl font-extrabold tracking-tight">Cura<span class="text-emerald-600">D</span></span>
                    </a>
                    <p class="mt-4 max-w-xs text-sm leading-6 text-slate-600">One place to find medicines and health products, and one system for pharmacies to sell them in store and online.</p>
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Shop</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600">
                        <li><a href="{{ route('marketplace') }}" class="hover:text-emerald-700">All products</a></li>
                        <li><a href="{{ route('marketplace', ['in_stock' => 1]) }}" class="hover:text-emerald-700">In stock now</a></li>
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-emerald-700">How it works</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Pharmacies</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600">
                        <li><a href="{{ route('home') }}#for-pharmacies" class="hover:text-emerald-700">What you get</a></li>
                        <li><a href="{{ route('register', ['as' => 'pharmacy']) }}" class="hover:text-emerald-700">Register your pharmacy</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Account</h2>
                    <ul class="mt-4 space-y-3 text-sm text-slate-600">
                        @if ($siteUser)
                            <li><a href="{{ $siteDashboard }}" class="hover:text-emerald-700">My dashboard</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-emerald-700">Sign in</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-emerald-700">Create account</a></li>
                            <li><a href="{{ route('password.request') }}" class="hover:text-emerald-700">Forgot password</a></li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-col gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ date('Y') }} CuraD. All rights reserved.</p>
                <p>Always read the label. Ask a pharmacist if you are not sure a medicine is right for you.</p>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            const toggle = document.getElementById('site-menu-toggle');
            const menu = document.getElementById('site-menu');
            if (!toggle || !menu) return;

            function setOpen(open) {
                menu.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
                toggle.querySelector('i').className = open ? 'fas fa-xmark text-lg' : 'fas fa-bars text-lg';
            }

            toggle.addEventListener('click', () => setOpen(menu.classList.contains('hidden')));
            menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
            document.addEventListener('keydown', event => { if (event.key === 'Escape') setOpen(false); });
        })();
    </script>
    @stack('scripts')
</body>

</html>
