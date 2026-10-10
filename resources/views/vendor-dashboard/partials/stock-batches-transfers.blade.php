{{-- Two tabs of the Stock page: every batch on the shelf with its expiry date, and stock moving between branches. --}}
@php
    $expiryStyles = [
        'expired' => ['Expired', 'bg-rose-50 text-rose-700 ring-rose-600/15'],
        'soon' => ['Expiring soon', 'bg-amber-50 text-amber-700 ring-amber-600/15'],
        'ok' => ['In date', 'bg-emerald-50 text-emerald-700 ring-emerald-600/15'],
        'none' => ['No expiry date', 'bg-slate-100 text-slate-600 ring-slate-500/15'],
    ];
    $transferStyles = [
        'in_transit' => 'bg-amber-50 text-amber-700 ring-amber-600/15',
        'received' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'cancelled' => 'bg-slate-100 text-slate-600 ring-slate-500/15',
    ];
    // What can be sent to another branch: free stock only (not expired, not held for an online order).
    $sendable = $products->map(fn ($product) => [
        'id' => $product->id,
        'label' => $product->name . ($product->dosage ? ' · ' . $product->dosage : ''),
        'free' => max(0, (int) $product->stock_level - (int) $product->expired_stock - (int) $product->reserved_stock),
    ])->where('free', '>', 0)->values();
@endphp

{{-- ======================= TAB: BATCHES & EXPIRY ======================= --}}
<div id="tab-batches" role="tabpanel" aria-labelledby="tab-btn-batches" data-tab-panel="batches" class="{{ $activeTab === 'batches' ? '' : 'hidden' }}">
    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border bg-white p-4 shadow-sm {{ $batchCounts['expired'] ? 'border-rose-200' : 'border-slate-200' }}">
            <p class="text-[13px] font-medium text-slate-500">Expired units on the shelf</p>
            <p class="mt-2 text-2xl font-bold leading-none {{ $batchCounts['expired'] ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($batchCounts['expired']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Blocked from the till and the marketplace</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[13px] font-medium text-slate-500">Expiring within 90 days</p>
            <p class="mt-2 text-2xl font-bold leading-none {{ $batchCounts['soon'] ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format($batchCounts['soon']) }}</p>
            <p class="mt-2 text-xs text-slate-500">Units to sell first</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[13px] font-medium text-slate-500">Batches in stock</p>
            <p class="mt-2 text-2xl font-bold leading-none text-slate-900">{{ number_format($batchCounts['all']) }}</p>
            <p class="mt-2 text-xs text-slate-500">The till always sells the oldest date first</p>
        </div>
    </div>

    @if ($batches->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-layer-group"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">No stock on the shelf yet</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">Each delivery you record becomes a batch here, with its own batch number and expiry date.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            {{-- Wide screens: a table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-500">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Batch</th>
                            <th class="px-5 py-3">Expiry date</th>
                            <th class="px-5 py-3 text-right">Units</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($batches as $batch)
                            @php [$stateLabel, $stateStyle] = $expiryStyles[$batch->expiryState()]; @endphp
                            <tr class="{{ $batch->expiryState() === 'expired' ? 'bg-rose-50/40' : '' }}">
                                <td class="px-5 py-3.5">
                                    <p class="font-medium text-slate-900">{{ $batch->medication->name ?? 'Product' }}</p>
                                    <p class="text-xs text-slate-500">{{ collect([$batch->medication?->dosage, $batch->medication?->sku])->filter()->implode(' · ') }}</p>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[13px] text-slate-700">{{ $batch->batch_number ?: '—' }}</td>
                                <td class="px-5 py-3.5 text-slate-700">
                                    @if ($batch->expiry_date)
                                        {{ $batch->expiry_date->format('M j, Y') }}
                                        <span class="block text-xs text-slate-500">{{ $batch->isExpired() ? $batch->expiry_date->diffForHumans() : 'in ' . (int) today()->diffInDays($batch->expiry_date) . ' days' }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right font-semibold text-slate-900">{{ number_format($batch->quantity) }}</td>
                                <td class="px-5 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $stateStyle }}">{{ $stateLabel }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Phones: cards --}}
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($batches as $batch)
                    @php [$stateLabel, $stateStyle] = $expiryStyles[$batch->expiryState()]; @endphp
                    <li class="p-4 {{ $batch->expiryState() === 'expired' ? 'bg-rose-50/40' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900">{{ $batch->medication->name ?? 'Product' }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $batch->medication?->dosage }}</p>
                            </div>
                            <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $stateStyle }}">{{ $stateLabel }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                            <div><dt class="text-slate-500">Batch</dt><dd class="mt-0.5 truncate font-mono font-medium text-slate-900">{{ $batch->batch_number ?: '—' }}</dd></div>
                            <div><dt class="text-slate-500">Expires</dt><dd class="mt-0.5 font-medium text-slate-900">{{ $batch->expiry_date?->format('M j, Y') ?? '—' }}</dd></div>
                            <div class="text-right"><dt class="text-slate-500">Units</dt><dd class="mt-0.5 font-semibold text-slate-900">{{ number_format($batch->quantity) }}</dd></div>
                        </dl>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($batchCounts['expired'])
            <p class="mt-3 flex gap-2 text-xs leading-5 text-slate-500"><i class="fas fa-circle-info mt-1 text-slate-400"></i>To remove expired units from your count, open the product in Inventory and subtract them. Expired units always come off first.</p>
        @endif
    @endif
