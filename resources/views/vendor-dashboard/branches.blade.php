@extends('layouts.app')

@section('title', 'Branches')
@section('header_title', 'Branches')

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $short = function ($amount) {
        $amount = (float) $amount;

        return '₦' . match (true) {
            $amount >= 1_000_000 => rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M',
            $amount >= 10_000 => rtrim(rtrim(number_format($amount / 1_000, 1), '0'), '.') . 'k',
            default => number_format($amount, 0),
        };
    };
    $branchCount = $rows->count();
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1280px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-500">
                <span class="font-semibold text-slate-900">{{ $organization->name }}</span>
                <a href="{{ route('vendor.profile') }}#organization" class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-gear text-[10px]"></i> Organization settings</a>
                <span class="w-full sm:w-auto">{{ $branchCount }} {{ Str::plural('branch', $branchCount) }}. Everything below adds up all of them.</span>
            </p>
        </div>
        <button type="button" data-cura-modal-open="branch-modal" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
            <i class="fas fa-plus text-xs"></i> Add branch
        </button>
    </div>

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i><p>{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- ============ The whole organization ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <div class="relative flex items-center justify-between">
                <p class="text-[13px] font-medium text-slate-300">Sales today</p>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-emerald-300"><i class="fas fa-coins text-sm"></i></span>
            </div>
            <p class="relative mt-3 truncate text-[22px] font-bold leading-none min-[380px]:text-[26px] sm:text-[28px]" title="{{ $money($totals['sales_today']) }}">{{ $money($totals['sales_today']) }}</p>
            <p class="relative mt-2 truncate text-xs text-slate-400">{{ $money($totals['sales_month']) }} this month</p>
        </div>

        @foreach ([
            ['Online orders waiting', number_format($totals['open_orders']), 'To accept, pack or hand over', 'fa-bag-shopping', $totals['open_orders'] ? 'bg-amber-50 text-amber-600' : 'bg-slate-100 text-slate-500'],
            ['Stock value', $short($totals['stock_value']), $totals['low_stock'] . ' ' . Str::plural('product', $totals['low_stock']) . ' running low', 'fa-boxes-stacked', 'bg-emerald-50 text-emerald-600'],
            ['Staff', number_format($totals['staff']), 'Across ' . $branchCount . ' ' . Str::plural('branch', $branchCount), 'fa-user-group', 'bg-sky-50 text-sky-600'],
        ] as [$label, $number, $hint, $icon, $iconStyle])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between">
                    <p class="truncate text-[13px] font-medium text-slate-500">{{ $label }}</p>
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $iconStyle }}"><i class="fas {{ $icon }} text-sm"></i></span>
                </div>
                <p class="mt-3 truncate text-[22px] font-bold leading-none text-slate-900 min-[380px]:text-[26px] sm:text-[28px]">{{ $number }}</p>
                <p class="mt-2 truncate text-xs text-slate-500">{{ $hint }}</p>
            </div>
        @endforeach
    </section>

    @if ($transfersOnTheWay)
        <a href="{{ route('vendor.stock.receive', ['tab' => 'transfers']) }}" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:border-amber-300">
            <i class="fas fa-right-left text-amber-600"></i>
            <span class="flex-1">{{ $transfersOnTheWay }} stock {{ Str::plural('transfer', $transfersOnTheWay) }} between branches {{ $transfersOnTheWay === 1 ? 'is' : 'are' }} still on the way.</span>
            <i class="fas fa-arrow-right text-xs"></i>
        </a>
    @endif

    {{-- ============ Each branch ============ --}}
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($rows as $row)
            @php
                $branch = $row->branch;
                $isCurrent = $current?->id === $branch->id;
                $share = (int) round($row->sales_month / $bestMonth * 100);
            @endphp
            <article class="flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition hover:shadow-md {{ $isCurrent ? 'border-emerald-300 ring-1 ring-emerald-200' : 'border-slate-200' }}">
                <a href="{{ route('vendor.branches.show', $branch) }}" class="group block p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl {{ $branch->is_main ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/30' : 'bg-slate-100 text-slate-500' }}"><i class="fas fa-store"></i></span>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-bold text-slate-900 group-hover:text-emerald-700">{{ $branch->name }}</h2>
                            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $branch->fullAddress() ?: 'No address yet' }}</p>
                        </div>
                        <i class="fas fa-chevron-right mt-1.5 flex-shrink-0 text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600"></i>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @if ($branch->is_main)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Main branch</span>
                        @endif
                        @if ($isCurrent)
                            <span class="rounded-full bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white">You are here</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $branch->is_active ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500' }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $branch->is_active ? 'Taking online orders' : 'Offline on marketplace' }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-end justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-500">Sales today</p>
                            <p class="mt-1 truncate text-xl font-extrabold text-slate-900">{{ $money($row->sales_today) }}</p>
                        </div>
                        <p class="flex-shrink-0 text-right text-xs text-slate-500">{{ $row->sale_count_today }} POS {{ Str::plural('sale', $row->sale_count_today) }}</p>
                    </div>

                    {{-- This month, shown against the best branch --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-xs text-slate-500">
                            <span>This month</span>
                            <span class="font-semibold text-slate-900">{{ $money($row->sales_month) }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="{{ $share }}% of the best branch this month">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ max($row->sales_month > 0 ? 4 : 0, $share) }}%"></div>
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center">
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500">Orders waiting</dt>
                            <dd class="mt-1 text-base font-bold {{ $row->open_orders ? 'text-amber-600' : 'text-slate-900' }}">{{ $row->open_orders }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500">Low stock</dt>
                            <dd class="mt-1 text-base font-bold {{ $row->low_stock ? 'text-rose-600' : 'text-slate-900' }}">{{ $row->low_stock }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-medium text-slate-500">Staff</dt>
                            <dd class="mt-1 text-base font-bold text-slate-900">{{ $row->staff }}</dd>
                        </div>
                    </dl>
                </a>

                <div class="mt-auto flex gap-2 border-t border-slate-100 bg-slate-50/70 p-3">
                    <a href="{{ route('vendor.branches.show', $branch) }}" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900">
                        <i class="fas fa-chart-simple text-xs text-slate-400"></i> View details
                    </a>
                    @unless ($isCurrent)
                        <form action="{{ route('vendor.branches.switch', $branch) }}" method="POST" class="flex-1">
                            @csrf
                            <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-slate-900 text-sm font-semibold text-white transition hover:bg-slate-800">
                                <i class="fas fa-right-to-bracket text-xs"></i> Work here
                            </button>
                        </form>
                    @endunless
                </div>
            </article>
        @endforeach

        {{-- Add another --}}
        <button type="button" data-cura-modal-open="branch-modal" class="flex min-h-[14rem] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white/50 p-6 text-center transition hover:border-emerald-400 hover:bg-emerald-50/40">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="fas fa-plus"></i></span>
            <span class="mt-4 text-sm font-semibold text-slate-900">Add a branch</span>
            <span class="mt-1 max-w-[15rem] text-xs leading-5 text-slate-500">It gets its own POS, stock, staff and online orders. Your products come along when you send it stock.</span>
        </button>
    </section>
</div>

{{-- Add branch --}}
<x-modal id="branch-modal" title="Add a branch" max-width="xl">
    <form action="{{ route('vendor.branches.store') }}" method="POST" id="branch-form" data-branch-form>
        @csrf
        @include('vendor-dashboard.partials.branch-form', ['branch' => null])
    </form>
    <x-slot:footer>
        <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
            <button type="submit" form="branch-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Add branch</button>
        </div>
    </x-slot:footer>
</x-modal>

@endsection

@push('scripts')
    @include('vendor-dashboard.partials.branch-form-script')
    @if ($errors->any() && old('address') !== null)
        <script>document.addEventListener('DOMContentLoaded', () => CuraModal.open('branch-modal'));</script>
    @endif
@endpush
