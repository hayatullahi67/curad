@php
    $currentUser = auth()->user();
    $isStaff = $currentUser->role === 'sales_person';
    $role = $currentUser->staff_role;
    $pharmacy = \App\Models\Pharmacy::resolveForUser($currentUser);

    $canSell = ! $isStaff || \App\Support\StaffRoles::canSell($role);
    $seesSales = $canSell || \App\Support\StaffRoles::can($role, 'all_sales');
    $seesAllSales = ! $isStaff || \App\Support\StaffRoles::canApproveVoids($role) || \App\Support\StaffRoles::can($role, 'all_sales');
    $canViewOrders = ! $isStaff || \App\Support\StaffRoles::canViewReservations($role);
    $canManageOrders = ! $isStaff || \App\Support\StaffRoles::canManageOrders($role);
    $canStock = ! $isStaff || \App\Support\StaffRoles::can($role, 'inventory');
    $canReports = ! $isStaff || \App\Support\StaffRoles::can($role, 'reports');
    $canActivity = ! $isStaff || \App\Support\StaffRoles::can($role, 'activity');

    $dashboardRoute = $isStaff ? route('sales.staff.dashboard') : route('vendor.dashboard');

    // Each link: label, address, icon, the route names that light it up, whether to show it, optional badge id.
    $operations = [
        ['Dashboard', $dashboardRoute, 'fa-chart-line', [$isStaff ? 'sales.staff.dashboard' : 'vendor.dashboard'], true],
        [$isStaff ? 'Sell' : 'POS Terminal', $isStaff ? route('sales.staff.pos') : route('vendor.pos'), 'fa-cash-register', ['vendor.pos', 'sales.staff.pos'], $canSell],
        [$seesAllSales ? 'Sales & Receipts' : 'My Sales', route('vendor.sales'), 'fa-receipt', ['vendor.sales', 'vendor.sales.*'], $seesSales],
        ['Inventory Listing', route('vendor.inventory'), 'fa-pills', ['vendor.inventory'], $canStock],
        ['Stock', route('vendor.stock.receive'), 'fa-truck', ['vendor.stock.receive'], $canStock],
        ['Online Orders', route('vendor.orders'), 'fa-shopping-cart', ['vendor.orders'], $canViewOrders],
        ['Logistics', route('vendor.dispatch'), 'fa-shipping-fast', ['vendor.dispatch'], $canManageOrders],
        ['Messages', route('vendor.chat'), 'fa-comment-dots', ['vendor.chat', 'vendor.chat.*'], $canManageOrders, 'vendor-messages-badge'],
        ['Notifications', $isStaff ? '#' : route('vendor.notifications'), 'fa-bell', ['vendor.notifications'], ! $isStaff, 'vendor-system-badge'],
    ];

    $management = [
        ['Branches', $isStaff ? '#' : route('vendor.branches'), 'fa-code-branch', ['vendor.branches', 'vendor.branches.*'], ! $isStaff],
        ['Staff Access', $isStaff ? '#' : route('vendor.sales-staff'), 'fa-user-friends', ['vendor.sales-staff', 'vendor.sales-staff.*'], ! $isStaff],
        ['Reports & Insights', route('vendor.reports'), 'fa-chart-pie', ['vendor.reports'], $canReports],
        ['Activity Log', route('vendor.activity'), 'fa-clipboard-list', ['vendor.activity'], $canActivity],
        ['Settings', route('vendor.profile'), 'fa-gear', ['vendor.profile'], true],
    ];

    $linkOn = 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-100';
    $linkOff = 'text-gray-500 hover:bg-gray-50 hover:text-gray-900';
