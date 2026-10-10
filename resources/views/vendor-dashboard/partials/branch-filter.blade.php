{{--
    Lets the owner look at online orders for all branches together or for one
    branch. Needs: $scope (App\Support\BranchScope), $route (route name),
    $keep (other address parts to keep), $waiting (branch id => orders needing action).
--}}
@if ($scope->canChoose())
    @php
        $chip = 'inline-flex h-10 flex-shrink-0 items-center gap-2 rounded-xl border px-3.5 text-sm font-semibold transition';
        $on = 'border-slate-900 bg-slate-900 text-white';
        $off = 'border-slate-200 bg-white text-slate-700 hover:border-slate-300';
        $badge = fn (bool $active, int $count) => 'rounded-md px-1.5 py-0.5 text-xs ' . ($active ? 'bg-white/20 text-white' : ($count ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'));
        $everything = (int) collect($waiting)->sum();
    @endphp
    <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden" aria-label="Choose a branch">
        <a href="{{ route($route, $keep) }}" class="{{ $chip }} {{ $scope->selected ? $off : $on }}" @unless ($scope->selected) aria-current="true" @endunless>
            <i class="fas fa-layer-group text-xs opacity-70"></i>All branches
            <span class="{{ $badge(! $scope->selected, $everything) }}">{{ $everything }}</span>
        </a>
        @foreach ($scope->branches as $branch)
            @php $active = $scope->selected?->id === $branch->id; $count = (int) ($waiting[$branch->id] ?? 0); @endphp
            <a href="{{ route($route, $keep + ['branch' => $branch->id]) }}" class="{{ $chip }} {{ $active ? $on : $off }}" @if ($active) aria-current="true" @endif>
                <i class="fas fa-store text-xs opacity-70"></i>{{ $branch->name }}
                <span class="{{ $badge($active, $count) }}" title="{{ $count }} needing action">{{ $count }}</span>
            </a>
        @endforeach
    </nav>
@endif
