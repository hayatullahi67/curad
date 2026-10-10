{{--
    Which branch the owner is working in, and a menu to move to another.
    Staff belong to one branch, so they only see its name.
--}}
@php
    $switcherUser = Auth::user();
    $switcherBranch = in_array($switcherUser->role, ['vendor', 'sales_person'], true) ? \App\Models\Pharmacy::current() : null;
    $switcherBranches = $switcherUser->role === 'vendor' ? \App\Models\Pharmacy::branchesOf($switcherUser)->get(['id', 'name', 'is_main', 'city']) : collect();
@endphp
@if ($switcherBranch && $switcherBranches->count() > 1)
    <details class="relative" id="branch-switcher">
        <summary class="flex h-10 max-w-[9.5rem] cursor-pointer list-none items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 sm:max-w-[14rem] sm:px-3 [&::-webkit-details-marker]:hidden" aria-label="Branch: {{ $switcherBranch->name }}. Change branch">
            <i class="fas fa-store flex-shrink-0 text-xs text-emerald-600"></i>
            <span class="hidden truncate sm:inline">{{ $switcherBranch->name }}</span>
            <i class="fas fa-chevron-down flex-shrink-0 text-[10px] text-slate-400"></i>
        </summary>

        <div class="absolute right-0 z-50 mt-2 w-72 max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10">
            <p class="border-b border-slate-100 px-4 py-3 text-xs font-medium text-slate-500">The till, stock, orders and reports all show the branch you choose here.</p>
            <ul class="max-h-72 overflow-y-auto py-1.5">
                @foreach ($switcherBranches as $option)
                    @php $isHere = $option->id === $switcherBranch->id; @endphp
                    <li>
                        <form action="{{ route('vendor.branches.switch', $option) }}" method="POST">
                            @csrf
                            <input type="hidden" name="to" value="{{ request()->isMethod('GET') && ! request()->routeIs('vendor.branches.show', 'vendor.sales.show') ? url()->current() : '' }}">
                            <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition {{ $isHere ? 'bg-emerald-50' : 'hover:bg-slate-50' }}" @disabled($isHere)>
                                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg {{ $isHere ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-500' }}"><i class="fas {{ $isHere ? 'fa-check' : 'fa-store' }} text-xs"></i></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $option->name }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $option->is_main ? 'Main branch' : ($option->city ?: 'Branch') }}</span>
                                </span>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('vendor.branches') }}" class="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                All branches <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </details>
    <script>
        // The branch menu closes when you click anywhere else or press Escape.
        (function () {
            const menu = document.getElementById('branch-switcher');
            document.addEventListener('click', event => { if (menu.open && !menu.contains(event.target)) menu.open = false; });
            document.addEventListener('keydown', event => { if (event.key === 'Escape') menu.open = false; });
        })();
    </script>
@elseif ($switcherBranch && $switcherUser->role === 'sales_person')
    <span class="hidden h-10 max-w-[14rem] items-center gap-2 rounded-xl bg-slate-100 px-3 text-sm font-semibold text-slate-600 sm:flex" title="Your branch">
        <i class="fas fa-store flex-shrink-0 text-xs text-slate-400"></i><span class="truncate">{{ $switcherBranch->name }}</span>
    </span>
@endif