</div>

{{-- ======================= TAB: TRANSFERS ======================= --}}
<div id="tab-transfers" role="tabpanel" aria-labelledby="tab-btn-transfers" data-tab-panel="transfers" class="{{ $activeTab === 'transfers' ? '' : 'hidden' }}">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-500">Stock moving between {{ $pharmacy->name }} and your other branches. It leaves the sender straight away and joins the receiver's shelf when they confirm it arrived.</p>
        @if ($otherBranches->isNotEmpty())
            <button type="button" data-cura-modal-open="transfer-modal" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none" @disabled($sendable->isEmpty())>
                <i class="fas fa-right-left text-xs"></i> Send stock to a branch
            </button>
        @endif
    </div>

    @if ($otherBranches->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-code-branch"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">You have one branch</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">Transfers move stock from one branch to another. Open a second branch and they appear here.</p>
            @if (auth()->user()->role === 'vendor')
                <a href="{{ route('vendor.branches') }}" class="mt-6 inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Go to Branches</a>
            @endif
        </div>
    @elseif ($transfers->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-xl text-emerald-600"><i class="fas fa-right-left"></i></span>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">No transfers yet</h2>
            <p class="mx-auto mt-1.5 max-w-sm text-sm text-slate-500">Send stock to a branch that is running low instead of buying more.</p>
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($transfers as $transfer)
                @php
                    $incoming = $transfer->to_pharmacy_id === $pharmacy->id;
                    $units = (int) $transfer->items->sum('quantity');
                    $waiting = $transfer->status === 'in_transit';
                @endphp
                <article class="flex flex-col overflow-hidden rounded-2xl border bg-white shadow-sm {{ $waiting && $incoming ? 'border-amber-300 ring-1 ring-amber-200' : 'border-slate-200' }}">
                    <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl {{ $incoming ? 'bg-emerald-50 text-emerald-600' : 'bg-sky-50 text-sky-600' }}"><i class="fas {{ $incoming ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i></span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $incoming ? 'From ' . ($transfer->from->name ?? 'a branch') : 'To ' . ($transfer->to->name ?? 'a branch') }}</p>
                                <p class="text-xs text-slate-500"><span class="font-mono">{{ $transfer->reference }}</span> · {{ $transfer->sent_at?->format('M j, g:i A') }}</p>
                            </div>
                        </div>
                        <span class="inline-flex flex-shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $transferStyles[$transfer->status] ?? $transferStyles['cancelled'] }}">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $transfer->statusLabel() }}
                        </span>
                    </header>

                    <ul class="space-y-1 border-y border-slate-100 bg-slate-50/70 px-4 py-3 text-sm sm:px-5">
                        @foreach ($transfer->items as $item)
                            <li class="flex gap-2 text-slate-700"><span class="w-9 flex-shrink-0 font-semibold text-slate-900">{{ $item->quantity }}×</span><span class="min-w-0 truncate">{{ $item->product_name }}</span></li>
                        @endforeach
                    </ul>

                    @if ($transfer->note)
                        <p class="px-4 pt-3 text-sm text-slate-600 sm:px-5"><i class="fas fa-comment-dots mr-1.5 text-slate-400"></i>{{ $transfer->note }}</p>
                    @endif

                    <footer class="mt-auto flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
                        <p class="text-xs text-slate-500">
                            {{ $units }} {{ Str::plural('unit', $units) }} · sent by {{ $transfer->sender->full_name ?? 'staff' }}
                            @if (! $waiting && $transfer->closer)
                                · {{ $transfer->status === 'received' ? 'received' : 'cancelled' }} by {{ $transfer->closer->full_name }}
                            @endif
                        </p>
                        @if ($waiting)
                            <form action="{{ route($incoming ? 'vendor.transfers.receive' : 'vendor.transfers.cancel', $transfer) }}" method="POST" data-once
                                  @unless ($incoming) onsubmit="return confirm('Cancel this transfer and put the stock back on your shelf?')" @endunless>
                                @csrf
                                @if ($incoming)
                                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700"><i class="fas fa-check text-xs"></i> Confirm it arrived</button>
                                @else
                                    <button type="submit" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition hover:border-rose-300 hover:text-rose-700">Cancel transfer</button>
                                @endif
                            </form>
                        @endif
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
</div>

