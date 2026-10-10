@extends('layouts.app')

@section('title', 'Messages')
@section('header_title', 'Messages')

@php
    $pharmacy = $conversation->pharmacy;
    $lastId = (int) ($messages->last()?->id ?? 0);
@endphp

@section('content')
{{-- The thread fills the screen below the top bar: the messages scroll, the reply box stays put. --}}
<div class="mx-auto -my-8 flex h-[calc(100vh-4.25rem)] max-w-[820px] flex-col py-3 sm:h-[calc(100vh-4.75rem)] sm:py-5" style="height: calc(var(--vh, 1vh) * 100 - 4.5rem)">
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <header class="flex items-center gap-3 border-b border-slate-100 p-3 sm:px-5 sm:py-4">
            <a href="{{ route('user.chat') }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900" aria-label="Back to all messages"><i class="fas fa-arrow-left"></i></a>
            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas fa-store"></i></span>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[15px] font-bold text-slate-900">{{ $pharmacy?->displayName() ?? 'Pharmacy' }}</h1>
                <p class="truncate text-xs text-slate-500">
                    @if ($conversation->order)
                        About order <a href="{{ route('user.orders.show', $conversation->order) }}" class="font-mono font-semibold text-emerald-700 hover:text-emerald-800">{{ $conversation->order->order_number }}</a>
                    @else
                        {{ $pharmacy?->fullAddress() }}
                    @endif
                </p>
            </div>
            @if ($pharmacy?->phone)
                <a href="tel:{{ $pharmacy->phone }}" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-emerald-300 hover:text-emerald-700" aria-label="Call {{ $pharmacy->name }}"><i class="fas fa-phone text-sm"></i></a>
            @endif
        </header>

        <div id="thread" class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4 sm:p-5" aria-live="polite">
            @forelse ($messages as $message)
                @php $mine = ! $message->from_pharmacy; @endphp
                <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-message="{{ $message->id }}">
                    <div class="max-w-[82%] sm:max-w-[70%]">
                        <p class="whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-[15px] leading-6 {{ $mine ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800' }}">{{ $message->message }}</p>
                        <p class="mt-1 text-[11px] text-slate-400 {{ $mine ? 'text-right' : '' }}">{{ $message->created_at->isToday() ? $message->created_at->format('g:i A') : $message->created_at->format('M j, g:i A') }}</p>
                    </div>
                </div>
            @empty
                <div id="thread-empty" class="flex h-full flex-col items-center justify-center px-6 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 ring-1 ring-slate-200"><i class="fas fa-comment-dots"></i></span>
                    <p class="mt-4 text-sm font-semibold text-slate-900">Start the conversation</p>
                    <p class="mt-1 max-w-xs text-sm text-slate-500">Ask the pharmacy about your order, when it will be ready, or how to find them.</p>
                </div>
            @endforelse
        </div>

        <form id="reply-form" action="{{ route('user.chat.store', $conversation) }}" method="POST" class="flex items-end gap-2 border-t border-slate-100 p-3 sm:p-4">
            @csrf
            <label for="reply" class="sr-only">Your message</label>
            <textarea id="reply" name="message" rows="1" maxlength="1000" required placeholder="Write a message…" class="max-h-32 min-h-[2.75rem] flex-1 resize-none rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-[15px] text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100"></textarea>
            <button type="submit" class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white transition hover:bg-emerald-700 disabled:opacity-60" aria-label="Send message"><i class="fas fa-paper-plane text-sm"></i></button>
        </form>
        @error('message')<p class="px-4 pb-3 text-sm text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>
@endsection

@push('scripts')
<script>
    (() => {
        const thread = document.getElementById('thread');
        const form = document.getElementById('reply-form');
        const box = document.getElementById('reply');
        const send = form.querySelector('button[type="submit"]');
        const messagesUrl = @json(route('api.chat.messages', [$conversation, 'as' => 'customer']));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        let lastId = {{ $lastId }};

        const toBottom = () => { thread.scrollTop = thread.scrollHeight; };

        function show(message) {
            document.getElementById('thread-empty')?.remove();

            const row = document.createElement('div');
            row.className = 'flex ' + (message.from_customer ? 'justify-end' : 'justify-start');
            row.dataset.message = message.id;

            const wrap = document.createElement('div');
            wrap.className = 'max-w-[82%] sm:max-w-[70%]';

            const bubble = document.createElement('p');
            bubble.className = 'whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-[15px] leading-6 '
                + (message.from_customer ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800');
            bubble.textContent = message.message;

            const time = document.createElement('p');
            time.className = 'mt-1 text-[11px] text-slate-400' + (message.from_customer ? ' text-right' : '');
            time.textContent = new Date(message.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

            wrap.append(bubble, time);
            row.append(wrap);
            thread.append(row);
        }

        // Anything newer than what is on screen is added; reading it marks it read.
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
            if (!text) { event.preventDefault(); return; }

            event.preventDefault();
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
