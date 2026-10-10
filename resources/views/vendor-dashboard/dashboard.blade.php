@extends('layouts.app')

@section('title', 'Dashboard')
@section('header_title', 'Dashboard')

@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = $user->first_name ?: explode(' ', (string) $user->full_name)[0];
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);

    // Colours for the small icon tile on each card and task.
    $tones = [
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-500',
    ];
    $columns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'][max(1, count($cards))];
    $mainAction = collect($actions)->firstWhere(4, true);

    // The owner and the branch's manager look after where the branch is on the map.
    $canSetLocation = $user->role === 'vendor' || $user->staff_role === 'branch_manager';
    $hasLocation = filled($branch->latitude) && filled($branch->longitude);
    $field = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
    $locationFailed = $errors->getBag('location')->any();
    $typed = fn (string $name) => $locationFailed ? old($name, $branch->{$name}) : $branch->{$name};
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Who, where, and the one thing to do next ============ --}}
    <section class="relative overflow-hidden rounded-2xl bg-slate-900 px-5 py-6 text-white shadow-sm sm:px-7 sm:py-7">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-emerald-500/25 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-28 left-1/3 h-56 w-56 rounded-full bg-emerald-400/10 blur-3xl" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-emerald-300">{{ now()->format('l, F j') }}</p>
                <h1 class="mt-1.5 break-words text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm text-slate-300">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white"><i class="fas fa-id-badge text-[10px] text-emerald-300"></i>{{ $roleLabel }}</span>
                    <span class="inline-flex min-w-0 items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-xs font-semibold text-white"><i class="fas fa-store text-[10px] text-emerald-300"></i><span class="truncate">{{ $branch->name }}</span></span>
                    @unless ($branch->is_active)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 px-2.5 py-1 text-xs font-semibold text-amber-200"><i class="fas fa-eye-slash text-[10px]"></i>Offline on the marketplace</span>
                    @endunless
                </p>
            </div>

            @if ($mainAction)
                <a href="{{ $mainAction[3] }}" class="inline-flex h-12 flex-shrink-0 items-center justify-center gap-2.5 rounded-xl bg-emerald-500 px-6 text-[15px] font-semibold text-white shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-400">
                    <i class="fas {{ $mainAction[2] }} text-sm"></i> {{ $mainAction[0] }}
                </a>
            @endif
        </div>

    </section>

    {{-- ============ Where this branch is on the map ============ --}}
    @if ($canSetLocation && ! $hasLocation)
        <section class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-center sm:p-5">
            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><i class="fas fa-location-dot"></i></span>
            <div class="min-w-0 flex-1">
                <h2 class="text-[15px] font-bold text-amber-950">{{ $branch->name }} is not on the map yet</h2>
                <p class="mt-1 text-sm leading-6 text-amber-900">Customers cannot get directions to it, and delivery fees use a flat rate. Use your location if you are in the shop now, or type the address.</p>
            </div>
            <div class="flex w-full flex-shrink-0 flex-col gap-2 sm:w-auto sm:flex-row">
                <button type="button" data-cura-modal-open="location-modal" data-type-address class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-amber-300 bg-white px-4 text-sm font-semibold text-slate-800 transition hover:border-amber-400">
                    <i class="fas fa-keyboard text-xs text-slate-500"></i> Type the address
                </button>
                <button type="button" data-cura-modal-open="location-modal" data-locate-now class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                    <i class="fas fa-location-crosshairs text-xs"></i> Use my location
                </button>
            </div>
        </section>
    @elseif ($canSetLocation)
        <section class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm sm:px-5">
            <span class="flex min-w-0 flex-1 items-center gap-2.5 text-slate-600">
                <i class="fas fa-location-dot flex-shrink-0 text-emerald-600"></i>
                <span class="min-w-0 break-words"><span class="font-semibold text-slate-900">On the map:</span> {{ $branch->fullAddress() ?: 'pinned' }}</span>
            </span>
            <a href="https://www.google.com/maps/search/?api=1&query={{ $branch->latitude }},{{ $branch->longitude }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-arrow-up-right-from-square text-[11px]"></i> View on Google Maps</a>
            <button type="button" data-cura-modal-open="location-modal" class="inline-flex items-center gap-1.5 font-semibold text-slate-600 hover:text-slate-900"><i class="fas fa-pen text-[11px]"></i> Change</button>
        </section>
    @endif

    {{-- ============ The numbers that matter for this role ============ --}}
    @if ($cards)
        <section class="grid grid-cols-2 gap-3 {{ $columns }}">
            @foreach ($cards as [$label, $value, $hint, $icon, $tone, $url])
                @php $dark = $tone === 'dark'; @endphp
                <a href="{{ $url }}" class="group relative overflow-hidden rounded-2xl p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:p-5 {{ $dark ? 'bg-emerald-600 text-white' : 'border border-slate-200 bg-white hover:border-emerald-300' }} {{ $loop->first && count($cards) % 2 === 1 ? 'col-span-2 lg:col-span-1' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-[13px] font-medium {{ $dark ? 'text-emerald-50' : 'text-slate-500' }}">{{ $label }}</p>
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $dark ? 'bg-white/15 text-white' : $tones[$tone] }}"><i class="fas {{ $icon }} text-sm"></i></span>
                    </div>
                    <p class="mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] sm:text-[28px] {{ $dark ? 'text-white' : 'text-slate-900' }}" title="{{ $value }}">{{ $value }}</p>
                    <p class="mt-2 truncate text-xs {{ $dark ? 'text-emerald-100' : 'text-slate-500' }}">{{ $hint }}</p>
                </a>
            @endforeach
        </section>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] xl:items-start">
        <div class="space-y-5">

            {{-- ============ Needs your attention ============ --}}
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 p-4 pb-3 sm:px-6 sm:pt-6">
                    <h2 class="text-base font-bold text-slate-900">Needs your attention</h2>
                    @if ($tasks)
                        <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ count($tasks) }}</span>
                    @endif
                </div>

                @if ($tasks)
                    <ul class="divide-y divide-slate-100">
                        @foreach ($tasks as [$icon, $tone, $title, $detail, $url])
                            <li>
                                <a href="{{ $url }}" class="group flex items-center gap-3.5 px-4 py-3.5 transition hover:bg-slate-50 sm:px-6">
                                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $tones[$tone] }}"><i class="fas {{ $icon }} text-sm"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $title }}</span>
                                        <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $detail }}</span>
                                    </span>
                                    <i class="fas fa-chevron-right flex-shrink-0 text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="mx-4 mb-4 flex items-center gap-3.5 rounded-xl bg-emerald-50 px-4 py-4 sm:mx-6 sm:mb-6">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white text-emerald-600 shadow-sm"><i class="fas fa-check"></i></span>
                        <div>
                            <p class="text-sm font-semibold text-emerald-900">You are all caught up</p>
                            <p class="mt-0.5 text-xs text-emerald-800">Nothing in {{ $branch->name }} is waiting for you right now.</p>
                        </div>
                    </div>
                @endif
            </section>

            {{-- ============ Sales, day by day ============ --}}
            @if ($chart)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900">{{ $chart['title'] }}</h2>
                        <p class="text-sm text-slate-500"><span class="font-bold text-slate-900">{{ $money($chart['total']) }}</span> in all</p>
                    </div>

                    @if ($chart['total'] > 0)
                        <div class="mt-5 flex h-40 items-end gap-2 sm:gap-3" role="img" aria-label="Bar chart of daily sales for the last 7 days">
                            @foreach ($chart['days'] as $day)
                                <div class="group relative flex h-full min-w-0 flex-1 flex-col justify-end" tabindex="0">
                                    <div class="pointer-events-none absolute -top-1 left-1/2 z-10 hidden -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1.5 text-xs font-semibold text-white shadow-lg group-hover:block group-focus:block">{{ $money($day->amount) }}</div>
                                    @if ($day->amount > 0)
                                        <div class="w-full rounded-t-lg transition {{ $day->date->isToday() ? 'bg-emerald-500' : 'bg-emerald-200 group-hover:bg-emerald-400' }}" style="height: {{ max(3, $day->amount / $chart['peak'] * 100) }}%"></div>
                                    @else
                                        <div class="h-0.5 w-full rounded-full bg-slate-200"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex gap-2 sm:gap-3">
                            @foreach ($chart['days'] as $day)
                                <span class="min-w-0 flex-1 text-center text-[11px] font-medium {{ $day->date->isToday() ? 'font-semibold text-emerald-700' : 'text-slate-400' }}">{{ $day->date->isToday() ? 'Today' : $day->date->format('D') }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-5 rounded-xl bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">No sales in the last 7 days yet.</p>
                    @endif
                </section>
            @endif
        </div>

        <div class="space-y-5">

            {{-- ============ Shortcuts ============ --}}
            @if ($actions)
                <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h2 class="text-base font-bold text-slate-900">Shortcuts</h2>
                    <div class="mt-4 grid grid-cols-2 gap-2.5">
                        @foreach ($actions as [$label, $hint, $icon, $url])
                            <a href="{{ $url }}" class="group flex flex-col rounded-xl border border-slate-200 p-3.5 transition hover:border-emerald-300 hover:bg-emerald-50/50">
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition group-hover:bg-emerald-600 group-hover:text-white"><i class="fas {{ $icon }} text-sm"></i></span>
                                <span class="mt-3 text-sm font-semibold leading-5 text-slate-900">{{ $label }}</span>
                                <span class="mt-0.5 text-xs leading-4 text-slate-500">{{ $hint }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ============ Latest ============ --}}
            @if ($recent)
                <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 p-4 pb-3 sm:px-6 sm:pt-6">
                        <h2 class="text-base font-bold text-slate-900">{{ $recent['title'] }}</h2>
                        <a href="{{ $recent['url'] }}" class="flex-shrink-0 text-sm font-semibold text-emerald-700 hover:text-emerald-800">See all <i class="fas fa-arrow-right ml-0.5 text-xs"></i></a>
                    </div>

                    @if ($recent['rows']->isEmpty())
                        <p class="mx-4 mb-4 rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500 sm:mx-6 sm:mb-6">Nothing here yet.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($recent['rows'] as $row)
                                <li>
                                    <a href="{{ $row['url'] }}" class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50 sm:px-6">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-900">{{ $row['title'] }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $row['meta'] }}</span>
                                        </span>
                                        <span class="flex-shrink-0 text-right">
                                            <span class="block text-sm font-semibold {{ str_starts_with($row['value'], '-') ? 'text-rose-600' : (str_starts_with($row['value'], '+') ? 'text-emerald-700' : 'text-slate-900') }}">{{ $row['value'] }}</span>
                                            @if ($row['note'])
                                                <span class="mt-0.5 block text-[11px] font-semibold text-amber-700">{{ $row['note'] }}</span>
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    </div>
</div>

