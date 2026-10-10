{{--
    Messages on the pharmacy side: the list of conversations on the left and
    the open one on the right. On a phone only one of the two is on screen:
    the list, or the open conversation with a back arrow.
--}}
@extends('layouts.app')

@section('title', 'Messages')
@section('header_title', 'Messages')

@php
    $me = auth()->user();
    $customer = $open?->user;
    $lastId = (int) ($messages->last()?->id ?? 0);
    $initial = fn (?string $name) => strtoupper(mb_substr(trim((string) $name) ?: 'C', 0, 1));
    $roleOf = fn ($sender) => $sender?->role === 'vendor' ? 'Owner' : \App\Support\StaffRoles::label($sender?->staff_role);
    $dayLabel = fn ($date) => $date->isToday() ? 'Today' : ($date->isYesterday() ? 'Yesterday' : $date->format('D, M j'));
    $tab = 'inline-flex h-8 items-center gap-1.5 rounded-lg px-3 text-[13px] font-semibold transition';
@endphp

@section('content')
<div class="mx-auto -my-8 flex max-w-[1280px] flex-col gap-3 py-3 sm:py-5" style="height: calc(var(--vh, 1vh) * 100 - 4.5rem)">

    <div class="{{ $open ? 'hidden lg:block' : '' }} flex-shrink-0">
        @include('vendor-dashboard.partials.branch-filter', ['route' => 'vendor.chat', 'keep' => array_diff_key($keep, ['branch' => true]), 'waiting' => $waitingByBranch])
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[360px_minmax(0,1fr)]">

        {{-- ============ Conversations ============ --}}
        <aside class="{{ $open ? 'hidden lg:flex' : 'flex' }} min-h-0 min-w-0 flex-col border-slate-200 lg:border-r" aria-label="Conversations">
            <div class="flex-shrink-0 space-y-3 border-b border-slate-100 p-3 sm:p-4">
                <div class="flex items-center justify-between gap-3">
                    <h1 class="min-w-0 truncate text-base font-bold text-slate-900">
                        {{ $scope->showsSeveral() ? 'All branches' : ($scope->selected?->name ?? 'Messages') }}
                    </h1>
                    <div class="flex flex-shrink-0 rounded-xl bg-slate-100 p-1" role="group" aria-label="Filter conversations">
                        @foreach ($filters as $value => $label)
                            <a href="{{ route('vendor.chat', array_filter(array_merge($keep, ['show' => $value === 'all' ? null : $value]))) }}"
                               class="{{ $tab }} {{ $filter === $value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}" @if ($filter === $value) aria-current="true" @endif>
                                {{ $label }}
                                @if ($value === 'unread' && $unreadTotal)
                                    <span class="flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold text-white">{{ $unreadTotal > 99 ? '99+' : $unreadTotal }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <form action="{{ route('vendor.chat') }}" method="GET" role="search" class="flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 transition focus-within:border-emerald-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-emerald-100">
                    @foreach (array_diff_key($keep, ['q' => true]) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <label for="chat-search" class="sr-only">Search conversations</label>
                    <i class="fas fa-magnifying-glass text-sm text-slate-400"></i>
                    <input id="chat-search" type="search" name="q" value="{{ $search }}" placeholder="Customer, phone or order number" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0">
                    @if ($search !== '')
                        <a href="{{ route('vendor.chat', array_diff_key($keep, ['q' => true])) }}" class="text-slate-400 hover:text-slate-700" aria-label="Clear search"><i class="fas fa-xmark"></i></a>
                    @endif
                </form>
            </div>

            <ul class="min-h-0 flex-1 overflow-y-auto">
                @forelse ($conversations as $conversation)
                    @php
                        $last = $conversation->latestMessage;
                        $unread = (int) $conversation->unread_count;
                        $active = $open && $open->id === $conversation->id;
                        $fromUs = $last && $last->from_pharmacy;
                    @endphp
                    <li>
                        <a href="{{ route('vendor.chat.show', [$conversation] + $keep) }}"
                           class="relative flex items-start gap-3 border-b border-slate-100 px-3 py-3.5 transition sm:px-4 {{ $active ? 'bg-emerald-50' : 'hover:bg-slate-50' }}" @if ($active) aria-current="true" @endif>
                            @if ($active)<span class="absolute inset-y-0 left-0 w-1 bg-emerald-600"></span>@endif
                            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $unread ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $initial($conversation->user?->full_name) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm {{ $unread ? 'font-bold' : 'font-semibold' }} text-slate-900">{{ $conversation->user?->full_name ?? 'Customer' }}</span>
                                    <span class="flex-shrink-0 text-[11px] {{ $unread ? 'font-semibold text-emerald-700' : 'text-slate-400' }}">{{ ($last?->created_at ?? $conversation->updated_at)->diffForHumans(null, true, true) }}</span>
                                </span>
                                <span class="mt-0.5 flex items-center gap-2">
                                    <span class="min-w-0 flex-1 truncate text-[13px] {{ $unread ? 'font-medium text-slate-800' : 'text-slate-500' }}">
                                        @if ($last)@if ($fromUs)<i class="fas fa-reply mr-1 text-[10px] text-slate-400"></i>@endif{{ $last->message }}@else No messages yet @endif
                                    </span>
                                    @if ($unread)
                                        <span class="flex h-5 min-w-[1.25rem] flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 px-1 text-[11px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                                    @endif
                                </span>
                                <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                    @if ($scope->showsSeveral())
                                        <span class="inline-flex max-w-full items-center gap-1 rounded-md bg-slate-900 px-1.5 py-0.5 text-[10px] font-semibold text-white"><i class="fas fa-store text-[8px] text-emerald-300"></i><span class="truncate">{{ $conversation->pharmacy?->name }}</span></span>
                                    @endif
                                    @if ($conversation->order)
                                        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-slate-600">{{ $conversation->order->order_number }}</span>
                                    @endif
                                </span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="flex h-full flex-col items-center justify-center px-6 py-14 text-center">
                        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas {{ $search !== '' ? 'fa-magnifying-glass' : ($filter === 'unread' ? 'fa-check' : 'fa-comment-dots') }}"></i></span>
                        <p class="mt-4 text-sm font-semibold text-slate-900">
                            {{ $search !== '' ? 'No conversation matches that search' : ($filter === 'unread' ? 'Nothing unread' : 'No messages yet') }}
                        </p>
                        <p class="mt-1 max-w-[16rem] text-[13px] leading-5 text-slate-500">
                            {{ $search !== '' ? 'Try the customer’s name, phone or the order number.' : ($filter === 'unread' ? 'Every customer message has been read.' : 'When a customer writes to ' . ($scope->showsSeveral() ? 'one of your branches' : 'this branch') . ' about an order, the conversation appears here.') }}
                        </p>
                    </li>
                @endforelse
            </ul>
        </aside>

        {{-- ============ The open conversation ============ --}}
        <section class="{{ $open ? 'flex' : 'hidden lg:flex' }} min-h-0 min-w-0 flex-col" aria-label="Conversation">
            @if ($open)
                <header class="flex flex-shrink-0 items-center gap-3 border-b border-slate-100 p-3 sm:px-5 sm:py-3.5">
                    <a href="{{ route('vendor.chat', $keep) }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden" aria-label="Back to all conversations"><i class="fas fa-arrow-left"></i></a>
                    <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-white">{{ $initial($customer?->full_name) }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-[15px] font-bold text-slate-900">{{ $customer?->full_name ?? 'Customer' }}</h2>
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1"><i class="fas fa-store text-[10px] text-slate-400"></i>{{ $open->pharmacy?->name }}</span>
                            @if ($open->order)
                                <a href="{{ route('vendor.orders', ['status' => 'all', 'search' => $open->order->order_number, 'branch' => $scope->canChoose() ? $open->pharmacy_id : null]) }}" class="font-mono font-semibold text-emerald-700 hover:text-emerald-800">{{ $open->order->order_number }}</a>
                            @endif
                        </p>
                    </div>
                    @if ($customer?->phone)
                        <a href="tel:{{ $customer->phone }}" class="flex h-10 flex-shrink-0 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700" aria-label="Call {{ $customer->full_name }}">
                            <i class="fas fa-phone text-xs"></i><span class="hidden sm:inline">{{ $customer->phone }}</span>
                        </a>
                    @endif
                </header>

                <div id="thread" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4 sm:p-5" aria-live="polite">
                    @php $shownDay = null; @endphp
                    @forelse ($messages as $message)
                        @php
                            $fromCustomer = ! $message->from_pharmacy;
                            $day = $message->created_at->toDateString();
                        @endphp
                        @if ($day !== $shownDay)
                            @php $shownDay = $day; @endphp
                            <p class="flex justify-center py-1"><span class="rounded-full bg-white px-3 py-1 text-[11px] font-semibold text-slate-500 shadow-sm ring-1 ring-slate-200">{{ $dayLabel($message->created_at) }}</span></p>
                        @endif
                        <div class="flex {{ $fromCustomer ? 'justify-start' : 'justify-end' }}" data-message="{{ $message->id }}">
                            <div class="max-w-[85%] sm:max-w-[70%]">
                                <p class="whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-[15px] leading-6 {{ $fromCustomer ? 'rounded-bl-md border border-slate-200 bg-white text-slate-800' : 'rounded-br-md bg-emerald-600 text-white' }}">{{ $message->message }}</p>
                                <p class="mt-1 text-[11px] text-slate-400 {{ $fromCustomer ? '' : 'text-right' }}">
                                    @unless ($fromCustomer)<span class="font-medium text-slate-500">{{ (int) $message->sender_id === (int) $me->id ? 'You' : ($message->sender?->full_name ?? 'Pharmacy') . ' · ' . $roleOf($message->sender) }}</span> · @endunless{{ $message->created_at->format('g:i A') }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div id="thread-empty" class="flex h-full flex-col items-center justify-center px-6 text-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 ring-1 ring-slate-200"><i class="fas fa-comment-dots"></i></span>
                            <p class="mt-4 text-sm font-semibold text-slate-900">No messages yet</p>
                            <p class="mt-1 max-w-xs text-sm text-slate-500">{{ $customer?->first_name ?: 'The customer' }} opened this conversation but has not written anything.</p>
                        </div>
                    @endforelse
                </div>

                <form id="reply-form" action="{{ route('vendor.chat.store', [$open] + array_filter(['branch' => $scope->param()])) }}" method="POST" class="flex flex-shrink-0 items-end gap-2 border-t border-slate-100 p-3 sm:p-4">
                    @csrf
                    <label for="reply" class="sr-only">Your reply</label>
                    <textarea id="reply" name="message" rows="1" maxlength="1000" required placeholder="Reply as {{ $open->pharmacy?->name }}…" class="max-h-32 min-h-[2.75rem] flex-1 resize-none rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"></textarea>
                    <button type="submit" class="flex h-11 flex-shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"><i class="fas fa-paper-plane text-xs"></i><span class="hidden sm:inline">Send</span></button>
                </form>
                @error('message')<p class="flex-shrink-0 px-4 pb-3 text-sm text-rose-600">{{ $message }}</p>@enderror
            @else
                <div class="flex h-full flex-col items-center justify-center bg-slate-50 px-6 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-xl text-emerald-600 shadow-sm ring-1 ring-slate-200"><i class="fas fa-comments"></i></span>
                    <p class="mt-5 text-base font-semibold text-slate-900">Choose a conversation</p>
                    <p class="mt-1 max-w-xs text-sm leading-6 text-slate-500">Pick a customer on the left to read and reply. Customers start a conversation from one of their orders.</p>
                </div>
            @endif
        </section>
    </div>
</div>
@endsection

@if ($open)
@push('scripts')
<script>
    (() => {
        const thread = document.getElementById('thread');
        const form = document.getElementById('reply-form');
        const box = document.getElementById('reply');
        const send = form.querySelector('button[type="submit"]');
        const messagesUrl = @json(route('api.chat.messages', $open));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const myId = {{ (int) $me->id }};
        let lastId = {{ $lastId }};

        const toBottom = () => { thread.scrollTop = thread.scrollHeight; };

        function show(message) {
            document.getElementById('thread-empty')?.remove();

            const row = document.createElement('div');
            row.className = 'flex ' + (message.from_customer ? 'justify-start' : 'justify-end');
            row.dataset.message = message.id;

            const wrap = document.createElement('div');
            wrap.className = 'max-w-[85%] sm:max-w-[70%]';

            const bubble = document.createElement('p');
            bubble.className = 'whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-[15px] leading-6 '
                + (message.from_customer ? 'rounded-bl-md border border-slate-200 bg-white text-slate-800' : 'rounded-br-md bg-emerald-600 text-white');
            bubble.textContent = message.message;

            const time = new Date(message.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            const note = document.createElement('p');
            note.className = 'mt-1 text-[11px] text-slate-400' + (message.from_customer ? '' : ' text-right');

            if (message.from_customer) {
                note.textContent = time;
            } else {
                const who = document.createElement('span');
                who.className = 'font-medium text-slate-500';
                who.textContent = message.sender_id === myId ? 'You' : `${message.sender_name} · ${message.sender_role}`;
                note.append(who, ' · ' + time);
            }

            wrap.append(bubble, note);
            row.append(wrap);
            thread.append(row);
        }

        // Anything newer than what is on screen is added; fetching it marks the customer's messages as read.
        function refresh() {
            return fetch(messagesUrl, { headers: { Accept: 'application/json' } })
                .then(response => response.json())
                .then(data => {
                    const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 120;
                    const fresh = (data.messages || []).filter(message => message.id > lastId);

                    fresh.forEach(message => { show(message); lastId = message.id; });
                    if (fresh.length && nearBottom) toBottom();
                })
                .catch(() => {});
        }

        // Sends without leaving the page. If that fails, the form is sent the ordinary way.
        form.addEventListener('submit', event => {
            const text = box.value.trim();
            event.preventDefault();
            if (!text) return;

            send.disabled = true;

            fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ message: text }),
            })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(() => {
                    box.value = '';
                    box.style.height = '';
                    return refresh().then(toBottom);
                })
                .catch(() => form.submit())
                .finally(() => { send.disabled = false; box.focus(); });
        });

        // Enter sends, Shift+Enter makes a new line. The box grows with the text.
        box.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); }
        });
        box.addEventListener('input', () => { box.style.height = ''; box.style.height = Math.min(box.scrollHeight, 128) + 'px'; });

        toBottom();
        setInterval(refresh, 4000);
    })();
</script>
@endpush
@endif
