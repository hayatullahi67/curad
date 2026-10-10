@extends('layouts.app')

@section('title', 'Messages')
@section('header_title', 'Messages')

@section('content')
<div class="mx-auto -mt-3 max-w-[820px] space-y-5">
    <p class="text-sm text-slate-500">Your conversations with pharmacies. To start one, open an order and press “Message” beside the pharmacy.</p>

    @if ($conversations->isNotEmpty())
        <ul class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @foreach ($conversations as $conversation)
                @php
                    $last = $conversation->latestMessage;
                    $unread = (int) $conversation->unread_count;
                    $mine = $last && ! $last->from_pharmacy;
                @endphp
                <li class="border-b border-slate-100 last:border-b-0">
                    <a href="{{ route('user.chat.show', $conversation) }}" class="group flex items-center gap-3 p-4 transition hover:bg-slate-50 sm:gap-4 sm:px-6 {{ $unread ? 'bg-emerald-50/40' : '' }}">
                        <span class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl {{ $unread ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' }}"><i class="fas fa-store"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-baseline justify-between gap-3">
                                <span class="truncate text-sm {{ $unread ? 'font-bold' : 'font-semibold' }} text-slate-900">{{ $conversation->pharmacy?->displayName() ?? 'Pharmacy' }}</span>
                                <span class="flex-shrink-0 text-xs {{ $unread ? 'font-semibold text-emerald-700' : 'text-slate-400' }}">{{ ($last?->created_at ?? $conversation->updated_at)->diffForHumans(null, true, true) }}</span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-[13px] {{ $unread ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                    @if ($last){{ $mine ? 'You: ' : '' }}{{ $last->message }}@else No messages yet. Say hello.@endif
                                </span>
                                @if ($unread)
                                    <span class="flex h-5 min-w-[1.25rem] flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 px-1 text-[11px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                                @endif
                            </span>
                            @if ($conversation->order)
                                <span class="mt-1 block text-xs text-slate-400">About order <span class="font-mono">{{ $conversation->order->order_number }}</span></span>
                            @endif
                        </span>
                        <i class="fas fa-chevron-right flex-shrink-0 text-xs text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-emerald-600"></i>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas fa-comment-dots"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">No messages yet</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">Have a question about an order? Open the order and press “Message” beside the pharmacy that is supplying it.</p>
            <a href="{{ route('user.orders') }}" class="mt-6 inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"><i class="fas fa-box text-xs"></i>Go to my orders</a>
        </div>
    @endif
</div>
@endsection
