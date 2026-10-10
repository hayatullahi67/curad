@extends('layouts.app')

@section('title', 'Wallet')
@section('header_title', 'Wallet')

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $tab = 'inline-flex h-10 flex-shrink-0 items-center rounded-xl border px-3.5 text-sm font-semibold transition';
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[900px] space-y-5">

    {{-- ============ Balance ============ --}}
    <section class="grid gap-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)]">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-5 text-white shadow-sm sm:p-6">
            <div class="pointer-events-none absolute -right-10 -top-12 h-40 w-40 rounded-full bg-emerald-500/25 blur-2xl" aria-hidden="true"></div>
            <p class="relative flex items-center gap-2 text-sm font-medium text-slate-300"><i class="fas fa-wallet text-emerald-300"></i>Wallet balance</p>
            <p class="relative mt-3 break-words text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $money($user->wallet_balance) }}</p>
            <p class="relative mt-2 text-xs leading-5 text-slate-400">Used to pay for orders at checkout. Refunds come back here.</p>
        </div>

        @foreach ([
            ['Spent this month', $money($spentThisMonth), 'fa-arrow-up', 'bg-rose-50 text-rose-500'],
            ['Received this month', $money($receivedThisMonth), 'fa-arrow-down', 'bg-emerald-50 text-emerald-600'],
        ] as [$label, $amount, $icon, $tone])
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-[13px] font-medium text-slate-500">{{ $label }}</p>
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $tone }}"><i class="fas {{ $icon }} text-xs"></i></span>
                </div>
                <p class="mt-3 break-words text-xl font-bold text-slate-900 sm:text-2xl">{{ $amount }}</p>
            </div>
        @endforeach
    </section>

    @unless ($hasPin)
        <a href="{{ route('user.profile') }}#pin" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:border-amber-300">
            <i class="fas fa-key text-amber-600"></i>
            <span class="flex-1"><span class="font-semibold">Set a wallet PIN.</span> You need a 4-digit PIN before you can pay from your wallet.</span>
            <i class="fas fa-arrow-right text-xs"></i>
        </a>
    @endunless

    {{-- ============ Money in and out ============ --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 class="text-base font-bold text-slate-900">Money in and out</h2>
            <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden" aria-label="Filter">
                @foreach ($filters as $value => $label)
                    <a href="{{ route('user.history', $value === 'all' ? [] : ['show' => $value]) }}" class="{{ $tab }} {{ $filter === $value ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' }}" @if ($filter === $value) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </header>

        @forelse ($transactions as $transaction)
            @php $in = $transaction->type === 'credit'; @endphp
            <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3.5 last:border-b-0 sm:px-6">
                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $in ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500' }}"><i class="fas {{ $in ? 'fa-arrow-down' : 'fa-arrow-up' }} text-sm"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="break-words text-sm font-semibold text-slate-900">{{ $transaction->description }}</p>
                    <p class="mt-0.5 truncate text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($transaction->created_at)->format('M j, Y · g:i A') }}@if ($transaction->reference) · <span class="font-mono">{{ $transaction->reference }}</span>@endif</p>
                </div>
                <p class="flex-shrink-0 text-sm font-bold {{ $in ? 'text-emerald-700' : 'text-slate-900' }}">{{ $in ? '+' : '−' }}{{ $money($transaction->amount) }}</p>
            </div>
        @empty
            <div class="px-6 py-14 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-receipt"></i></span>
                <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $filter === 'all' ? 'No wallet activity yet' : 'Nothing here' }}</h3>
                <p class="mx-auto mt-1 max-w-xs text-sm text-slate-500">Payments you make from your wallet, and refunds for cancelled orders, are listed here.</p>
            </div>
        @endforelse
    </section>

    @if ($transactions->hasPages())
        <nav class="flex items-center justify-between gap-3" aria-label="Pages">
            @if ($transactions->onFirstPage())
                <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Newer</span>
            @else
                <a href="{{ $transactions->previousPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Newer</a>
            @endif
            <p class="text-sm text-slate-500">Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</p>
            @if ($transactions->hasMorePages())
                <a href="{{ $transactions->nextPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Older</a>
            @else
                <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Older</span>
            @endif
        </nav>
    @endif
</div>
@endsection
