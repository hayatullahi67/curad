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
        $isOwner ? ['delete', 'fa-trash-can', 'Delete account'] : null,
    ]);
    $deleteFailed = $errors->getBag('delete')->any();
    $blocked = $isOwner && ($deletion['open_orders'] || $deletion['own_orders']);
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

            @if ($isOwner)
                {{-- ============ Delete account (owner only) ============ --}}
                <section id="delete" data-settings-section class="scroll-mt-24 overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm">
                    <header class="flex items-start gap-3 border-b border-rose-100 bg-rose-50/60 p-4 sm:p-6">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600"><i class="fas fa-triangle-exclamation"></i></span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-slate-900">Delete account</h2>
                            <p class="mt-0.5 text-sm leading-6 text-slate-600">Closes {{ $organization->name }} on CuraD for good. This cannot be undone.</p>
                        </div>
                    </header>

                    <div class="p-4 sm:p-6">
                        <p class="text-sm font-semibold text-slate-900">If you delete your account:</p>
                        <ul class="mt-3 space-y-2.5 text-sm leading-6 text-slate-600">
                            <li class="flex gap-2.5"><i class="fas fa-store mt-1 w-4 flex-shrink-0 text-center text-xs text-rose-500"></i><span>{{ $deletion['branches'] === 1 ? 'Your branch leaves' : 'All ' . $deletion['branches'] . ' of your branches leave' }} the marketplace, with {{ number_format($deletion['products']) }} {{ Str::plural('product', $deletion['products']) }}. Customers can no longer order from you.</span></li>
                            <li class="flex gap-2.5"><i class="fas fa-user-slash mt-1 w-4 flex-shrink-0 text-center text-xs text-rose-500"></i><span>{{ $deletion['staff'] === 0 ? 'No staff accounts are affected.' : ($deletion['staff'] === 1 ? 'Your 1 staff account is switched off and signed out.' : 'Your ' . $deletion['staff'] . ' staff accounts are switched off and signed out.') }}</span></li>
                            <li class="flex gap-2.5"><i class="fas fa-user-xmark mt-1 w-4 flex-shrink-0 text-center text-xs text-rose-500"></i><span>Your sign-in, name, phone and address are removed. You can register again later with the same email.</span></li>
                            <li class="flex gap-2.5"><i class="fas fa-file-invoice mt-1 w-4 flex-shrink-0 text-center text-xs text-slate-400"></i><span>Sales, orders and the activity log are kept, because they are your business’s financial record.</span></li>
                            @if ($deletion['wallet'] > 0)
                                <li class="flex gap-2.5"><i class="fas fa-wallet mt-1 w-4 flex-shrink-0 text-center text-xs text-amber-500"></i><span class="text-amber-800">You still have <span class="font-semibold">₦{{ number_format($deletion['wallet'], 2) }}</span> in your wallet. Contact CuraD support to collect it before you delete the account.</span></li>
                            @endif
                        </ul>

                        @if ($blocked)
                            <div class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm leading-6 text-amber-900">
                                <i class="fas fa-circle-info mt-1 text-amber-600"></i>
                                <div class="min-w-0">
                                    <p class="font-semibold">You cannot delete the account yet.</p>
                                    <ul class="mt-1 space-y-1">
                                        @if ($deletion['open_orders'])
                                            <li>{{ $deletion['open_orders'] }} online {{ Str::plural('order', $deletion['open_orders']) }} {{ $deletion['open_orders'] === 1 ? 'is' : 'are' }} still in progress at your branches. <a href="{{ route('vendor.orders') }}" class="font-semibold underline">Finish or reject {{ $deletion['open_orders'] === 1 ? 'it' : 'them' }}</a> first.</li>
                                        @endif
                                        @if ($deletion['own_orders'])
                                            <li>{{ $deletion['own_orders'] }} {{ Str::plural('order', $deletion['own_orders']) }} you placed as a customer {{ $deletion['own_orders'] === 1 ? 'is' : 'are' }} still on the way.</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>

                    <footer class="flex justify-end border-t border-rose-100 bg-rose-50/40 px-4 py-3 sm:px-6">
                        <button type="button" data-cura-modal-open="delete-account-modal" @disabled($blocked)
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-rose-300 bg-white px-5 text-sm font-semibold text-rose-700 transition hover:bg-rose-600 hover:text-white disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-400 sm:w-auto">
                            <i class="fas fa-trash-can text-xs"></i> Delete my account
                        </button>
                    </footer>
                </section>
            @endif
        </div>
    </div>
