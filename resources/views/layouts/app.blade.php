<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.viewport')
    <title>@yield('title', 'Dashboard') | CuraD</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
    @stack('styles')
</head>

<body class="bg-gray-50 font-sans">
    
    <div class="flex h-screen overflow-hidden">
        
        <!-- Sidebar -->
        @auth
            @if(in_array(auth()->user()->role, ['vendor', 'sales_person']))
                @include('partials.vendor-sidebar')
                <!-- Adjust margin for main content based on sidebar width -->
                <div class="hidden lg:block w-72 flex-shrink-0"></div>
            @else
                @include('partials.user-sidebar')
                 <!-- Adjust margin for main content based on sidebar width -->
                <div class="hidden lg:block w-72 flex-shrink-0"></div>
            @endif
        @else
            @include('partials.user-sidebar')
            <div class="hidden lg:block w-72 flex-shrink-0"></div>
        @endauth

        <!-- Sidebar Backdrop for Mobile -->
        <div id="sidebar-backdrop" class="fixed inset-0 bg-slate-900/50 z-40 backdrop-blur-xs hidden lg:hidden transition-opacity"></div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden bg-slate-50 relative w-full">

            <!-- Header -->
            @include('partials.header')

            <!-- Scrollable Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-50 px-4 sm:px-6 py-8">
                @if (session('success'))
                    <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ session('error') }}</div>
                @endif

                @yield('content')
            </main>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('header-mobile-toggle');
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const closeBtn = document.getElementById('sidebar-close-btn');

            function openSidebar() {
                if (!sidebar) return;
                sidebar.classList.remove('hidden');
                sidebar.classList.add('flex');
                if (backdrop) backdrop.classList.remove('hidden');
            }

            function closeSidebar() {
                if (!sidebar) return;
                sidebar.classList.add('hidden');
                sidebar.classList.remove('flex');
                if (backdrop) backdrop.classList.add('hidden');
            }

            if (toggleBtn && sidebar) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    if (sidebar.classList.contains('hidden')) {
                        openSidebar();
                    } else {
                        closeSidebar();
                    }
                });
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeSidebar);
            }

            // Close when clicking outside of the sidebar on mobile
            document.addEventListener('click', function(e) {
                if (window.innerWidth < 1024 && sidebar && !sidebar.classList.contains('hidden')) {
                    if (!sidebar.contains(e.target) && (!toggleBtn || !toggleBtn.contains(e.target))) {
                        closeSidebar();
                    }
                }
            });

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && window.innerWidth < 1024) {
                    closeSidebar();
                }
            });
        });
    </script>
    @stack('scripts')
</body>

</html>