@endphp
<aside id="sidebar" class="hidden lg:flex flex-col w-72 bg-white border-r flex-shrink-0 fixed z-50 h-screen transition-all duration-300">
    <!-- Brand / Logo Area -->
    <div class="p-6 pb-4 flex items-center justify-between">
        <a href="{{ $dashboardRoute }}" class="flex min-w-0 items-center space-x-3 group">
            <div class="w-11 h-11 flex-shrink-0 bg-gradient-to-br from-emerald-600 to-emerald-700 rounded-xl flex items-center justify-center text-white shadow-lg shadow-emerald-200 group-hover:scale-105 transition-transform">
                <i class="fas fa-plus"></i>
            </div>
            <div class="min-w-0">
                <p class="font-extrabold text-gray-900 leading-none tracking-tight text-lg">Cura<span class="text-emerald-600">D</span></p>
                <p class="mt-1.5 truncate text-[11px] font-semibold text-emerald-700" title="{{ $pharmacy->name ?? '' }}">{{ $pharmacy->name ?? ($isStaff ? \App\Support\StaffRoles::label($role) : 'Set up your pharmacy') }}</p>
            </div>
        </a>
        <button type="button" id="sidebar-close-btn" class="lg:hidden p-2 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>

    <!-- Navigation Area -->
    <div class="flex-1 px-4 py-6 overflow-y-auto space-y-8">
        @foreach (['Operations' => $operations, 'Management' => $management] as $groupLabel => $links)
            @php $visible = array_filter($links, fn ($link) => $link[4]); @endphp
            <div>
                <p class="px-4 text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-4">{{ $groupLabel }}</p>
                <nav class="space-y-1.5">
                    @foreach ($visible as $link)
                        @php [$label, $url, $icon, $names] = $link; $active = request()->routeIs(...$names); @endphp
                        <a href="{{ $url }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-xl transition-all duration-200 {{ $active ? $linkOn : $linkOff }}" @if ($active) aria-current="page" @endif>
                            <i class="fas {{ $icon }} mr-3.5 w-5 text-lg {{ $active ? 'text-emerald-600' : 'text-gray-400 group-hover:text-gray-600' }}"></i>
                            {{ $label }}
                            @isset($link[5])
                                <span id="{{ $link[5] }}" class="hidden ml-auto w-5 h-5 bg-red-500 text-white text-[10px] font-black rounded-full flex items-center justify-center">0</span>
                            @endisset
                        </a>
                    @endforeach

                    @if ($groupLabel === 'Management')
                        @unless ($isStaff)
                            <form action="{{ route('vendor.become.patient') }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full group flex items-center px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-gray-50 hover:text-gray-900 rounded-xl transition-all duration-200">
                                    <i class="fas fa-bag-shopping mr-3.5 w-5 text-lg text-gray-400 group-hover:text-gray-600"></i>
                                    Shop as a customer
                                </button>
                            </form>
                        @else
                            @if (session('impersonator_id'))
                                <form action="{{ route('sales.staff.return-to-admin') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="w-full group flex items-center px-4 py-3 text-sm font-bold text-emerald-700 hover:bg-emerald-50 rounded-xl transition-all duration-200">
                                        <i class="fas fa-user-shield mr-3.5 w-5 text-lg text-emerald-600"></i>
                                        Return to Admin
                                    </button>
                                </form>
                            @endif
                        @endunless

                        <a href="{{ $isStaff ? route('sales.staff.logout') : route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="group flex items-center px-4 py-3 text-sm font-bold text-red-500 hover:bg-red-50 rounded-xl transition-all duration-200">
                            <i class="fas fa-sign-out-alt mr-3.5 w-5 text-lg text-red-400 group-hover:text-red-600"></i>
                            Log Out
                        </a>
                    @endif
                </nav>
            </div>
        @endforeach
    </div>

    <!-- Who is signed in -->
    <div class="p-4 border-t border-gray-100">
        <div class="bg-gray-50 rounded-2xl p-4 flex items-center space-x-3">
            <div class="w-10 h-10 flex-shrink-0 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-bold shadow-sm shadow-emerald-100">
                {{ strtoupper(mb_substr($currentUser->full_name ?? 'U', 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-900 truncate">{{ $currentUser->full_name }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500 font-medium truncate">{{ $isStaff ? \App\Support\StaffRoles::label($role) : 'Owner' }}</p>
            </div>
        </div>
    </div>

    <form id="logout-form" action="{{ $isStaff ? route('sales.staff.logout') : route('logout') }}" method="POST" class="hidden">
        @csrf
    </form>
</aside>

@if (! $isStaff || $canManageOrders)
<script>
    // Poll for unread messages count for vendor
    function checkVendorUnreadMessages() {
        const badge = document.getElementById('vendor-messages-badge');
        if (!badge) return;

        fetch('{{ route("api.chat.unread") }}')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.classList.remove('flex');
                    badge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error checking vendor messages:', error));
    }

    // Poll for System Notifications
    function checkVendorSystemNotifications() {
        const badge = document.getElementById('vendor-system-badge');
        if (!badge) return;

        fetch('{{ route("api.notifications.unread") }}')
            .then(response => response.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : data.count;
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.classList.remove('flex');
                    badge.style.display = 'none';
                }
            })
            .catch(error => console.error('Error checking vendor notifications:', error));
    }

    // Check immediately and then every 8 seconds
    document.addEventListener('DOMContentLoaded', () => {
        checkVendorUnreadMessages();
        checkVendorSystemNotifications();
    });
    setInterval(() => {
        checkVendorUnreadMessages();
        checkVendorSystemNotifications();
    }, 8000);
</script>
@endif
