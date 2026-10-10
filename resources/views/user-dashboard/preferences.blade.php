@extends('layouts.app')

@section('title', 'Notification choices')
@section('header_title', 'Notifications')

@section('content')
<div class="mx-auto -mt-3 max-w-[720px] space-y-5">
    <a href="{{ route('user.notifications') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-slate-900"><i class="fas fa-arrow-left text-xs"></i>Back to notifications</a>

    <form action="{{ route('user.notifications.preferences.save') }}" method="POST" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @csrf
        <header class="border-b border-slate-100 p-4 sm:p-6">
            <h1 class="text-base font-bold text-slate-900">What should we tell you about?</h1>
            <p class="mt-0.5 text-sm leading-6 text-slate-500">Switch off anything you do not want to hear about.</p>
        </header>

        <ul class="divide-y divide-slate-100">
            @foreach ($options as $key => [$title, $text, $icon])
                <li>
                    <label for="pref-{{ $key }}" class="flex cursor-pointer items-center gap-3 p-4 transition hover:bg-slate-50 sm:gap-4 sm:px-6">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><i class="fas {{ $icon }} text-sm"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900">{{ $title }}</span>
                            <span class="mt-0.5 block text-[13px] leading-5 text-slate-500">{{ $text }}</span>
                        </span>
                        <input id="pref-{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked($settings[$key]) class="peer sr-only">
                        <span class="relative h-6 w-11 flex-shrink-0 rounded-full bg-slate-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:bg-emerald-600 peer-checked:after:left-[22px] peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100" aria-hidden="true"></span>
                    </label>
                </li>
            @endforeach
        </ul>

        <footer class="flex justify-end border-t border-slate-100 bg-slate-50/70 px-4 py-3 sm:px-6">
            <button type="submit" class="inline-flex h-11 w-full items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 sm:w-auto">Save choices</button>
        </footer>
    </form>
</div>
@endsection
