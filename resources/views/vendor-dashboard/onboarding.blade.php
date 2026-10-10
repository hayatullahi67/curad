@extends('layouts.auth')

@section('title', 'Set up your pharmacy')
@section('aside_eyebrow', 'Last step')
@section('aside_title', 'Tell us where your pharmacy is, and you can start selling.')

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
            <input id="address" type="text" name="address" value="{{ old('address') }}" required maxlength="500" placeholder="House number, street and area" data-place="address" class="{{ $field }} @error('address') has-error @enderror">
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
