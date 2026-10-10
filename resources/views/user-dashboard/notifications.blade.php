@extends('layouts.app')

@section('title', 'Notifications')
@section('header_title', 'Notifications')

@php
    // Icon and colour for each kind of notification.
    $kinds = [
        'order' => ['fa-box', 'bg-emerald-50 text-emerald-600'],
        'stock' => ['fa-bookmark', 'bg-sky-50 text-sky-600'],
        'finance' => ['fa-wallet', 'bg-amber-50 text-amber-600'],
        'security' => ['fa-shield-halved', 'bg-rose-50 text-rose-500'],
    ];
    $tab = 'inline-flex h-10 flex-shrink-0 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold transition';
    $tabOn = 'border-emerald-600 bg-emerald-600 text-white';
    $tabOff = 'border-slate-200 bg-white text-slate-700 hover:border-slate-300';
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[820px] space-y-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex gap-2" aria-label="Filter notifications">
            <a href="{{ route('user.notifications') }}" class="{{ $tab }} {{ $unreadOnly ? $tabOff : $tabOn }}" @unless ($unreadOnly) aria-current="page" @endunless>
                All <span class="rounded-md px-1.5 py-0.5 text-xs {{ $unreadOnly ? 'bg-slate-100 text-slate-500' : 'bg-white/20 text-white' }}">{{ $totalCount }}</span>
            </a>
            <a href="{{ route('user.notifications', ['show' => 'unread']) }}" class="{{ $tab }} {{ $unreadOnly ? $tabOn : $tabOff }}" @if ($unreadOnly) aria-current="page" @endif>
                Unread <span class="rounded-md px-1.5 py-0.5 text-xs {{ $unreadOnly ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $unreadCount }}</span>
            </a>
        </nav>

        <div class="flex items-center gap-2">
            @if ($unreadCount)
                <form action="{{ route('user.notifications.mark-read') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700">
                        <i class="fas fa-check-double text-xs"></i><span class="hidden min-[420px]:inline">Mark all as read</span><span class="min-[420px]:hidden">Read all</span>
                    </button>
                </form>
            @endif
            <a href="{{ route('user.notifications.preferences') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:text-slate-900" aria-label="Choose what you are notified about" title="Choose what you are notified about">
                <i class="fas fa-sliders text-sm"></i>
            </a>
        </div>
    </div>

    @if ($notifications->isNotEmpty())
        <ul class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @foreach ($notifications as $notification)
                @php [$icon, $tone] = $kinds[$notification->type] ?? ['fa-bell', 'bg-slate-100 text-slate-500']; $unread = ! $notification->read_at; @endphp
                <li class="flex gap-3 border-b border-slate-100 p-4 last:border-b-0 sm:gap-4 sm:px-6 {{ $unread ? 'bg-emerald-50/40' : '' }}">
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $tone }}"><i class="fas {{ $icon }} text-sm"></i></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-start gap-2">
                            <span class="min-w-0 flex-1 break-words text-sm {{ $unread ? 'font-bold text-slate-900' : 'font-semibold text-slate-700' }}">{{ $notification->title }}</span>
                            @if ($unread)<span class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500" title="Unread"></span>@endif
                        </p>
                        <p class="mt-1 break-words text-sm leading-6 text-slate-600">{{ $notification->message }}</p>
                        <p class="mt-1.5 text-xs text-slate-400" title="{{ $notification->created_at->format('M j, Y · g:i A') }}">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    <form action="{{ route('user.notifications.delete', $notification->id) }}" method="POST" class="flex-shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 transition hover:bg-rose-50 hover:text-rose-600" aria-label="Remove this notification">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>

        @if ($notifications->hasPages())
            <nav class="flex items-center justify-between gap-3" aria-label="Pages">
                @if ($notifications->onFirstPage())
                    <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Newer</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Newer</a>
                @endif
                <p class="text-sm text-slate-500">Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</p>
                @if ($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-emerald-300 hover:text-emerald-700">Older</a>
                @else
                    <span class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-300">Older</span>
                @endif
            </nav>
        @endif
    @else
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-xl text-slate-400"><i class="fas {{ $unreadOnly ? 'fa-check' : 'fa-bell' }}"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">{{ $unreadOnly ? 'You are all caught up' : 'No notifications yet' }}</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-600">{{ $unreadOnly ? 'Nothing unread. New order updates will appear here.' : 'When a pharmacy confirms an order, gets it ready or sends it out, you will see it here.' }}</p>
            @if ($unreadOnly && $totalCount)
                <a href="{{ route('user.notifications') }}" class="mt-6 inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:border-slate-300">Show all notifications</a>
            @endif
        </div>
    @endif
</div>
@endsection
