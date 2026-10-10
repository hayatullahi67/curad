{{-- The customer's menu. Same look as the pharmacy menu, so the two sides feel like one product. --}}
@php
    $shopper = auth()->user();
    $cartCount = \App\Models\Cart::count();
    $ownsPharmacy = \App\Models\Pharmacy::where('user_id', $shopper->id)->exists();

    // Each link: label, address, icon, the route names that light it up, optional badge id, optional count.
    $groups = [
        'Shop' => [
            ['Overview', route('user.dashboard'), 'fa-house', ['user.dashboard']],
            ['Marketplace', route('user.marketplace'), 'fa-store', ['user.marketplace', 'user.marketplace.*']],
            ['My Cart', route('cart'), 'fa-bag-shopping', ['cart', 'checkout'], null, $cartCount],
            ['My Orders', route('user.orders'), 'fa-box', ['user.orders', 'user.orders.*']],
        ],
        'Updates' => [
            ['Notifications', route('user.notifications'), 'fa-bell', ['user.notifications', 'user.notifications.*'], 'system-notifications-badge'],
            ['Messages', route('user.chat'), 'fa-comment-dots', ['user.chat', 'user.chat.*'], 'messages-badge'],
        ],
        'Saved' => [
            ['Watchlist', route('user.watchlist'), 'fa-bookmark', ['user.watchlist']],
            ['Saved Pharmacies', route('user.pharmacies'), 'fa-heart', ['user.pharmacies']],
        ],
        'Account' => [
            ['Wallet', route('user.history'), 'fa-wallet', ['user.history']],
            ['Profile & Security', route('user.profile'), 'fa-user-gear', ['user.profile']],
            ['Help', route('user.help'), 'fa-circle-question', ['user.help']],
        ],
    ];

    $linkOn = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100';
    $linkOff = 'text-gray-500 hover:bg-gray-50 hover:text-gray-900';
@endphp
<aside id="sidebar" class="hidden lg:flex flex-col w-72 bg-white border-r flex-shrink-0 fixed z-50 h-screen transition-all duration-300">
    <div class="p-6 pb-4 flex items-center justify-between">
        <a href="{{ route('user.dashboard') }}" class="flex min-w-0 items-center space-x-3 group">
            <div class="w-11 h-11 flex-shrink-0 bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-xl flex items-center justify-center text-white shadow-lg shadow-emerald-200 group-hover:scale-105 transition-transform">
                <i class="fas fa-plus"></i>
            </div>
            <div class="min-w-0">
                <p class="font-extrabold text-gray-900 leading-none tracking-tight text-lg">Cura<span class="text-emerald-600">D</span></p>
                <p class="mt-1.5 truncate text-[11px] font-semibold text-emerald-700">My account</p>
            </div>
        </a>
        <button type="button" id="sidebar-close-btn" class="lg:hidden p-2 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition" aria-label="Close menu">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <div class="flex-1 px-4 py-6 overflow-y-auto space-y-7">
        @foreach ($groups as $groupLabel => $links)
            <div>
                <p class="px-4 text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">{{ $groupLabel }}</p>
                <nav class="space-y-1">
                    @foreach ($links as $link)
                        @php [$label, $url, $icon, $names] = $link; $active = request()->routeIs(...$names); @endphp
                        <a href="{{ $url }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 {{ $active ? $linkOn : $linkOff }}" @if ($active) aria-current="page" @endif>
                            <i class="fas {{ $icon }} mr-3.5 w-5 text-center text-base {{ $active ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}"></i>
                            {{ $label }}
                            @if (! empty($link[4]))
                                <span id="{{ $link[4] }}" class="hidden ml-auto h-5 min-w-[1.25rem] px-1 bg-rose-500 text-white text-[10px] font-bold rounded-full items-center justify-center">0</span>
                            @elseif (! empty($link[5]))
                                <span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold text-white">{{ $link[5] > 99 ? '99+' : $link[5] }}</span>
                            @endif
                        </a>
                    @endforeach

                    @if ($loop->last)
                        <form action="{{ route('user.become.pharmacy') }}" method="POST">
                            @csrf
                            <button type="submit" class="w-full group flex items-center px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl transition-all duration-200">
                                <i class="fas fa-shop mr-3.5 w-5 text-center text-base text-gray-400 group-hover:text-gray-600"></i>
                                {{ $ownsPharmacy ? 'Back to my pharmacy' : 'Sell on CuraD' }}
                            </button>
                        </form>
                        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="group flex items-center px-4 py-3 text-sm font-bold text-red-500 hover:bg-red-50 rounded-xl transition-all duration-200">
                            <i class="fas fa-sign-out-alt mr-3.5 w-5 text-center text-base text-red-400 group-hover:text-red-600"></i>
                            Log Out
                        </a>
                    @endif
                </nav>
            </div>
        @endforeach
    </div>

    <div class="p-4 border-t border-gray-100">
        <a href="{{ route('user.profile') }}" class="bg-gray-50 rounded-2xl p-4 flex items-center space-x-3 transition hover:bg-gray-100">
            <div class="w-10 h-10 flex-shrink-0 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-bold shadow-sm shadow-emerald-100">
                {{ strtoupper(mb_substr($shopper->full_name ?? 'U', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-900 truncate">{{ $shopper->full_name }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500 font-medium truncate">{{ $shopper->email }}</p>
            </div>
        </a>
    </div>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
        @csrf
    </form>
</aside>

<script>
    // Unread counts beside Notifications and Messages, refreshed every 10 seconds.
    (function () {
        const badges = [
            ['system-notifications-badge', @json(route('api.notifications.unread'))],
            ['messages-badge', @json(route('api.chat.unread'))],
        ];

        function refresh() {
            badges.forEach(([id, url]) => {
                const badge = document.getElementById(id);
                if (!badge) return;

                fetch(url, { headers: { Accept: 'application/json' } })
                    .then(response => response.json())
                    .then(data => {
                        const count = Number(data.count) || 0;
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.classList.toggle('hidden', count === 0);
                        badge.classList.toggle('flex', count > 0);
                    })
                    .catch(() => {});
            });
        }

        document.addEventListener('DOMContentLoaded', refresh);
        setInterval(refresh, 10000);
    })();
</script>
