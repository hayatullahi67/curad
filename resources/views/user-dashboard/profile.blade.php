@extends('layouts.app')

@section('title', 'Profile & security')
@section('header_title', 'Profile & Security')

@php
    $field = 'h-11 w-full rounded-xl border bg-white px-3 text-[15px] text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-4';
    $fieldOk = 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-100';
    $fieldBad = 'border-rose-400 focus:border-rose-500 focus:ring-rose-100';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
    $save = 'inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-70 sm:w-auto';
    $card = 'scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm';

    $value = fn (string $key, $saved) => $errors->getBag('details')->any() ? old($key, $saved) : $saved;
    $style = fn (string $bag, string $key) => $field . ' ' . ($errors->getBag($bag)->has($key) ? $fieldBad : $fieldOk);

    $sections = [['details', 'fa-user', 'My details'], ['password', 'fa-lock', 'Password'], ['pin', 'fa-key', 'Wallet PIN']];
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1080px]">
    <p class="text-sm text-slate-500">Your details, your password and the PIN you pay with.</p>

    <div class="mt-5 grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-8">

        <nav aria-label="Sections" class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0 lg:sticky lg:top-0 lg:self-start lg:overflow-visible [&::-webkit-scrollbar]:hidden">
            <ul class="flex gap-2 lg:flex-col lg:gap-1">
                @foreach ($sections as [$id, $icon, $name])
                    <li class="flex-shrink-0">
                        <a href="#{{ $id }}" data-section-link="{{ $id }}"
                            class="flex items-center gap-2.5 whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900 lg:rounded-xl lg:border-transparent lg:bg-transparent lg:px-3 lg:py-2.5 lg:hover:border-transparent lg:hover:bg-slate-100">
                            <i class="fas {{ $icon }} w-4 text-center text-xs text-slate-400"></i>{{ $name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 space-y-5">

            {{-- ============ My details ============ --}}
            <section id="details" data-section class="{{ $card }}">
                <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-base font-bold text-white">{{ strtoupper(mb_substr($user->full_name ?: 'U', 0, 1)) }}</span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900">My details</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Customer since {{ $user->created_at?->format('F Y') }}</p>
                    </div>
                </header>

                <form action="{{ route('user.profile.update') }}" method="POST" data-busy-form novalidate>
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="p-name" class="{{ $label }}">Full name</label>
                            <input id="p-name" type="text" name="name" value="{{ $value('name', $user->full_name) }}" required maxlength="255" autocomplete="name" class="{{ $style('details', 'name') }}">
                            @if ($errors->details->has('name'))<p class="mt-1.5 text-sm text-rose-600">{{ $errors->details->first('name') }}</p>@endif
                        </div>
                        <div>
                            <label for="p-phone" class="{{ $label }}">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="p-phone" type="tel" inputmode="tel" name="phone" value="{{ $value('phone', $user->phone) }}" maxlength="20" autocomplete="tel" class="{{ $style('details', 'phone') }}">
                            @if ($errors->details->has('phone'))
                                <p class="mt-1.5 text-sm text-rose-600">{{ $errors->details->first('phone') }}</p>
                            @else
                                <p class="mt-1.5 text-[13px] leading-5 text-slate-500">Pharmacies and riders call this number about your orders.</p>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <label for="p-email" class="{{ $label }}">Sign-in email</label>
                            <input id="p-email" type="email" inputmode="email" name="email" value="{{ $value('email', $user->email) }}" required maxlength="255" autocomplete="username" class="{{ $style('details', 'email') }}">
                            @if ($errors->details->has('email'))
                                <p class="mt-1.5 text-sm text-rose-600">{{ $errors->details->first('email') }}</p>
                            @else
                                <p class="mt-1.5 text-[13px] leading-5 text-slate-500">You sign in with this email. If you change it, use the new one next time.</p>
                            @endif
                        </div>
                    </div>
                    <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $save }}">Save my details</button>
                    </footer>
                </form>
            </section>

            {{-- ============ Password ============ --}}
            <section id="password" data-section class="{{ $card }}">
                <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fas fa-lock"></i></span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900">Password</h2>
                        <p class="mt-0.5 text-sm leading-6 text-slate-500">Use at least 8 characters.</p>
                    </div>
                </header>

                <form action="{{ route('user.profile.password') }}" method="POST" data-busy-form novalidate>
                    @csrf
                    @method('PUT')
                    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-6">
                        @foreach ([
                            ['current_password', 'Current password', 'current-password', 'sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]'],
                            ['password', 'New password', 'new-password', ''],
                            ['password_confirmation', 'Type the new password again', 'new-password', ''],
                        ] as [$name, $text, $autocomplete, $span])
                            <div class="{{ $span }}">
                                <label for="pw-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                                <div class="relative">
                                    <input id="pw-{{ $name }}" type="password" name="{{ $name }}" required autocomplete="{{ $autocomplete }}" class="{{ $style('password', $name) }} pr-11">
                                    <button type="button" data-show-secret="pw-{{ $name }}" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700"><i class="fas fa-eye text-sm"></i></button>
                                </div>
                                @if ($errors->password->has($name))<p class="mt-1.5 text-sm text-rose-600">{{ $errors->password->first($name) }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                    <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $save }}">Change password</button>
                    </footer>
                </form>
            </section>

            {{-- ============ Wallet PIN ============ --}}
            <section id="pin" data-section class="{{ $card }}">
                <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fas fa-key"></i></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="flex flex-wrap items-center gap-2 text-base font-bold text-slate-900">Wallet PIN
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $hasPin ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $hasPin ? 'Set' : 'Not set yet' }}</span>
                        </h2>
                        <p class="mt-0.5 text-sm leading-6 text-slate-500">A 4-digit PIN you type at checkout when you pay from your wallet.</p>
                    </div>
                </header>

                <form action="{{ route('user.profile.pin') }}" method="POST" data-busy-form novalidate>
                    @csrf
                    @method('PUT')
                    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-6">
                        @foreach ([
                            ['transaction_pin', $hasPin ? 'New PIN' : 'Choose a PIN'],
                            ['transaction_pin_confirmation', 'Type the PIN again'],
                        ] as [$name, $text])
                            <div>
                                <label for="pin-{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                                <input id="pin-{{ $name }}" type="password" name="{{ $name }}" required inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" placeholder="••••" class="{{ $style('pin', $name) }} text-center font-mono text-lg tracking-[0.5em]">
                                @if ($errors->pin->has($name))<p class="mt-1.5 text-sm text-rose-600">{{ $errors->pin->first($name) }}</p>@endif
                            </div>
                        @endforeach
                        <div class="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]">
                            <label for="pin-current_password" class="{{ $label }}">Your account password</label>
                            <div class="relative">
                                <input id="pin-current_password" type="password" name="current_password" required autocomplete="current-password" class="{{ $style('pin', 'current_password') }} pr-11">
                                <button type="button" data-show-secret="pin-current_password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700"><i class="fas fa-eye text-sm"></i></button>
                            </div>
                            @if ($errors->pin->has('current_password'))
                                <p class="mt-1.5 text-sm text-rose-600">{{ $errors->pin->first('current_password') }}</p>
                            @else
                                <p class="mt-1.5 text-[13px] leading-5 text-slate-500">So nobody else can change your PIN.</p>
                            @endif
                        </div>
                    </div>
                    <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $save }}">{{ $hasPin ? 'Change PIN' : 'Set PIN' }}</button>
                    </footer>
                </form>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        document.querySelectorAll('[data-busy-form]').forEach(form => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving…';
            });
        });

        document.querySelectorAll('[data-show-secret]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.showSecret);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.querySelector('i').className = 'fas text-sm ' + (show ? 'fa-eye-slash' : 'fa-eye');
            });
        });

        // Light up the section being read.
        const links = [...document.querySelectorAll('[data-section-link]')];
        const on = ['!border-emerald-200', '!bg-emerald-50', '!text-emerald-700'];
        const mark = id => links.forEach(link => on.forEach(name => link.classList.toggle(name, link.dataset.sectionLink === id)));
        const sections = [...document.querySelectorAll('[data-section]')];
        const watcher = new IntersectionObserver(entries => {
            const seen = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
            if (seen) mark(seen.target.id);
        }, { rootMargin: '-110px 0px -55% 0px' });
        sections.forEach(section => watcher.observe(section));
        mark((location.hash || '').slice(1) || sections[0]?.id);

        // A form that failed is the one to look at.
        document.querySelector('[data-section] .text-rose-600')?.closest('[data-section]').scrollIntoView();
    })();
</script>
@endpush
