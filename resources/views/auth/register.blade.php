@extends('layouts.auth')

@php $chosenType = old('account_type', $accountType); @endphp

@section('title', 'Create account')
@section('aside_eyebrow', 'Join CuraD')
@section('aside_title', 'One account for shopping, or for running your pharmacy.')

@section('content')
    <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Create your account</h1>
    <p class="mt-2 text-sm text-slate-600">Already have one? <a href="{{ route('login') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Sign in</a></p>

    @if ($errors->any())
        <div class="mt-6 flex gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <p>Please fix the {{ $errors->count() === 1 ? 'field' : 'fields' }} marked below and try again.</p>
        </div>
    @endif

    <form action="{{ route('register.post') }}" method="POST" data-auth-form id="register-form" class="mt-7 space-y-5" novalidate>
        @csrf

        <fieldset>
            <legend class="mb-2 block text-sm font-semibold text-slate-800">I want to</legend>
            <div class="grid grid-cols-2 gap-3">
                @foreach ([
                    ['patient', 'fa-bag-shopping', 'Shop', 'Buy medicines and health products'],
                    ['vendor', 'fa-store', 'Sell', 'Register and run my pharmacy'],
                ] as [$value, $icon, $label, $hint])
                    <label class="relative cursor-pointer">
                        <input type="radio" name="account_type" value="{{ $value }}" class="peer sr-only" @checked($chosenType === $value)>
                        <span class="flex h-full flex-col rounded-xl border border-slate-300 bg-white p-3.5 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:ring-1 peer-checked:ring-emerald-600 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100 hover:border-slate-400">
                            <i class="fas {{ $icon }} text-emerald-600"></i>
                            <span class="mt-2 block text-sm font-semibold text-slate-900">{{ $label }}</span>
                            <span class="mt-0.5 block text-xs leading-4 text-slate-500">{{ $hint }}</span>
                        </span>
                        <i class="fas fa-circle-check absolute right-3 top-3 hidden text-emerald-600 peer-checked:block"></i>
                    </label>
                @endforeach
            </div>
            @error('account_type')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </fieldset>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-800">First name</label>
                <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name" maxlength="60" class="auth-input @error('first_name') has-error @enderror">
                @error('first_name')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-800">Last name</label>
                <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name" maxlength="60" class="auth-input @error('last_name') has-error @enderror">
                @error('last_name')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-800">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" inputmode="email" placeholder="you@example.com" class="auth-input @error('email') has-error @enderror">
            @error('email')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-800">Phone number</label>
            <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel" placeholder="08012345678" class="auth-input @error('phone') has-error @enderror">
            @error('phone')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        {{-- Only customers are asked here; a pharmacy gives its address on the next page. --}}
        <div id="address-field" class="{{ $chosenType === 'patient' ? '' : 'hidden' }}">
            <div class="mb-1.5 flex items-center justify-between gap-3">
                <label for="address" class="block text-sm font-semibold text-slate-800">Your address <span class="font-normal text-slate-400">(optional)</span></label>
                <button type="button" id="use-my-location" class="inline-flex flex-shrink-0 items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800"><i class="fas fa-location-crosshairs"></i><span>Use my location</span></button>
            </div>
            <input id="address" type="text" name="address" value="{{ old('address') }}" maxlength="500" placeholder="Start typing your street or area" data-address-suggest data-latitude="#address_latitude" data-longitude="#address_longitude" class="auth-input @error('address') has-error @enderror">
            <input type="hidden" id="address_latitude" name="address_latitude" value="{{ old('address_latitude') }}">
            <input type="hidden" id="address_longitude" name="address_longitude" value="{{ old('address_longitude') }}">
            @error('address')
                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
            @else
                <p id="address-help" class="mt-1.5 text-xs leading-5 text-slate-500">Pick your address from the list as you type, or press “Use my location”. It is filled in for you when you order a delivery, and you can change it then.</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-800">Password</label>
            <div class="relative">
                <input id="password" type="password" name="password" required autocomplete="new-password" aria-describedby="password-rules" class="auth-input pr-12 @error('password') has-error @enderror">
                <button type="button" data-toggle-password="password" aria-label="Show password" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-slate-400 hover:text-slate-700"><i class="fas fa-eye"></i></button>
            </div>
            <ul id="password-rules" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <li data-rule="length"><i class="far fa-circle mr-1"></i>8 or more characters</li>
                <li data-rule="letter"><i class="far fa-circle mr-1"></i>A letter</li>
                <li data-rule="number"><i class="far fa-circle mr-1"></i>A number</li>
            </ul>
            @error('password')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-800">Type the password again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="auth-input">
            <p id="password-match" class="mt-1.5 hidden text-sm text-rose-600">The two passwords do not match yet.</p>
        </div>

        <div>
            <label class="flex cursor-pointer items-start gap-2.5 text-sm leading-5 text-slate-700">
                <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                <span>I agree to CuraD's terms of use and to how it handles my personal information.</span>
            </label>
            @error('terms')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror
        </div>

        <button type="submit" data-busy="Creating your account…" class="w-full rounded-xl bg-emerald-600 px-5 py-3.5 text-[15px] font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">Create account</button>
    </form>
