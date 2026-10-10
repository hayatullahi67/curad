@extends('layouts.app')

@section('title', 'Staff Access')
@section('header_title', 'Staff Access')

@php
    $money = fn ($amount) => '₦' . number_format((float) $amount, 2);
    $branchNames = $branches->pluck('name', 'id');
    $manyBranches = $branches->count() > 1;
    $activeCount = $staffMembers->where('is_active', true)->count();
    $resetCount = $pendingResets->count();
    $field = 'h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
    $roleTones = [
        'branch_manager' => 'bg-emerald-50 text-emerald-700',
        'pharmacist' => 'bg-sky-50 text-sky-700',
        'inventory_officer' => 'bg-teal-50 text-teal-700',
        'finance' => 'bg-violet-50 text-violet-700',
    ];
    // Staff grouped under the branch they work in, in the same order as the Branches page.
    $groups = $branches->map(fn ($branch) => [$branch, $staffMembers->where('assigned_pharmacy_id', $branch->id)])
        ->push([null, $staffMembers->whereNotIn('assigned_pharmacy_id', $branches->pluck('id'))])
        ->filter(fn ($group) => $group[1]->isNotEmpty() || ($group[0] && $manyBranches));
@endphp

@section('content')
<div class="mx-auto -mt-3 max-w-[1180px] space-y-5">

    {{-- ============ Top bar ============ --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-500">
            Give each person their own login. They sign in on the same page as you. A staff member works in one branch only, and their role decides what they can do there.
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-cura-modal-open="add-staff-modal" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700">
                <i class="fas fa-user-plus text-xs"></i> Add staff
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5 text-rose-600"></i>
            <ul class="space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- ============ Key numbers ============ --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl bg-slate-900 p-4 text-white shadow-sm sm:p-5">
            <div class="pointer-events-none absolute -right-8 -top-8 h-28 w-28 rounded-full bg-emerald-500/25 blur-2xl"></div>
            <p class="relative text-[13px] font-medium text-slate-300">Active staff</p>
            <p class="relative mt-3 text-[26px] font-bold leading-none sm:text-[28px]">{{ $activeCount }}</p>
            <p class="relative mt-2 text-xs text-slate-400">{{ $staffMembers->count() - $activeCount }} deactivated</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-[13px] font-medium text-slate-500">Branches</p>
            <p class="mt-3 text-[26px] font-bold leading-none text-slate-900 sm:text-[28px]">{{ $branches->count() }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">Staff see only their own</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-[13px] font-medium text-slate-500">Can use the till</p>
            <p class="mt-3 text-[26px] font-bold leading-none text-slate-900 sm:text-[28px]">{{ $staffMembers->where('is_active', true)->filter(fn ($staff) => \App\Support\StaffRoles::canSell($staff->staff_role))->count() }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">Cashiers, pharmacists, managers</p>
        </div>
        <div class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5 {{ $resetCount ? 'border-amber-300' : 'border-slate-200' }}">
            <p class="text-[13px] font-medium text-slate-500">Password requests</p>
            <p class="mt-3 text-[26px] font-bold leading-none sm:text-[28px] {{ $resetCount ? 'text-amber-600' : 'text-slate-900' }}">{{ $resetCount }}</p>
            <p class="mt-2 truncate text-xs text-slate-500">{{ $resetCount ? 'Waiting for a new password' : 'Nobody is locked out' }}</p>
        </div>
    </section>

    {{-- ============ Staff, branch by branch ============ --}}
    @if ($staffMembers->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-user-group"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">No staff yet</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">Add a cashier or pharmacist so they can sell on their own login, and you can see who sold what.</p>
            <button type="button" data-cura-modal-open="add-staff-modal" class="mt-6 inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700"><i class="fas fa-user-plus text-xs"></i> Add staff</button>
        </div>
    @else
        @foreach ($groups as [$branch, $members])
            <section>
                @if ($manyBranches || ! $branch)
                    <h2 class="mb-2 flex items-center gap-2 px-1 text-sm font-semibold text-slate-900">
                        <i class="fas fa-store text-xs text-slate-400"></i>{{ $branch->name ?? 'No branch assigned' }}
                        <span class="rounded-md bg-slate-200/70 px-1.5 py-0.5 text-xs font-semibold text-slate-600">{{ $members->count() }}</span>
                    </h2>
                @endif

                @if ($members->isEmpty())
                    <p class="rounded-2xl border border-dashed border-slate-300 bg-white/60 px-4 py-6 text-center text-sm text-slate-500">Nobody works in this branch yet.</p>
                @else
                    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        @foreach ($members as $staff)
                            @php
                                $summary = $salesSummary->get($staff->id);
                                $wantsReset = $pendingResets->has($staff->id);
                                $sells = \App\Support\StaffRoles::canSell($staff->staff_role);
                            @endphp
                            <li class="flex flex-col gap-3 p-4 sm:px-5 lg:flex-row lg:items-center {{ $staff->is_active ? '' : 'bg-slate-50/70' }}">
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $staff->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-200 text-slate-500' }}">{{ strtoupper(mb_substr($staff->full_name ?? 'S', 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="truncate text-sm font-semibold {{ $staff->is_active ? 'text-slate-900' : 'text-slate-500' }}">{{ $staff->full_name }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $roleTones[$staff->staff_role] ?? 'bg-slate-100 text-slate-600' }}">{{ \App\Support\StaffRoles::label($staff->staff_role) }}</span>
                                            @unless ($staff->is_active)
                                                <span class="rounded-full bg-slate-200 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Deactivated</span>
                                            @endunless
                                            @if ($wantsReset)
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800"><i class="fas fa-key mr-1 text-[9px]"></i>Asked for a new password</span>
                                            @endif
                                        </p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $staff->email }}@if ($staff->phone) · {{ $staff->phone }}@endif</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between gap-4 lg:justify-end">
                                    <p class="text-xs text-slate-500 lg:w-36 lg:text-right">
                                        @if ($sells)
                                            <span class="block text-sm font-semibold text-slate-900">{{ $money($summary->total_amount ?? 0) }}</span>
                                            {{ (int) ($summary->sale_count ?? 0) }} {{ Str::plural('sale', (int) ($summary->sale_count ?? 0)) }}
                                        @else
                                            No till access
                                        @endif
                                    </p>

                                    <div class="flex flex-shrink-0 items-center gap-1.5">
                                        @if ($sells)
                                            <a href="{{ route('vendor.sales-staff.history', $staff->id) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:border-slate-300">Sales</a>
                                        @endif
                                        <button type="button" class="inline-flex h-9 items-center rounded-lg border px-3 text-xs font-semibold transition {{ $wantsReset ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300' }}"
                                                data-manage
                                                data-name="{{ $staff->full_name }}"
                                                data-role="{{ $staff->staff_role }}"
                                                data-branch="{{ $staff->assigned_pharmacy_id }}"
                                                data-active="{{ $staff->is_active ? 1 : 0 }}"
                                                data-assign-url="{{ route('vendor.sales-staff.assign', $staff->id) }}"
                                                data-reset-url="{{ route('vendor.sales-staff.reset-password', $staff->id) }}"
                                                data-login-url="{{ route('vendor.sales-staff.login-as', $staff->id) }}"
                                                data-toggle-url="{{ $staff->is_active ? route('vendor.sales-staff.destroy', $staff->id) : route('vendor.sales-staff.reactivate', $staff->id) }}">
                                            Manage
                                        </button>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    @endif

    {{-- ============ What each role can do ============ --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <h2 class="text-base font-bold text-slate-900">What each role can do</h2>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($roleOptions as $key => $role)
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-sm font-semibold text-slate-900">{{ $role['label'] }}</dt>
                    <dd class="mt-1 text-xs leading-5 text-slate-600">{{ $role['summary'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>

{{-- Add staff --}}
<x-modal id="add-staff-modal" title="Add staff" max-width="xl">
    <form action="{{ route('vendor.sales-staff.store') }}" method="POST" id="add-staff-form" data-once>
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="staff-name" class="mb-1.5 block text-sm font-semibold text-slate-800">Full name</label>
                <input id="staff-name" type="text" name="full_name" value="{{ old('full_name') }}" required maxlength="255" placeholder="Ada Okafor" class="{{ $field }}">
            </div>
            <div>
                <label for="staff-email" class="mb-1.5 block text-sm font-semibold text-slate-800">Email (their login)</label>
                <input id="staff-email" type="email" name="email" value="{{ old('email') }}" required placeholder="ada@yourpharmacy.com" class="{{ $field }}">
            </div>
            <div>
                <label for="staff-phone" class="mb-1.5 block text-sm font-semibold text-slate-800">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="staff-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" inputmode="tel" placeholder="08012345678" class="{{ $field }}">
            </div>
            <div>
                <label for="staff-role" class="mb-1.5 block text-sm font-semibold text-slate-800">Role</label>
                <select id="staff-role" name="staff_role" required class="{{ $field }}" data-role-select>
                    <option value="" disabled @selected(! old('staff_role'))>Choose a role</option>
                    @foreach ($roleOptions as $key => $role)
                        <option value="{{ $key }}" data-summary="{{ $role['summary'] ?? '' }}" @selected(old('staff_role') === $key)>{{ $role['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="staff-branch" class="mb-1.5 block text-sm font-semibold text-slate-800">Branch</label>
                <select id="staff-branch" name="branch_id" required class="{{ $field }}">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) old('branch_id', $pharmacy->id ?? 0) === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <p class="hidden rounded-xl bg-emerald-50 px-3.5 py-2.5 text-xs leading-5 text-emerald-900 sm:col-span-2" data-role-summary></p>
            <div class="sm:col-span-2">
                <label for="staff-password" class="mb-1.5 block text-sm font-semibold text-slate-800">First password</label>
                <input id="staff-password" type="text" name="password" required minlength="6" autocomplete="off" placeholder="At least 6 characters" class="{{ $field }}">
                <p class="mt-1.5 text-xs text-slate-500">You give this to them. It is shown to you once more after saving.</p>
            </div>
        </div>
    </form>
    <x-slot:footer>
        <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
            <button type="submit" form="add-staff-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Create login</button>
        </div>
    </x-slot:footer>
</x-modal>

{{-- Manage one staff member --}}
<x-modal id="manage-staff-modal" title="Manage staff" max-width="xl">
    <p class="text-sm text-slate-600">Changes for <span id="manage-name" class="font-semibold text-slate-900"></span>.</p>

    <form method="POST" id="assign-form" data-once class="mt-5 rounded-xl border border-slate-200 p-4">
        @csrf
        <p class="text-sm font-semibold text-slate-900">Role and branch</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div>
                <label for="manage-role" class="mb-1.5 block text-xs font-semibold text-slate-600">Role</label>
                <select id="manage-role" name="staff_role" required class="{{ $field }}">
                    @foreach ($roleOptions as $key => $role)
                        <option value="{{ $key }}">{{ $role['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="manage-branch" class="mb-1.5 block text-xs font-semibold text-slate-600">Branch</label>
                <select id="manage-branch" name="branch_id" required class="{{ $field }}">
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="mt-3 h-10 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Save role and branch</button>
    </form>

    <form method="POST" id="reset-form" data-once class="mt-3 rounded-xl border border-slate-200 p-4">
        @csrf
        <label for="manage-password" class="text-sm font-semibold text-slate-900">Set a new password</label>
        <div class="mt-3 flex gap-2">
            <input id="manage-password" type="text" name="new_password" required minlength="6" autocomplete="off" placeholder="At least 6 characters" class="{{ $field }} min-w-0 flex-1">
            <button type="submit" class="h-11 flex-shrink-0 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-800">Save</button>
        </div>
    </form>

    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <form method="POST" id="login-as-form" data-once>
            @csrf
            <button type="submit" class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 transition hover:border-slate-300"><i class="fas fa-user-secret text-xs text-slate-400"></i> See what they see</button>
        </form>
        <form method="POST" id="toggle-form" data-once>
            @csrf
            <input type="hidden" name="_method" id="toggle-method" value="DELETE">
            <button type="submit" id="toggle-button" class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border text-sm font-semibold transition"></button>
        </form>
    </div>
</x-modal>
@endsection

@push('scripts')
<script>
    (function () {
        // Explains the chosen role under the role box.
        document.querySelectorAll('[data-role-select]').forEach(select => {
            const summary = select.form.querySelector('[data-role-summary]');
            const show = () => {
                const text = select.selectedOptions[0]?.dataset.summary || '';
                summary.textContent = text;
                summary.classList.toggle('hidden', !text);
            };
            select.addEventListener('change', show);
            show();
        });

        document.querySelectorAll('[data-manage]').forEach(button => {
            button.addEventListener('click', () => {
                const data = button.dataset;
                const active = data.active === '1';

                document.getElementById('manage-name').textContent = data.name;
                document.getElementById('assign-form').action = data.assignUrl;
                document.getElementById('reset-form').action = data.resetUrl;
                document.getElementById('login-as-form').action = data.loginUrl;
                document.getElementById('login-as-form').classList.toggle('hidden', !active);
                document.getElementById('manage-role').value = data.role || '';
                document.getElementById('manage-branch').value = data.branch || '';
                document.getElementById('manage-password').value = '';

                const toggle = document.getElementById('toggle-button');
                document.getElementById('toggle-form').action = data.toggleUrl;
                document.getElementById('toggle-method').value = active ? 'DELETE' : 'POST';
                toggle.innerHTML = active ? '<i class="fas fa-user-slash text-xs"></i> Deactivate login' : '<i class="fas fa-user-check text-xs"></i> Reactivate login';
                toggle.className = 'flex h-11 w-full items-center justify-center gap-2 rounded-xl border text-sm font-semibold transition '
                    + (active ? 'border-rose-200 bg-white text-rose-700 hover:bg-rose-50' : 'border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50');

                CuraModal.open('manage-staff-modal');
            });
        });

        document.getElementById('toggle-form').addEventListener('submit', event => {
            if (document.getElementById('toggle-method').value === 'DELETE' && !confirm('Deactivate this login? They will not be able to sign in until you reactivate it.')) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });

        document.querySelectorAll('form[data-once]').forEach(form => {
            form.addEventListener('submit', () => {
                document.querySelectorAll('button[type="submit"]').forEach(button => {
                    if (button.form === form) { button.disabled = true; button.classList.add('opacity-60', 'cursor-wait'); }
                });
            });
        });
    })();
</script>
@if ($errors->any() && old('full_name') !== null)
    <script>document.addEventListener('DOMContentLoaded', () => CuraModal.open('add-staff-modal'));</script>
@endif
@endpush
