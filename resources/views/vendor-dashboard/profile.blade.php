@extends('layouts.app')

@section('title', 'Settings')
@section('header_title', 'Settings')

@php
    $field = 'h-11 w-full rounded-xl border bg-white px-3 text-[15px] text-slate-900 placeholder:text-slate-400 transition focus:outline-none focus:ring-4';
    $fieldOk = 'border-slate-300 focus:border-emerald-500 focus:ring-emerald-100';
    $fieldBad = 'border-rose-400 focus:border-rose-500 focus:ring-rose-100';
    $label = 'mb-1.5 block text-sm font-semibold text-slate-800';
    $save = 'inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-wait disabled:opacity-70 sm:w-auto';

    // Two forms share the field name "name", so a form only gets its typed-in
    // values back when it is the one that failed.
    $value = fn (string $bag, string $key, $saved) => $errors->getBag($bag)->any() ? old($key, $saved) : $saved;
    $style = fn (string $bag, string $key) => $field . ' ' . ($errors->getBag($bag)->has($key) ? $fieldBad : $fieldOk);

    $sections = array_filter([
        $isOwner ? ['organization', 'fa-building', 'Organization'] : null,
        $isOwner ? ['branches', 'fa-store', 'Branches'] : null,
        ['account', 'fa-user', 'My account'],
        ['password', 'fa-lock', 'Password'],
    ]);
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1080px]">
    <p class="text-sm text-slate-500">
        {{ $isOwner ? 'Your business details, your branches and your own sign-in.' : 'Your own details and password.' }}
    </p>

    <div class="mt-5 grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-8">

        {{-- ============ Section list: a row of chips on phones, a side list on large screens ============ --}}
        <nav aria-label="Settings sections" class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0 lg:sticky lg:top-24 lg:self-start lg:overflow-visible">
            <ul class="flex gap-2 lg:flex-col lg:gap-1">
                @foreach ($sections as [$id, $icon, $name])
                    <li class="flex-shrink-0">
                        <a href="#{{ $id }}" data-settings-link="{{ $id }}"
                            class="flex items-center gap-2.5 whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900 lg:rounded-xl lg:border-transparent lg:bg-transparent lg:px-3 lg:py-2.5 lg:hover:border-transparent lg:hover:bg-slate-100">
                            <i class="fas {{ $icon }} w-4 text-center text-xs text-slate-400"></i>{{ $name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="min-w-0 space-y-5">

            @if ($isOwner)
                {{-- ============ Organization ============ --}}
                <section id="organization" data-settings-section class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-building"></i></span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-slate-900">Organization</h2>
                            <p class="mt-0.5 text-sm leading-6 text-slate-500">The business as a whole. Every branch belongs to it.</p>
                        </div>
                    </header>

                    <form action="{{ route('vendor.organization.update') }}" method="POST" data-settings-form novalidate>
                        @csrf
                        @method('PATCH')
                        <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-6">
                            <div class="sm:col-span-2">
                                <label for="org-name" class="{{ $label }}">Organization name</label>
                                <input id="org-name" type="text" name="name" value="{{ $value('organization', 'name', $organization->name) }}" required maxlength="255" autocomplete="organization" class="{{ $style('organization', 'name') }}">
                                @if ($errors->organization->has('name'))
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $errors->organization->first('name') }}</p>
                                @else
                                    <p class="mt-1.5 text-[13px] leading-5 text-slate-500">Customers see it in front of the branch name, for example “{{ $organization->name }} · Ikeja Branch”.</p>
                                @endif
                            </div>

                            <div>
                                <label for="org-phone" class="{{ $label }}">Business phone</label>
                                <input id="org-phone" type="tel" inputmode="tel" name="phone" value="{{ $value('organization', 'phone', $organization->phone) }}" required maxlength="20" autocomplete="tel" class="{{ $style('organization', 'phone') }}">
                                @if ($errors->organization->has('phone'))
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $errors->organization->first('phone') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="org-email" class="{{ $label }}">Business email</label>
                                <input id="org-email" type="email" inputmode="email" name="email" value="{{ $value('organization', 'email', $organization->email) }}" required maxlength="255" autocomplete="email" class="{{ $style('organization', 'email') }}">
                                @if ($errors->organization->has('email'))
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $errors->organization->first('email') }}</p>
                                @else
                                    <p class="mt-1.5 text-[13px] leading-5 text-slate-500">It can be different from the email you sign in with.</p>
                                @endif
                            </div>
                        </div>
                        <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                            <button type="submit" class="{{ $save }}">Save organization</button>
                        </footer>
                    </form>
                </section>

                {{-- ============ Branches ============ --}}
                <section id="branches" data-settings-section class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex flex-wrap items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-store"></i></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-slate-900">Branches <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $branches->count() }}</span></h2>
                            <p class="mt-0.5 text-sm leading-6 text-slate-500">Each branch keeps its own address, phone, opening hours and map location. Open a branch to change them.</p>
                        </div>
                        <a href="{{ route('vendor.branches') }}" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 sm:w-auto">
                            <i class="fas fa-plus text-xs text-slate-400"></i> Add branch
                        </a>
                    </header>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($branches as $item)
                            <li>
                                <a href="{{ route('vendor.branches.show', $item) }}" class="group flex items-start gap-3 p-4 transition hover:bg-slate-50 sm:items-center sm:px-6">
                                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $item->is_main ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' }}"><i class="fas fa-store text-sm"></i></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="break-words text-sm font-bold text-slate-900 group-hover:text-emerald-700">{{ $item->name }}</span>
                                            @if ($item->is_main)
                                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Main branch</span>
                                            @endif
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $item->is_active ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $item->is_active ? 'Taking online orders' : 'Offline on marketplace' }}
                                            </span>
                                        </p>
                                        <p class="mt-1 break-words text-[13px] leading-5 text-slate-500">{{ $item->fullAddress() ?: 'No address yet' }}</p>
                                        <p class="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[13px] text-slate-500">
                                            @if ($item->phone)<span><i class="fas fa-phone mr-1.5 text-[11px] text-slate-400"></i>{{ $item->phone }}</span>@endif
                                            @if ($item->operating_hours)<span><i class="fas fa-clock mr-1.5 text-[11px] text-slate-400"></i>{{ $item->operating_hours }}</span>@endif
                                            @unless ($item->latitude && $item->longitude)<span class="font-medium text-amber-700"><i class="fas fa-location-dot mr-1.5 text-[11px]"></i>No map location yet</span>@endunless
                                        </p>
                                    </div>
                                    <span class="hidden flex-shrink-0 items-center gap-1.5 text-sm font-semibold text-slate-500 group-hover:text-emerald-700 sm:inline-flex">Open <i class="fas fa-chevron-right text-[10px]"></i></span>
                                    <i class="fas fa-chevron-right mt-3 flex-shrink-0 text-xs text-slate-300 sm:hidden"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- ============ My account ============ --}}
            <section id="account" data-settings-section class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-base font-bold text-white">{{ strtoupper(mb_substr($user->full_name ?: 'U', 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-bold text-slate-900">My account</h2>
                        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                            <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $roleLabel }}</span>
                            @if ($branch)
                                <span class="break-words">{{ $isOwner ? 'All branches' : $branch->name }}</span>
                            @endif
                        </p>
                    </div>
                </header>

                <form action="{{ route('vendor.profile.update') }}" method="POST" data-settings-form novalidate>
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-5 p-4 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="account-name" class="{{ $label }}">Full name</label>
                            <input id="account-name" type="text" name="name" value="{{ $value('account', 'name', $user->full_name) }}" required maxlength="255" autocomplete="name" class="{{ $style('account', 'name') }}">
                            @if ($errors->account->has('name'))
                                <p class="mt-1.5 text-sm text-rose-600">{{ $errors->account->first('name') }}</p>
                            @endif
                        </div>

                        <div>
                            <label for="account-phone" class="{{ $label }}">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                            <input id="account-phone" type="tel" inputmode="tel" name="phone" value="{{ $value('account', 'phone', $user->phone) }}" maxlength="20" autocomplete="tel" class="{{ $style('account', 'phone') }}">
                            @if ($errors->account->has('phone'))
                                <p class="mt-1.5 text-sm text-rose-600">{{ $errors->account->first('phone') }}</p>
                            @endif
                        </div>

                        <div class="sm:col-span-2">
                            <label for="account-email" class="{{ $label }}">Sign-in email</label>
                            @if ($isOwner)
                                <input id="account-email" type="email" inputmode="email" name="email" value="{{ $value('account', 'email', $user->email) }}" required maxlength="255" autocomplete="username" class="{{ $style('account', 'email') }}">
                                @if ($errors->account->has('email'))
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $errors->account->first('email') }}</p>
                                @else
                                    <p class="mt-1.5 text-[13px] leading-5 text-slate-500">You sign in with this email. If you change it, use the new one next time.</p>
                                @endif
                            @else
                                <input id="account-email" type="email" value="{{ $user->email }}" readonly aria-describedby="account-email-help" class="{{ $field }} cursor-not-allowed border-slate-200 bg-slate-50 text-slate-500 focus:ring-0">
                                <p id="account-email-help" class="mt-1.5 text-[13px] leading-5 text-slate-500"><i class="fas fa-lock mr-1 text-[11px]"></i>Only the pharmacy owner can change the email you sign in with.</p>
                            @endif
                        </div>
                    </div>
                    <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $save }}">Save my details</button>
                    </footer>
                </form>
            </section>

            {{-- ============ Password ============ --}}
            <section id="password" data-settings-section class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-start gap-3 border-b border-slate-100 p-4 sm:p-6">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fas fa-lock"></i></span>
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-slate-900">Password</h2>
                        <p class="mt-0.5 text-sm leading-6 text-slate-500">Use at least 8 characters. Every password change is written to the Activity Log.</p>
                    </div>
                </header>

                <form action="{{ route('vendor.profile.password') }}" method="POST" data-settings-form novalidate>
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
                                    <button type="button" data-show-password="pw-{{ $name }}" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition hover:text-slate-700">
                                        <i class="fas fa-eye text-sm"></i>
                                    </button>
                                </div>
                                @if ($errors->password->has($name))
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $errors->password->first($name) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
                        <button type="submit" class="{{ $save }}">Change password</button>
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
        // One click is enough: the button waits while the form is sent.
        document.querySelectorAll('[data-settings-form]').forEach(form => {
            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Saving…';
            });
        });

        document.querySelectorAll('[data-show-password]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.showPassword);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.querySelector('i').className = 'fas text-sm ' + (show ? 'fa-eye-slash' : 'fa-eye');
            });
        });

        // Light up the section being read in the section list.
        const links = [...document.querySelectorAll('[data-settings-link]')];
        const on = ['!border-emerald-200', '!bg-emerald-50', '!text-emerald-700'];
        const mark = id => links.forEach(link => {
            const active = link.dataset.settingsLink === id;
            on.forEach(name => link.classList.toggle(name, active));
            active ? link.setAttribute('aria-current', 'true') : link.removeAttribute('aria-current');
        });

        const sections = [...document.querySelectorAll('[data-settings-section]')];
        const watcher = new IntersectionObserver(entries => {
            const seen = entries.filter(entry => entry.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
            if (seen) mark(seen.target.id);
        }, { rootMargin: '-110px 0px -55% 0px' });
        sections.forEach(section => watcher.observe(section));
        mark((location.hash || '').slice(1) || sections[0]?.id);

        // A form that failed is the one to look at.
        const failed = document.querySelector('[data-settings-section] .text-rose-600');
        if (failed) failed.closest('[data-settings-section]').scrollIntoView();
    })();
</script>
@endpush
