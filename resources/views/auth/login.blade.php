@extends('layouts.auth')

@section('title', 'Sign in')
@section('aside_eyebrow', 'Welcome back')
@section('aside_title', 'Good to see you again.')

@section('content')
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Sign in to CuraD</h1>
    <p class="mt-2 text-sm text-slate-600">New here? <a href="{{ route('register') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Create a free account</a></p>

    @if (session('status') || session('success'))
        <div class="mt-6 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm text-emerald-900" role="status">
            <i class="fas fa-circle-check mt-0.5 text-emerald-600"></i>
            <p>{{ session('status') ?? session('success') }}</p>
        </div>
    @endif

    @if (session('error') || $errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <p>{{ session('error') ?? $errors->first() }}</p>
        </div>
    @endif

    <form action="{{ route('login.post') }}" method="POST" data-auth-form class="mt-7 space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" placeholder="you@example.com" class="auth-input @error('email') has-error @enderror">
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Your password" class="auth-input pr-12 @error('password') has-error @enderror">
                <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-400 hover:text-slate-700"><i class="fas fa-eye"></i></button>
            </div>
        </div>

        <label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            Keep me signed in on this device
        </label>

        <button type="submit" data-busy="Signing in…" class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Sign in</button>
    </form>

    <div class="mt-8 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
        <p><i class="fas fa-store mr-1.5 text-slate-400"></i>Own a pharmacy? <a href="{{ route('register', ['as' => 'pharmacy']) }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Register it on CuraD</a></p>
        <p class="mt-1.5"><i class="fas fa-user-tag mr-1.5 text-slate-400"></i>Pharmacy staff sign in here too, in any branch, with the email their manager set up.</p>
    </div>
@endsection
