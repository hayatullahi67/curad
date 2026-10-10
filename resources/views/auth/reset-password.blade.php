@extends('layouts.auth')

@section('title', 'Choose a new password')
@section('aside_eyebrow', 'Account help')
@section('aside_title', 'Pick a password you have not used anywhere else.')

@section('content')
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="fas fa-lock text-lg"></i></span>
    <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Choose a new password</h1>
    <p class="mt-2 text-sm leading-6 text-slate-600">Use 8 or more characters, with at least one letter and one number.</p>

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <div>
                <p>{{ $errors->first() }}</p>
                @error('email')
                    <a href="{{ route('password.request') }}" class="mt-1 inline-block font-semibold underline">Ask for a new link</a>
                @enderror
            </div>
        </div>
    @endif

    <form action="{{ route('password.update') }}" method="POST" data-auth-form class="mt-7 space-y-5" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" inputmode="email" class="auth-input @error('email') has-error @enderror">
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-800">New password</label>
            <div class="relative">
                <input id="password" type="password" name="password" required autofocus autocomplete="new-password" class="auth-input pr-12 @error('password') has-error @enderror">
                <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-400 hover:text-slate-700"><i class="fas fa-eye"></i></button>
            </div>
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-800">Type the new password again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="auth-input">
        </div>

        <button type="submit" data-busy="Saving…" class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Save new password</button>
    </form>
@endsection