@if ($otherBranches->isNotEmpty())
    <x-modal id="transfer-modal" title="Send stock to a branch" max-width="xl">
        <form action="{{ route('vendor.transfers.store') }}" method="POST" id="transfer-form">
            @csrf
            <label for="transfer-to" class="mb-1.5 block text-sm font-semibold text-slate-800">Send to</label>
            <select id="transfer-to" name="to_branch" required class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">
                @if ($otherBranches->count() > 1)<option value="">Choose a branch</option>@endif
                @foreach ($otherBranches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>

            <p class="mb-1.5 mt-5 text-sm font-semibold text-slate-800">Products</p>
            <div id="transfer-lines" class="space-y-2"></div>
            <button type="button" id="transfer-add-line" class="mt-2 inline-flex h-9 items-center gap-2 rounded-lg border border-dashed border-slate-300 px-3 text-xs font-semibold text-slate-600 transition hover:border-emerald-400 hover:text-emerald-700"><i class="fas fa-plus text-[10px]"></i> Add another product</button>

            <label for="transfer-note" class="mb-1.5 mt-5 block text-sm font-semibold text-slate-800">Note <span class="font-normal text-slate-400">(optional)</span></label>
            <input id="transfer-note" type="text" name="note" maxlength="250" placeholder="e.g. Weekly top-up" class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100">

            <p class="mt-4 flex gap-2 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><i class="fas fa-circle-info mt-0.5 text-slate-400"></i>The units leave your stock as soon as you send. The oldest batches go first and keep their expiry dates.</p>
        </form>
        <x-slot:footer>
            <div class="flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
                <button type="button" data-cura-modal-close class="h-11 rounded-xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">Cancel</button>
                <button type="submit" form="transfer-form" class="h-11 rounded-xl bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">Send stock</button>
            </div>
        </x-slot:footer>
    </x-modal>

    @push('scripts')
    <script>
        (function () {
            const sendable = @json($sendable);
            const lines = document.getElementById('transfer-lines');
            const form = document.getElementById('transfer-form');
            let next = 0;

            function addLine() {
                const index = next++;
                const row = document.createElement('div');
                row.className = 'flex items-start gap-2';

                const select = document.createElement('select');
                select.name = `items[${index}][medication_id]`;
                select.required = true;
                select.className = 'h-11 min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';
                select.add(new Option('Choose a product', ''));
                sendable.forEach(product => select.add(new Option(`${product.label} (${product.free} free)`, product.id)));

                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.name = `items[${index}][quantity]`;
                quantity.min = 1;
                quantity.required = true;
                quantity.placeholder = 'Qty';
                quantity.inputMode = 'numeric';
                quantity.setAttribute('aria-label', 'Quantity');
                quantity.className = 'h-11 w-20 flex-shrink-0 rounded-xl border border-slate-300 px-2 text-center text-sm font-semibold text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-100';

                // The quantity box never accepts more than the branch has free.
                select.addEventListener('change', () => {
                    const product = sendable.find(item => String(item.id) === select.value);
                    quantity.max = product ? product.free : '';
                    if (product && Number(quantity.value) > product.free) quantity.value = product.free;
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.setAttribute('aria-label', 'Remove product');
                remove.className = 'flex h-11 w-10 flex-shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600';
                remove.innerHTML = '<i class="fas fa-trash-can text-sm"></i>';
                remove.addEventListener('click', () => { if (lines.children.length > 1) row.remove(); });

                row.append(select, quantity, remove);
                lines.append(row);
            }

            document.getElementById('transfer-add-line').addEventListener('click', addLine);
            addLine();

            document.querySelectorAll('#transfer-form, form[data-once]').forEach(target => {
                target.addEventListener('submit', () => {
                    document.querySelectorAll('button[type="submit"]').forEach(button => {
                        if (button.form === target) { button.disabled = true; button.classList.add('opacity-60', 'cursor-wait'); }
                    });
                });
            });
        })();
    </script>
    @endpush
@endif