@if ($canSetLocation)
    <x-modal id="location-modal" title="Where is {{ $branch->name }}?" max-width="xl">
        <form action="{{ route('vendor.branches.location', $branch) }}" method="POST" id="location-form" data-branch-form>
            @csrf
            @if ($locationFailed)
                <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">
                    <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->getBag('location')->first() }}</p>
                </div>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    @include('partials.location-picker', ['latitude' => $typed('latitude'), 'longitude' => $typed('longitude')])
                </div>
                <div class="sm:col-span-2">
                    <label for="location-address" class="mb-1.5 block text-sm font-semibold text-slate-800">Street address</label>
                    <input id="location-address" type="text" name="address" value="{{ $typed('address') }}" required maxlength="255" placeholder="House number, street and area" data-place="address" class="{{ $field }}">
                </div>
                <div>
                    <label for="location-city" class="mb-1.5 block text-sm font-semibold text-slate-800">City or area</label>
                    <input id="location-city" type="text" name="city" value="{{ $typed('city') }}" maxlength="120" placeholder="e.g. Ikeja" data-place="city" class="{{ $field }}">
                </div>
                <div>
                    <label for="location-state" class="mb-1.5 block text-sm font-semibold text-slate-800">State</label>
                    <input id="location-state" type="text" name="state" value="{{ $typed('state') }}" maxlength="120" placeholder="e.g. Lagos" data-place="state" class="{{ $field }}">
                </div>
            </div>
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
                <button type="submit" form="location-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Save location</button>
            </div>
        </x-slot:footer>
    </x-modal>
@endif
@endsection

@if ($canSetLocation)
    @push('scripts')
        @include('vendor-dashboard.partials.branch-form-script')
        <script>
            // The banner button opens the window and asks the device where it is, in one press.
            document.querySelector('[data-locate-now]')?.addEventListener('click', () => {
                setTimeout(() => document.querySelector('#location-form [data-use-location]')?.click(), 250);
            });
            document.querySelector('[data-type-address]')?.addEventListener('click', () => {
                setTimeout(() => document.getElementById('location-address')?.focus(), 250);
            });
        </script>
        @if ($locationFailed)
            <script>document.addEventListener('DOMContentLoaded', () => CuraModal.open('location-modal'));</script>
        @endif
    @endpush
@endif