@endsection

@push('scripts')
    @include('partials.address-suggest-script')
    <script>
        // "Use my location": where the device is becomes the address in the box, which can still be corrected.
        (function () {
            const button = document.getElementById('use-my-location');
            const help = document.getElementById('address-help');
            const words = button.querySelector('span');
            const say = (message, bad) => { if (help) { help.textContent = message; help.className = 'mt-1.5 text-xs leading-5 ' + (bad ? 'text-rose-600' : 'text-emerald-700'); } };
            const done = () => { button.disabled = false; words.textContent = 'Use my location'; };

            button.addEventListener('click', () => {
                if (!navigator.geolocation) { say('This browser cannot share a location. Type your address instead.', true); return; }

                button.disabled = true;
                words.textContent = 'Finding you…';

                navigator.geolocation.getCurrentPosition(position => {
                    const latitude = position.coords.latitude.toFixed(6);
                    const longitude = position.coords.longitude.toFixed(6);

                    fetch(`{{ route('location.lookup') }}?lat=${latitude}&lng=${longitude}`, { headers: { Accept: 'application/json' } })
                        .then(response => response.ok ? response.json() : null)
                        .then(found => {
                            const address = found ? [found.address, found.city, found.state].filter((part, index, parts) => part && parts.indexOf(part) === index).join(', ') : '';

                            if (!address) { say('We found where you are but not its address. Type your address instead.', true); return; }

                            document.getElementById('address').value = address;
                            document.getElementById('address_latitude').value = latitude;
                            document.getElementById('address_longitude').value = longitude;
                            say('Filled in from your location. Check it and correct anything that is wrong.', false);
                        })
                        .catch(() => say('We could not look up your address. Type it instead.', true))
                        .finally(done);
                }, problem => {
                    done();
                    say(problem.code === 1 ? 'Location is blocked for this site. Allow it in your browser, or type your address.' : 'We could not read your location. Try again, or type your address.', true);
                }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
            });
        })();

        // The address is only asked of customers.
        document.querySelectorAll('input[name="account_type"]').forEach(choice => {
            choice.addEventListener('change', () => document.getElementById('address-field').classList.toggle('hidden', choice.value !== 'patient' || !choice.checked));
        });
    </script>
    <script>
        (function () {
            const password = document.getElementById('password');
            const confirmation = document.getElementById('password_confirmation');
            const mismatch = document.getElementById('password-match');
            const rules = {
                length: value => value.length >= 8,
                letter: value => /[A-Za-z]/.test(value),
                number: value => /\d/.test(value),
            };

            function showRules() {
                Object.entries(rules).forEach(([name, passes]) => {
                    const item = document.querySelector(`[data-rule="${name}"]`);
                    const ok = passes(password.value);
                    item.classList.toggle('text-emerald-700', ok);
                    item.classList.toggle('font-semibold', ok);
                    item.querySelector('i').className = (ok ? 'fas fa-circle-check' : 'far fa-circle') + ' mr-1';
                });
            }

            function showMismatch() {
                mismatch.classList.toggle('hidden', confirmation.value === '' || confirmation.value === password.value);
            }

            password.addEventListener('input', () => { showRules(); showMismatch(); });
            confirmation.addEventListener('input', showMismatch);
            showRules();
        })();
    </script>
@endpush
