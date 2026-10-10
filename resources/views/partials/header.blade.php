{{-- The bar across the top of every dashboard page, for customers, owners and staff. --}}
@php
    $headerUser = Auth::user();
    $headerIsStaff = $headerUser->role === 'sales_person';
    $headerIsOwner = $headerUser->role === 'vendor';
    $headerIsCustomer = ! $headerIsStaff && ! $headerIsOwner;
    $headerCartCount = $headerIsCustomer ? \App\Models\Cart::count() : 0;
    $headerNotifications = $headerIsOwner ? route('vendor.notifications') : route('user.notifications');
@endphp
<header class="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-40 px-3 sm:px-6 py-3 sm:py-4 flex justify-between items-center gap-2 transition-all duration-300">
    <div class="flex items-center gap-2 sm:gap-4 min-w-0">
        <button id="header-mobile-toggle" class="lg:hidden text-gray-500 hover:text-gray-900 p-2 flex-shrink-0" aria-label="Open menu">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <div class="min-w-0">
            <h2 class="text-base sm:text-xl font-bold text-slate-800 tracking-tight truncate">@yield('header_title', 'Overview')</h2>
            <p class="text-xs text-slate-400 font-medium hidden sm:block">Welcome back, {{ $headerUser->first_name ?: explode(' ', (string) $headerUser->full_name)[0] }}</p>
        </div>
    </div>

    <div class="flex items-center gap-1.5 sm:gap-3 flex-shrink-0">
        @include('partials.branch-switcher')

        @if ($headerIsStaff && session('impersonator_id'))
            <form action="{{ route('sales.staff.return-to-admin') }}" method="POST">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 transition-colors">
                    <i class="fas fa-user-shield"></i>
                    <span class="hidden sm:inline">Viewing as staff:</span> Back
                </button>
            </form>
        @endif

        @unless ($headerIsStaff)
            <a href="{{ $headerIsCustomer ? route('user.history') : '#' }}" class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-emerald-50 rounded-lg border border-emerald-100 {{ $headerIsCustomer ? 'hover:border-emerald-200' : 'pointer-events-none' }}" title="Wallet balance">
                <i class="fas fa-wallet text-emerald-600 text-xs"></i>
                <span class="text-sm font-bold text-emerald-800">₦{{ number_format((float) ($headerUser->wallet_balance ?? 0), 2) }}</span>
            </a>

            {{-- Customer / Pharmacy switch --}}
            <div class="hidden md:flex items-center gap-1 rounded-full bg-slate-100 p-1 text-xs font-bold">
                <button type="button" @if ($headerIsOwner) onclick="document.getElementById('header-switch-patient-form').submit()" @endif
                    class="px-3 py-1.5 rounded-full transition-colors whitespace-nowrap {{ $headerIsCustomer ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">Customer</button>
                <button type="button" @if ($headerIsCustomer) onclick="document.getElementById('header-switch-pharmacy-form').submit()" @endif
                    class="px-3 py-1.5 rounded-full transition-colors whitespace-nowrap {{ $headerIsOwner ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">Pharmacy</button>
            </div>
            <form id="header-switch-pharmacy-form" action="{{ route('user.become.pharmacy') }}" method="POST" class="hidden">@csrf</form>
            <form id="header-switch-patient-form" action="{{ route('vendor.become.patient') }}" method="POST" class="hidden">@csrf</form>

            <a href="{{ $headerNotifications }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-emerald-700 transition-colors" aria-label="Notifications">
                <i class="fas fa-bell text-lg"></i>
                <span id="header-notification-dot" class="absolute top-2 right-2 w-2.5 h-2.5 bg-rose-500 rounded-full border-2 border-white hidden"></span>
            </a>
        @endunless

        @if ($headerIsCustomer)
            <a href="{{ route('cart') }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-emerald-700 transition-colors" aria-label="Cart, {{ $headerCartCount }} {{ Str::plural('item', $headerCartCount) }}">
                <i class="fas fa-bag-shopping text-lg"></i>
                @if ($headerCartCount > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1 text-[11px] font-bold text-white ring-2 ring-white">{{ $headerCartCount > 99 ? '99+' : $headerCartCount }}</span>
                @endif
            </a>
        @else
            {{-- Opens the sale in progress on the POS page, on small screens. --}}
            <button type="button" id="header-mobile-cart-btn" onclick="typeof openMobileCartDrawer === 'function' && openMobileCartDrawer()" class="lg:hidden relative p-2 text-slate-600 hover:text-emerald-600 transition-colors {{ request()->routeIs('vendor.pos', 'sales.staff.pos') ? 'flex' : 'hidden' }} items-center justify-center" aria-label="Current sale">
                <i class="fas fa-shopping-cart text-lg text-emerald-600"></i>
                <span id="header-cart-badge" class="absolute -top-0.5 -right-0.5 px-1.5 bg-emerald-600 text-white rounded-full text-[10px] font-black hidden">0</span>
            </button>
        @endif

        <a href="{{ $headerIsCustomer ? route('user.profile') : route('vendor.profile') }}" class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white shadow-sm shadow-emerald-200 ring-2 ring-white" aria-label="My account">
            {{ strtoupper(mb_substr($headerUser->full_name ?? 'U', 0, 1)) }}
        </a>
    </div>
</header>

<script>
    // The red dot on the bell: unread notifications or unread messages.
    (function () {
        const dot = document.getElementById('header-notification-dot');
        if (!dot) return;

        function refresh() {
            Promise.all([
                fetch(@json(route('api.notifications.unread'))).then(response => response.json()),
                fetch(@json(route('api.chat.unread'))).then(response => response.json()),
            ]).then(([notifications, chat]) => {
                dot.classList.toggle('hidden', (notifications.count || 0) + (chat.count || 0) === 0);
            }).catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', refresh);
        setInterval(refresh, 10000);
    })();
</script>
