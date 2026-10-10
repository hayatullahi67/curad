<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    @include('partials.viewport')
    <title>@yield('title', 'Sign in') | CuraD</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        .auth-input { width: 100%; border-radius: 0.75rem; border: 1px solid #cbd5e1; background: #fff; padding: 0.75rem 0.875rem; font-size: 15px; color: #0f172a; transition: border-color .15s, box-shadow .15s; }
        .auth-input::placeholder { color: #94a3b8; }
        .auth-input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 4px #d1fae5; }
        .auth-input.has-error { border-color: #f43f5e; }
        .auth-input.has-error:focus { box-shadow: 0 0 0 4px #ffe4e6; }
        a:focus-visible, button:focus-visible { outline: 2px solid #059669; outline-offset: 2px; }
    </style>
</head>

<body class="bg-white text-slate-900">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]" style="min-height: calc(var(--vh, 1vh) * 100)">
        {{-- Form side --}}
        <div class="flex flex-col px-5 py-6 sm:px-10 lg:px-16">
            <div class="flex items-center justify-between">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5" aria-label="CuraD home">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-600/30"><i class="fas fa-plus text-sm"></i></span>
                    <span class="text-xl font-extrabold tracking-tight">Cura<span class="text-emerald-600">D</span></span>
                </a>
                <a href="{{ route('marketplace') }}" class="text-sm font-semibold text-slate-600 hover:text-emerald-700"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Back to shop</a>
            </div>

            <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-10">
                @yield('content')
            </div>

            <p class="text-center text-xs text-slate-400">&copy; {{ date('Y') }} CuraD</p>
        </div>

        {{-- Brand side (large screens) --}}
        <aside class="relative hidden overflow-hidden bg-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-14">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-emerald-500/20 blur-3xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-emerald-600/20 blur-3xl" aria-hidden="true"></div>

            <div class="relative">
                <p class="text-sm font-semibold text-emerald-400">@yield('aside_eyebrow', 'CuraD')</p>
                <h2 class="mt-3 max-w-md text-3xl font-bold leading-tight tracking-tight text-white xl:text-4xl">@yield('aside_title', 'The pharmacy near you, on one page.')</h2>
            </div>

            <ul class="relative mt-10 space-y-5">
                @foreach ([
                    ['fa-magnifying-glass', 'Search once', 'Find a medicine or health product across every CuraD pharmacy.'],
                    ['fa-tags', 'Compare prices', 'See who has it in stock and what each pharmacy charges.'],
                    ['fa-cash-register', 'Run your pharmacy', 'POS, stock, staff and reports in the same system.'],
                ] as [$icon, $title, $text])
                    <li class="flex gap-4">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400"><i class="fas {{ $icon }}"></i></span>
                        <span>
                            <span class="block font-semibold text-white">{{ $title }}</span>
                            <span class="mt-0.5 block text-sm leading-6 text-slate-400">{{ $text }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <p class="relative mt-10 flex items-center gap-2 text-sm text-slate-400"><i class="fas fa-lock text-emerald-400"></i>Your password is stored encrypted. Nobody at CuraD can read it.</p>
        </aside>
    </div>

    <script>
        // "Show" buttons beside password boxes.
        document.querySelectorAll('[data-toggle-password]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
            });
        });

        // One click is enough: the button waits while the form is sent.
        document.querySelectorAll('form[data-auth-form]').forEach(form => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                if (!button || button.disabled) return;
                button.disabled = true;
                button.classList.add('opacity-70', 'cursor-wait');
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i>' + (button.dataset.busy || 'Please wait…');
            });
        });
    </script>
    @stack('scripts')
</body>

</html>
