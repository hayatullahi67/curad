@extends('layouts.app')

@section('title', 'Help')
@section('header_title', 'Help')

@php
    $topics = [
        'Ordering' => [
            ['How do I order?', 'Open the Marketplace, find what you need and add it to your cart. At checkout you choose pickup or delivery, see the full total, then place the order. You can put products from different pharmacies in one cart.'],
            ['Why is my order coming from more than one pharmacy?', 'When no single pharmacy has everything in your cart, CuraD splits the order between the fewest pharmacies that can supply it. You still pay once, and you see every pharmacy before you pay.'],
            ['What does “Near me” do?', 'It uses your device’s location to show the closest pharmacies first and how far each one is by road. Your location is only kept while you are signed in on this device. You can turn it off at any time.'],
        ],
        'Pickup and delivery' => [
            ['How does pickup work?', 'When the pharmacy accepts your order, you get a notification and a pickup code on the order page. Show the code at the counter to collect.'],
            ['How is the delivery fee worked out?', 'It is a base charge, plus an amount for each kilometre and each minute of the rider’s trip from the pharmacies to your address. At busy times it is a little higher, and checkout tells you when that applies. You always see the fee before you pay. Pickup has no delivery fee.'],
            ['How do I find the pharmacy?', 'Every pharmacy on your order page and on a product page has “View on Google Maps” and “Get directions” links.'],
        ],
        'Paying and refunds' => [
            ['How can I pay?', 'From your CuraD wallet, using your 4-digit wallet PIN, or by paying when you collect. Set your PIN under Profile & Security.'],
            ['Can I cancel an order?', 'Yes, until a pharmacy accepts it. Open the order and press “Cancel order”. If you paid from your wallet, the money goes back to your wallet. After that, message the pharmacy from the order page.'],
            ['A pharmacy could not supply an item. What happens?', 'You are told straight away and you are not charged for that item. If you already paid from your wallet, that part is refunded to your wallet.'],
        ],
        'Your account' => [
            ['How do I change my password or PIN?', 'Go to Profile & Security. You need your current password for both.'],
            ['What is the watchlist for?', 'It keeps products you buy often, or ones that are out of stock, in one place with today’s price and stock.'],
            ['How do I talk to a pharmacy?', 'Open the order and press “Message” beside the pharmacy. Your conversations are under Messages.'],
        ],
    ];
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[820px] space-y-5">
    <p class="text-sm text-slate-500">Quick answers about ordering, collecting, paying and your account.</p>

    @foreach ($topics as $topic => $questions)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-4 py-3.5 text-sm font-bold text-slate-900 sm:px-6">{{ $topic }}</h2>
            @foreach ($questions as [$question, $answer])
                <details class="group border-b border-slate-100 last:border-b-0">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-4 text-[15px] font-semibold text-slate-800 transition hover:bg-slate-50 sm:px-6 [&::-webkit-details-marker]:hidden">
                        <span>{{ $question }}</span>
                        <i class="fas fa-chevron-down flex-shrink-0 text-xs text-slate-400 transition-transform group-open:rotate-180"></i>
                    </summary>
                    <p class="px-4 pb-5 text-sm leading-6 text-slate-600 sm:px-6">{{ $answer }}</p>
                </details>
            @endforeach
        </section>
    @endforeach

    <section class="flex flex-col gap-4 rounded-2xl bg-slate-900 p-5 text-white sm:flex-row sm:items-center sm:p-6">
        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-white/10 text-emerald-300"><i class="fas fa-comment-dots"></i></span>
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-bold">Question about an order?</h2>
            <p class="mt-1 text-sm leading-6 text-slate-300">The pharmacy supplying it can answer fastest. Open the order and press “Message”.</p>
        </div>
        <a href="{{ route('user.orders') }}" class="inline-flex h-11 flex-shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 text-sm font-semibold text-white transition hover:bg-emerald-400">My orders <i class="fas fa-arrow-right text-xs"></i></a>
    </section>
</div>
@endsection
