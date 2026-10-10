@extends('layouts.auth')

@section('title', 'Forgot password')
@section('aside_eyebrow', 'Account help')
@section('aside_title', 'Locked out? It takes a minute to get back in.')

@section('content')
    <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><i class="fas fa-key text-lg"></i></span>
    <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Forgot your password?</h1>
    <p class="mt-2 text-sm leading-6 text-slate-600">Enter the email you sign in with. We will send you a link to choose a new password. If you are pharmacy staff, your pharmacy owner sets the new password for you instead.</p>

    @if (session('status'))
        <div class="mt-6 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm text-emerald-900" role="status">
            <i class="fas fa-envelope-circle-check mt-0.5 text-emerald-600"></i>
            <p>{{ session('status') }}</p>
        </div>
    @endif

    @if (session('dev_reset_link'))
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm text-amber-900">
            <p class="font-semibold"><i class="fas fa-screwdriver-wrench mr-1.5"></i>Test mode</p>
            <p class="mt-1">Email is not set up on this computer, so here is the link that would have been sent:</p>
            <a href="{{ session('dev_reset_link') }}" class="mt-2 inline-block break-all font-semibold text-emerald-700 underline">Choose a new password</a>
        </div>
    @endif

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <p>{{ $errors->first() }}</p>
        </div>
    @endif

    <form action="{{ route('password.email') }}" method="POST" data-auth-form class="mt-7 space-y-5" novalidate>
        @csrf
        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" inputmode="email" placeholder="you@example.com" class="auth-input @error('email') has-error @enderror">
        </div>

        <button type="submit" data-busy="Sending…" class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Send me the link</button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600"><a href="{{ route('login') }}" class="font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-arrow-left mr-1.5 text-xs"></i>Back to sign in</a></p>
@endsection
