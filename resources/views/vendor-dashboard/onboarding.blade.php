@extends('layouts.auth')

@section('title', 'Set up your pharmacy')
@section('aside_eyebrow', 'Last step')
@section('aside_title', 'Tell us where your pharmacy is, and you can start selling.')
@section('aside_points')
    @foreach ([
        ['done', 'Create your account', 'Done.'],
        ['now', 'Set up your pharmacy', 'Its name, a phone number and where it is.'],
        ['next', 'Add your products', 'Receive your stock and choose what to list.'],
        ['next', 'Start selling', 'At the counter with the POS, and online on the marketplace.'],
    ] as $index => [$state, $title, $text])
        <li class="flex gap-4">
            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $state === 'done' ? 'bg-emerald-500 text-white' : ($state === 'now' ? 'bg-white text-slate-900 ring-4 ring-emerald-500/30' : 'bg-white/10 text-slate-400') }}">
                @if ($state === 'done')<i class="fas fa-check text-xs"></i>@else{{ $index + 1 }}@endif
            </span>
            <span class="min-w-0">
                <span class="block font-semibold {{ $state === 'next' ? 'text-slate-300' : 'text-white' }}">{{ $title }}@if ($state === 'now')<span class="ml-2 rounded-full bg-emerald-500/20 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">You are here</span>@endif</span>
                <span class="mt-0.5 block text-sm leading-6 text-slate-400">{{ $text }}</span>
            </span>
        </li>
    @endforeach
@endsection
@section('aside_note', 'Your pharmacy details can be changed later in Settings.')

@php
    $field = 'auth-input';
@endphp

@section('content')
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="fas fa-store text-lg"></i></span>
    <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Set up your pharmacy</h1>
    <p class="mt-2 text-sm leading-6 text-slate-600">This becomes your main branch. You can add more branches later from the Branches page.</p>

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <ul class="space-y-0.5">@foreach ($errors->all() as $fieldError)<li>{{ $fieldError }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('vendor.onboarding.store') }}" method="POST" data-auth-form class="mt-7 space-y-5">
        @csrf

        <div>
            <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-800">Name of the pharmacy</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="e.g. HealthPlus Pharmacy" class="{{ $field }} @error('name') has-error @enderror">
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-800">Phone customers can call</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', Auth::user()->phone) }}" required maxlength="15" inputmode="tel" class="{{ $field }} @error('phone') has-error @enderror">
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Business email</label>
                <input id="email" type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required maxlength="255" class="{{ $field }} @error('email') has-error @enderror">
            </div>
        </div>

        {{-- Where it is --}}
        <div>
            <p class="mb-2 text-sm font-semibold text-slate-800">Where is it?</p>
            @include('partials.location-picker', ['latitude' => old('latitude'), 'longitude' => old('longitude')])
        </div>

        <div>
            <label for="address" class="mb-1.5 block text-sm font-semibold text-slate-800">Street address</label>
            <input id="address" type="text" name="address" value="{{ old('address') }}" required maxlength="500" placeholder="Start typing, then pick it from the list" data-place="address" data-address-suggest data-fill="address" data-latitude="[data-latitude]" data-longitude="[data-longitude]" data-city="[data-place=city]" data-state="[data-place=state]" class="{{ $field }} @error('address') has-error @enderror">
            <p class="mt-1.5 text-xs leading-5 text-slate-500">Add the house number if the list does not show it.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="city" class="mb-1.5 block text-sm font-semibold text-slate-800">City or area</label>
                <input id="city" type="text" name="city" value="{{ old('city') }}" maxlength="120" placeholder="e.g. Ikeja" data-place="city" class="{{ $field }}">
            </div>
            <div>
                <label for="state" class="mb-1.5 block text-sm font-semibold text-slate-800">State</label>
                <input id="state" type="text" name="state" value="{{ old('state') }}" maxlength="120" placeholder="e.g. Lagos" data-place="state" class="{{ $field }}">
            </div>
        </div>

        <button type="submit" data-busy="Setting up…" class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Finish and open my dashboard</button>
    </form>

    <form action="{{ route('logout') }}" method="POST" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-900">Sign out and finish later</button>
    </form>
@endsection

@push('scripts')
    @include('partials.location-picker-script')
@endpush
