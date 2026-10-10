{{--
    Supervisor sign-off for a void or refund started by staff who cannot
    approve it themselves. The manager types their own login here; they are
    only checked, not logged in, so the till stays with the cashier.

    $prefix  keeps input ids unique when two forms sit on one page
    $restore whether to refill the email after a failed attempt
--}}
<div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
    <div class="flex items-start gap-3">
        <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white"><i class="fas fa-user-shield text-sm"></i></span>
        <div>
            <p class="text-sm font-bold text-blue-950">Manager approval needed</p>
            <p class="mt-0.5 text-xs text-blue-800">Ask the owner or a supervisor to enter their own login below. You stay signed in.</p>
        </div>
    </div>
    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-manager-email" class="mb-1 block text-xs font-bold text-blue-950">Manager's email</label>
            <input id="{{ $prefix }}-manager-email" type="email" name="manager_email" required autocomplete="off" value="{{ $restore ? old('manager_email') : '' }}"
                class="w-full rounded-lg border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
        </div>
        <div>
            <label for="{{ $prefix }}-manager-password" class="mb-1 block text-xs font-bold text-blue-950">Manager's password</label>
            <input id="{{ $prefix }}-manager-password" type="password" name="manager_password" required autocomplete="new-password"
                class="w-full rounded-lg border border-blue-200 bg-white px-3 py-2.5 text-sm text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100">
        </div>
    </div>
</div>
