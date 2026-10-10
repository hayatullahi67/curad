@extends('layouts.app')

@section('title', 'Notifications')
@section('header_title', 'Notifications')

@php
    // Icon, colour and where to go for each kind of notification.
    $kinds = [
        'order' => ['fa-bag-shopping', 'bg-emerald-50 text-emerald-600', 'Online Orders', route('vendor.orders')],
        'stock' => ['fa-boxes-stacked', 'bg-amber-50 text-amber-600', 'Inventory', route('vendor.inventory')],
        'finance' => ['fa-wallet', 'bg-sky-50 text-sky-600', 'Reports', route('vendor.reports')],
    ];
    $days = $notifications->getCollection()->groupBy(fn ($notification) => $notification->created_at->toDateString());
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[880px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            @if ($counts['unread'])
                You have <span class="font-semibold text-slate-900">{{ $counts['unread'] }} unread</span> {{ Str::plural('notification', $counts['unread']) }}.
            @else
                You are all caught up.
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            @if ($counts['all'] > $counts['unread'])
                <form action="{{ route('vendor.notifications.clear') }}" method="POST" onsubmit="return confirm('Delete every notification you have already read?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-rose-300 hover:text-rose-700">
                        <i class="fas fa-trash-can text-xs"></i> Clear read
                    </button>
                </form>
            @endif
            @if ($counts['unread'])
                <form action="{{ route('vendor.notifications.read') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                        <i class="fas fa-check-double text-xs"></i> Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ============ All / Unread ============ --}}
    <nav class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label="Filter notifications">
        @foreach (['all' => 'All', 'unread' => 'Unread'] as $value => $label)
            <a href="{{ route('vendor.notifications', $value === 'all' ? [] : ['show' => $value]) }}"
               class="inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-sm font-semibold transition {{ $show === $value ? 'bg-slate-900 text-white' : 'text-slate-600 hover:text-slate-900' }}"
               @if ($show === $value) aria-current="page" @endif>
                {{ $label }}
                <span class="rounded-md px-1.5 py-0.5 text-xs {{ $show === $value ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $counts[$value] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- ============ List ============ --}}
    @if ($notifications->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas {{ $show === 'unread' ? 'fa-check' : 'fa-bell' }}"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">{{ $show === 'unread' ? 'Nothing unread' : 'No notifications yet' }}</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">
                {{ $show === 'unread' ? 'You have read everything.' : 'New online orders, cancellations and deliveries will show up here.' }}
            </p>
            @if ($show === 'unread' && $counts['all'])
                <a href="{{ route('vendor.notifications') }}" class="mt-6 inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Show all notifications</a>
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach ($days as $date => $items)
                @php $day = \Carbon\Carbon::parse($date); @endphp
                <section>
                    <h2 class="mb-2 px-1 text-sm font-semibold text-slate-900">{{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('l, M j, Y')) }}</h2>

                    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        @foreach ($items as $notification)
                            @php
                                [$icon, $tone, $linkLabel, $link] = $kinds[$notification->type] ?? ['fa-bell', 'bg-slate-100 text-slate-500', null, null];
                                $unread = $notification->read_at === null;
                            @endphp
                            <li class="relative flex items-start gap-3 px-4 py-4 sm:gap-4 sm:px-5 {{ $unread ? 'bg-emerald-50/40' : '' }}">
                                @if ($unread)
                                    <span class="absolute inset-y-0 left-0 w-1 bg-emerald-500" aria-hidden="true"></span>
                                @endif

                                <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $unread ? $tone : 'bg-slate-100 text-slate-400' }}"><i class="fas {{ $icon }} text-sm"></i></span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                        <h3 class="flex items-center gap-2 text-sm {{ $unread ? 'font-semibold text-slate-900' : 'font-medium text-slate-700' }}">
                                            {{ $notification->title }}
                                            @if ($unread)<span class="h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500"><span class="sr-only">Unread</span></span>@endif
                                        </h3>
                                        <time datetime="{{ $notification->created_at->toIso8601String() }}" class="whitespace-nowrap text-xs text-slate-500" title="{{ $notification->created_at->format('M j, Y g:i A') }}">{{ $day->isToday() ? $notification->created_at->diffForHumans() : $notification->created_at->format('g:i A') }}</time>
                                    </div>

                                    <p class="mt-1 break-words text-sm leading-6 {{ $unread ? 'text-slate-700' : 'text-slate-500' }}">{{ $notification->message }}</p>

                                    <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold">
                                        @if ($link)
                                            <a href="{{ $link }}" class="text-emerald-700 hover:text-emerald-800">Open {{ $linkLabel }} <i class="fas fa-arrow-right ml-0.5 text-[10px]"></i></a>
                                        @endif
                                        <form action="{{ route('vendor.notifications.toggle', $notification->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-slate-500 transition hover:text-slate-900">{{ $unread ? 'Mark as read' : 'Mark as unread' }}</button>
                                        </form>
                                        <form action="{{ route('vendor.notifications.destroy', $notification->id) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-slate-400 transition hover:text-rose-600">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>

        @if ($notifications->hasPages())
            <nav class="flex items-center justify-between gap-3" aria-label="Pages">
                @if ($notifications->onFirstPage())
                    <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Newer</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Newer</a>
                @endif
                <p class="text-sm text-slate-500">Page <span class="font-semibold text-slate-900">{{ $notifications->currentPage() }}</span> of {{ $notifications->lastPage() }}</p>
                @if ($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Older</a>
                @else
                    <span class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-300">Older</span>
                @endif
            </nav>
        @endif
    @endif
</div>
@endsection
