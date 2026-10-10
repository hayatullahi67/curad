{{-- The fields of a branch, used for both "Add branch" and "Edit branch". $branch is null when adding. --}}
@php
    $field = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
    $prefix = $branch ? 'edit' : 'new';
    $value = fn (string $name) => old($name, $branch?->{$name});
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-name" class="mb-1.5 block text-sm font-semibold text-slate-800">Branch name</label>
        <input id="{{ $prefix }}-name" type="text" name="name" value="{{ $value('name') }}" required maxlength="255" placeholder="e.g. Ikeja Branch" class="{{ $field }}">
    </div>

    {{-- Where it is: the map fills the address in, or the address finds the map position. --}}
    <div class="sm:col-span-2">
        @include('partials.location-picker', ['latitude' => $value('latitude'), 'longitude' => $value('longitude')])
    </div>

    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-address" class="mb-1.5 block text-sm font-semibold text-slate-800">Street address</label>
        <input id="{{ $prefix }}-address" type="text" name="address" value="{{ $value('address') }}" required maxlength="255" placeholder="House number, street and area" data-place="address" class="{{ $field }}">
    </div>
    <div>
        <label for="{{ $prefix }}-city" class="mb-1.5 block text-sm font-semibold text-slate-800">City or area</label>
        <input id="{{ $prefix }}-city" type="text" name="city" value="{{ $value('city') }}" maxlength="120" placeholder="e.g. Ikeja" data-place="city" class="{{ $field }}">
    </div>
    <div>
        <label for="{{ $prefix }}-state" class="mb-1.5 block text-sm font-semibold text-slate-800">State</label>
        <input id="{{ $prefix }}-state" type="text" name="state" value="{{ $value('state') }}" maxlength="120" placeholder="e.g. Lagos" data-place="state" class="{{ $field }}">
    </div>

    <div>
        <label for="{{ $prefix }}-phone" class="mb-1.5 block text-sm font-semibold text-slate-800">Phone number</label>
        <input id="{{ $prefix }}-phone" type="tel" name="phone" value="{{ $value('phone') }}" required maxlength="30" inputmode="tel" placeholder="08012345678" class="{{ $field }}">
    </div>
    <div>
        <label for="{{ $prefix }}-hours" class="mb-1.5 block text-sm font-semibold text-slate-800">Opening hours <span class="font-normal text-slate-400">(optional)</span></label>
        <input id="{{ $prefix }}-hours" type="text" name="operating_hours" value="{{ $value('operating_hours') }}" maxlength="255" placeholder="Mon–Sat, 8am–9pm" class="{{ $field }}">
    </div>
</div>