</div>

@if ($isOwner && ! $blocked)
    {{-- The last check before deleting: the business name, typed out, and the password. --}}
    <x-modal id="delete-account-modal" title="Delete your account?" max-width="md">
        <form action="{{ route('vendor.account.destroy') }}" method="POST" id="delete-account-form" novalidate>
            @csrf
            @method('DELETE')
            <div class="flex items-start gap-3 rounded-xl bg-rose-50 p-3.5 text-sm leading-6 text-rose-900">
                <i class="fas fa-triangle-exclamation mt-1 text-rose-600"></i>
                <p>This closes <span class="font-semibold">{{ $organization->name }}</span>, takes {{ $deletion['branches'] === 1 ? 'its branch' : 'its ' . $deletion['branches'] . ' branches' }} off the marketplace and signs out {{ $deletion['staff'] ?: 'no' }} staff. It cannot be undone.</p>
            </div>

            <label for="delete-confirm-name" class="{{ $label }} mt-5">Type <span class="select-all rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[13px] text-slate-900">{{ $organization->name }}</span> to confirm</label>
            <input id="delete-confirm-name" type="text" name="confirm_name" value="{{ $deleteFailed ? old('confirm_name') : '' }}" required autocomplete="off" autocapitalize="off" spellcheck="false" data-expected="{{ $organization->name }}"
                class="{{ $field }} {{ $errors->delete->has('confirm_name') ? $fieldBad : 'border-slate-300 focus:border-rose-500 focus:ring-rose-100' }}">
            @if ($errors->delete->has('confirm_name'))<p class="mt-1.5 text-sm text-rose-600">{{ $errors->delete->first('confirm_name') }}</p>@endif

            <label for="delete-password" class="{{ $label }} mt-4">Your password</label>
            <input id="delete-password" type="password" name="current_password" required autocomplete="current-password"
                class="{{ $field }} {{ $errors->delete->has('current_password') ? $fieldBad : 'border-slate-300 focus:border-rose-500 focus:ring-rose-100' }}">
            @if ($errors->delete->has('current_password'))<p class="mt-1.5 text-sm text-rose-600">{{ $errors->delete->first('current_password') }}</p>@endif
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Keep my account</button>
                <button type="submit" form="delete-account-form" id="delete-account-submit" disabled class="h-11 rounded-xl bg-rose-600 px-5 text-sm font-semibold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-rose-300">Delete account for good</button>
            </div>
        </x-slot:footer>
    </x-modal>
@endif
@endsection

@push('scripts')
<script>
    // Delete account: the final button only works once the business name is typed exactly and a password is given.
    (() => {
        const form = document.getElementById('delete-account-form');
        if (!form) return;

        const name = document.getElementById('delete-confirm-name');
        const password = document.getElementById('delete-password');
        const submit = document.getElementById('delete-account-submit');
        const ready = () => { submit.disabled = name.value.trim().toLowerCase() !== name.dataset.expected.trim().toLowerCase() || password.value === ''; };

        [name, password].forEach(input => input.addEventListener('input', ready));
        form.addEventListener('submit', () => { submit.disabled = true; submit.textContent = 'Deleting…'; });
        ready();

        @if ($deleteFailed)
            document.addEventListener('DOMContentLoaded', () => CuraModal.open('delete-account-modal'));
        @endif
    })();

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
