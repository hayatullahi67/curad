@extends('layouts.app')

@php
    $payment = $sale->payments->first();
    $voided = $sale->isVoided();
    $canVoid = $sale->canBeVoided();
    $canRefund = $sale->canBeRefunded();
    $refundableLeft = max(0, $sale->total_amount - $sale->refunded_amount);
    $receiptData = $sale->receiptData($pharmacy);

    $statusStyles = [
        'completed' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'voided' => 'bg-slate-100 text-slate-600 ring-slate-200',
        'partially_refunded' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'refunded' => 'bg-rose-50 text-rose-700 ring-rose-200',
    ];

    // Which popup to reopen when the form comes back with a mistake in it.
    $reopen = $errors->any() ? old('_form') : null;
@endphp

@section('title', 'Sale ' . $sale->receipt_no)
@section('header_title', 'Sale details')


@section('content')
<div class="max-w-[1400px] mx-auto space-y-6">

    <a href="{{ route('vendor.sales') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 transition hover:text-slate-900">
        <i class="fas fa-arrow-left text-xs"></i> Back to all sales
    </a>

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <p class="font-bold">That could not be saved:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Heading --}}
    <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-mono text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{ $sale->receipt_no }}</h1>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset {{ $statusStyles[$sale->status] ?? 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                    {{ $sale->statusLabel() }}
                </span>
            </div>
            <p class="mt-1.5 text-sm text-slate-500">
                {{ $sale->created_at->format('l, M j, Y · g:i A') }}
                · Sold by <span class="font-semibold text-slate-700">{{ $sale->staff->full_name ?? 'Unknown' }}</span>
                · {{ $sale->customer_name ?: 'Walk-in Customer' }}@if ($sale->customer_phone) ({{ $sale->customer_phone }})@endif
                @if ($sale->customer_id)
                    · <a href="{{ route('vendor.sales', ['customer' => $sale->customer_id]) }}" class="font-semibold text-emerald-700 hover:underline">All their purchases</a>
                @endif
            </p>
        </div>
        <div class="flex-shrink-0 text-left sm:text-right">
            <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ $voided ? 'Was' : 'Total paid' }}</p>
            <p class="font-mono text-2xl font-black {{ $voided ? 'text-slate-400 line-through' : 'text-slate-900' }}">₦{{ number_format($sale->total_amount, 2) }}</p>
        </div>
    </div>

    @if ($voided)
        <div class="flex gap-4 rounded-2xl border border-slate-300 bg-slate-100 p-5">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-slate-800 text-white"><i class="fas fa-ban"></i></div>
            <div class="text-sm text-slate-700">
                <p class="font-black text-slate-900">This sale was voided</p>
                <p class="mt-1">Reason: <span class="font-semibold">{{ $sale->void_reason }}</span></p>
                <p class="mt-1 text-slate-500">
                    {{ optional($sale->voided_at)->format('M j, Y · g:i A') }} by {{ $sale->voidedBy->full_name ?? 'Unknown' }}@if ($sale->voidAuthorizedBy && $sale->void_authorized_by !== $sale->voided_by), approved by {{ $sale->voidAuthorizedBy->full_name }}@endif.
                    All items went back into stock and this sale no longer counts towards takings.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Left: what was sold --}}
        <div class="space-y-6 lg:col-span-2">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <h2 class="text-sm font-black text-slate-900">Items sold</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3 sm:px-6">Product</th>
                                <th class="px-3 py-3 text-center">Qty</th>
                                <th class="px-3 py-3 text-right">Unit price</th>
                                <th class="px-5 py-3 text-right sm:px-6">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($sale->items as $item)
                                <tr>
                                    <td class="px-5 py-3.5 sm:px-6">
                                        <p class="font-semibold text-slate-800">{{ $item->product_name }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">
                                            @if ($item->sku) SKU {{ $item->sku }} @endif
                                            @if ($item->sku && $item->batch_number) · @endif
                                            @if ($item->batch_number) Batch {{ $item->batch_number }} @endif
                                        </p>
                                        @if ($item->returned_quantity > 0)
                                            <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold text-rose-700">
                                                <i class="fas fa-undo text-[9px]"></i> {{ $item->returned_quantity }} returned
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3.5 text-center font-mono font-bold text-slate-800">{{ $item->quantity }}</td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-right font-mono text-slate-600">₦{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right font-mono font-bold text-slate-900 sm:px-6">₦{{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="space-y-2 border-t border-slate-200 bg-slate-50 px-5 py-4 text-sm sm:px-6">
                    <div class="flex justify-between text-slate-600"><span>Subtotal</span><span class="font-mono">₦{{ number_format($sale->subtotal, 2) }}</span></div>
                    @if ($sale->discount_amount > 0)
                        <div class="flex justify-between text-slate-600"><span>Discount</span><span class="font-mono text-emerald-700">−₦{{ number_format($sale->discount_amount, 2) }}</span></div>
                        @if ($sale->discount_reason || $sale->discountAuthorizedBy)
                            <p class="text-xs text-slate-500">
                                @if ($sale->discount_reason) Reason: <span class="font-semibold text-slate-700">{{ $sale->discount_reason }}</span> @endif
                                @if ($sale->discountAuthorizedBy) · approved by <span class="font-semibold text-slate-700">{{ $sale->discountAuthorizedBy->full_name }}</span> @endif
                            </p>
                        @endif
                    @endif
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-black text-slate-900"><span>Total paid</span><span class="font-mono">₦{{ number_format($sale->total_amount, 2) }}</span></div>
                    @if (! $voided && $sale->refunded_amount > 0)
                        <div class="flex justify-between text-rose-700"><span>Refunded so far</span><span class="font-mono">−₦{{ number_format($sale->refunded_amount, 2) }}</span></div>
                        <div class="flex justify-between font-bold text-slate-900"><span>Money kept</span><span class="font-mono">₦{{ number_format($sale->netAmount(), 2) }}</span></div>
                    @endif
                </div>
            </div>

            {{-- Payment --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-sm font-black text-slate-900">Payment</h2>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-500">Paid by</dt>
                        <dd class="mt-0.5 font-bold text-slate-900">{{ $sale->isSplitPayment() ? 'Split payment' : $sale->paymentLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Amount received</dt>
                        <dd class="mt-0.5 font-mono font-bold text-slate-900">₦{{ number_format($sale->payments->sum(fn ($line) => $line->amount_tendered ?? $line->amount) ?: $sale->total_amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Change given</dt>
                        <dd class="mt-0.5 font-mono font-bold text-slate-900">₦{{ number_format($sale->payments->sum('change_given'), 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Reference</dt>
                        <dd class="mt-0.5 font-mono font-bold text-slate-900">{{ $sale->payments->pluck('reference')->filter()->implode(', ') ?: '—' }}</dd>
                    </div>
                </dl>
                @if ($sale->isSplitPayment())
                    <div class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200 text-sm">
                        @foreach ($sale->payments as $line)
                            <div class="flex items-center justify-between gap-4 px-4 py-2.5">
                                <span class="font-semibold text-slate-800">{{ \App\Models\PosPayment::methodLabel($line->method) }}@if ($line->reference)<span class="ml-2 font-mono text-xs font-normal text-slate-500">{{ $line->reference }}</span>@endif</span>
                                <span class="font-mono font-bold text-slate-900">₦{{ number_format($line->amount, 2) }}@if ($line->change_given > 0)<span class="ml-2 text-xs font-normal text-slate-500">(₦{{ number_format($line->amount_tendered, 2) }} given, ₦{{ number_format($line->change_given, 2) }} change)</span>@endif</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Refund history --}}
            @if ($sale->refunds->isNotEmpty())
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <h2 class="text-sm font-black text-slate-900">Refunds on this sale</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($sale->refunds as $refund)
                            <div class="rounded-xl border border-rose-100 bg-rose-50/60 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="font-mono text-xs font-bold text-slate-500">{{ $refund->refund_no }}</p>
                                        <p class="mt-0.5 text-sm font-bold text-slate-900">₦{{ number_format($refund->amount, 2) }} returned by {{ \App\Models\PosPayment::methodLabel($refund->method) }}</p>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ $refund->created_at->format('M j, Y · g:i A') }}</p>
                                </div>
                                <ul class="mt-3 space-y-1 text-sm text-slate-700">
                                    @foreach ($refund->items as $line)
                                        <li class="flex flex-wrap items-center gap-x-2">
                                            <span class="font-mono font-bold">{{ $line->quantity }} ×</span> {{ $line->product_name }}
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $line->restocked ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                                {{ $line->restocked ? 'Back in stock' : 'Not restocked' }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-3 text-xs text-slate-500">
                                    Reason: <span class="font-semibold text-slate-700">{{ $refund->reason }}</span>
                                    · By {{ $refund->user->full_name ?? 'Unknown' }}@if ($refund->authorizedBy && $refund->authorized_by !== $refund->user_id), approved by {{ $refund->authorizedBy->full_name }}@endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Right: what you can do --}}
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-sm font-black text-slate-900">What would you like to do?</h2>

                <div class="mt-4 space-y-3">
                    <button type="button" onclick="CuraReceipt.print(saleReceipt, saleReceiptOptions)" class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white p-3.5 text-left transition hover:border-slate-300 hover:bg-slate-50">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-slate-900 text-white"><i class="fas fa-print"></i></span>
                        <span>
                            <span class="block text-sm font-bold text-slate-900">Print receipt</span>
                            <span class="block text-xs text-slate-500">Reprint a copy for the customer</span>
                        </span>
                    </button>

                    @if ($canRefund)
                        <button type="button" data-cura-modal-open="refund-modal" class="flex w-full items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-left transition hover:bg-amber-100">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500 text-white"><i class="fas fa-undo"></i></span>
                            <span>
                                <span class="block text-sm font-bold text-amber-950">Refund returned items</span>
                                <span class="block text-xs text-amber-800">Customer brought something back</span>
                            </span>
                        </button>
                    @endif

                    @if ($canVoid)
                        <button type="button" data-cura-modal-open="void-modal" class="flex w-full items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-left transition hover:bg-rose-100">
                            <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-rose-600 text-white"><i class="fas fa-ban"></i></span>
                            <span>
                                <span class="block text-sm font-bold text-rose-950">Void this sale</span>
                                <span class="block text-xs text-rose-800">It was rung up by mistake</span>
                            </span>
                        </button>
                    @endif
                </div>

                <div class="mt-5 rounded-xl bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">
                    @if ($voided)
                        <p>A voided sale is closed. Nothing more can be done with it.</p>
                    @elseif ($sale->status === \App\Models\PosSale::STATUS_REFUNDED)
                        <p>Every item on this sale has been returned and refunded, so there is nothing left to refund.</p>
                    @else
                        <p><span class="font-bold text-slate-800">Void</span> cancels the whole sale as if it never happened. It only works on the day of the sale.</p>
                        <p class="mt-2"><span class="font-bold text-slate-800">Refund</span> gives money back for items the customer returns, on any day. The sale stays on record.</p>
                        @if (! $canVoid && $canRefund)
                            <p class="mt-2 text-slate-500">
                                {{ $sale->refunded_amount > 0 ? 'This sale already has a refund on it, so it can no longer be voided.' : 'This sale was not made today, so it can only be refunded.' }}
                            </p>
                        @endif
                    @endif
                    @if ($needsApproval && ($canVoid || $canRefund))
                        <p class="mt-2 font-semibold text-slate-700"><i class="fas fa-user-shield mr-1 text-slate-400"></i> A manager will need to enter their login to approve either one.</p>
                    @endif
                </div>
            </div>

            {{-- Receipt: the same one the till prints --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Receipt preview</p>
                    <button type="button" onclick="CuraReceipt.download(saleReceipt, saleReceiptOptions)" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"><i class="fas fa-download text-[10px]"></i> Save</button>
                </div>
                <div class="overflow-hidden rounded-xl bg-slate-100 px-3 py-4">
                    <div class="mx-auto w-fit overflow-x-auto pb-1">
                        {{-- Shown a little smaller to fit this column; it still prints at full 80mm --}}
                        <div class="receipt-paper w-[80mm] bg-white shadow-md shadow-slate-900/10" style="zoom: 0.84">
                            <iframe id="sale-receipt-preview" title="Receipt preview" class="block w-[80mm] border-0" style="height: 480px"></iframe>
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-slate-500">Printed copies are marked <span class="font-bold text-slate-700">COPY</span> with the time they were printed.</p>
            </div>
        </div>
    </div>
</div>

{{-- ================= VOID ================= --}}
@if ($canVoid)
<x-modal id="void-modal" title="Void this sale?" subtitle="Receipt {{ $sale->receipt_no }}" max-width="lg">
    <form method="POST" action="{{ route('vendor.sales.void', $sale) }}" id="void-form" class="space-y-5">
        @csrf
        <input type="hidden" name="_form" value="void">

        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">
            <p class="font-bold">Here is what will happen:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-rose-800">
                <li>The whole sale of <strong>₦{{ number_format($sale->total_amount, 2) }}</strong> is cancelled.</li>
                <li>All {{ $sale->items->sum('quantity') }} unit(s) go back into stock.</li>
                <li>Give the customer their money back. This cannot be undone.</li>
            </ul>
        </div>

        <div>
            <label for="void-reason" class="mb-1.5 block text-sm font-bold text-slate-800">Why is this sale being voided?</label>
            <div class="mb-2 flex flex-wrap gap-1.5">
                @foreach (['Wrong item entered', 'Wrong quantity', 'Customer changed their mind', 'Payment did not go through', 'Duplicate sale'] as $quick)
                    <button type="button" onclick="document.getElementById('void-reason').value = this.textContent.trim()" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-900">{{ $quick }}</button>
                @endforeach
            </div>
            <input id="void-reason" type="text" name="reason" required minlength="3" maxlength="255" value="{{ $reopen === 'void' ? old('reason') : '' }}" placeholder="Pick one above or type your own"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 focus:border-rose-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-rose-100">
        </div>

        @if ($needsApproval)
            @include('vendor-dashboard.partials.manager-approval', ['prefix' => 'void', 'restore' => $reopen === 'void'])
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="rounded-xl px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100">Keep the sale</button>
            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-rose-700">
                <i class="fas fa-ban"></i> Yes, void this sale
            </button>
        </div>
    </form>
</x-modal>
@endif

{{-- ================= REFUND ================= --}}
@if ($canRefund)
<x-modal id="refund-modal" title="Refund returned items" subtitle="Receipt {{ $sale->receipt_no }}" max-width="2xl">
    <form method="POST" action="{{ route('vendor.sales.refund', $sale) }}" id="refund-form" class="space-y-5">
        @csrf
        <input type="hidden" name="_form" value="refund">

        <div>
            <p class="text-sm font-bold text-slate-800">1. What is the customer returning?</p>
            <p class="mt-0.5 text-xs text-slate-500">Enter how many of each item came back. Leave the rest at 0.</p>

            <div class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                @foreach ($sale->items as $item)
                    @php
                        $left = $item->returnableQuantity();
                        $oldQuantity = $reopen === 'refund' ? (int) old('quantities.' . $item->id, 0) : 0;
                        $oldRestock = $reopen === 'refund' ? array_key_exists($item->id, (array) old('restock', [])) : true;
                    @endphp
                    <div class="flex flex-col gap-3 p-3.5 sm:flex-row sm:items-center {{ $left === 0 ? 'bg-slate-50 opacity-60' : '' }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900">{{ $item->product_name }}</p>
                            <p class="text-xs text-slate-500">
                                Paid ₦{{ number_format($item->netUnitPrice(), 2) }} each ·
                                @if ($left === 0) all {{ $item->quantity }} already returned
                                @else {{ $left }} of {{ $item->quantity }} can be returned
                                @endif
                            </p>
                        </div>
                        @if ($left > 0)
                            <div class="flex items-center gap-4">
                                <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white">
                                    <button type="button" class="refund-step px-3 py-2 text-sm font-black text-slate-500 hover:text-slate-900" data-step="-1" aria-label="Fewer">−</button>
                                    <input type="number" name="quantities[{{ $item->id }}]" value="{{ $oldQuantity }}" min="0" max="{{ $left }}" step="1" inputmode="numeric"
                                        data-unit="{{ round($item->netUnitPrice(), 4) }}"
                                        class="refund-qty w-12 border-x border-slate-200 py-2 text-center font-mono text-sm font-bold text-slate-900 focus:outline-none" aria-label="Quantity of {{ $item->product_name }} returned">
                                    <button type="button" class="refund-step px-3 py-2 text-sm font-black text-slate-500 hover:text-slate-900" data-step="1" aria-label="More">+</button>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700" title="Untick if the item is damaged, opened or expired and cannot be sold again">
                                    <input type="checkbox" name="restock[{{ $item->id }}]" value="1" @checked($oldRestock) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    Put back in stock
                                </label>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-slate-500"><i class="fas fa-info-circle mr-1 text-slate-400"></i> Untick “Put back in stock” for anything damaged, opened or expired. It will be refunded but not sold again.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="refund-method" class="mb-1.5 block text-sm font-bold text-slate-800">2. How is the money going back?</label>
                <select id="refund-method" name="method" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-amber-100">
                    @foreach (\App\Models\PosPayment::METHODS as $value => $label)
                        <option value="{{ $value }}" @selected(($reopen === 'refund' ? old('method') : ($payment->method ?? 'cash')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="rounded-xl bg-slate-900 p-4 text-white">
                <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Give the customer</p>
                <p id="refund-total" class="mt-1 font-mono text-2xl font-black">₦0.00</p>
                <p class="mt-0.5 text-[11px] text-slate-400">Up to ₦{{ number_format($refundableLeft, 2) }} is left on this sale</p>
            </div>
        </div>

        <div>
            <label for="refund-reason" class="mb-1.5 block text-sm font-bold text-slate-800">3. Why is it being returned?</label>
            <div class="mb-2 flex flex-wrap gap-1.5">
                @foreach (['Wrong product given', 'Customer changed their mind', 'Damaged or faulty', 'Expired product', 'Doctor changed prescription'] as $quick)
                    <button type="button" onclick="document.getElementById('refund-reason').value = this.textContent.trim()" class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-slate-400 hover:text-slate-900">{{ $quick }}</button>
                @endforeach
            </div>
            <input id="refund-reason" type="text" name="reason" required minlength="3" maxlength="255" value="{{ $reopen === 'refund' ? old('reason') : '' }}" placeholder="Pick one above or type your own"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-amber-100">
        </div>

        @if ($needsApproval)
            @include('vendor-dashboard.partials.manager-approval', ['prefix' => 'refund', 'restore' => $reopen === 'refund'])
        @endif

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-cura-modal-close class="rounded-xl px-5 py-3 text-sm font-bold text-slate-600 transition hover:bg-slate-100">Cancel</button>
            <button type="submit" id="refund-submit" disabled class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-5 py-3 text-sm font-bold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-50">
                <i class="fas fa-undo"></i> <span id="refund-submit-label">Choose items to refund</span>
            </button>
        </div>
    </form>
</x-modal>
@endif
@endsection

@push('scripts')
@include('partials.receipt-script')
<script>
    // Printing from this page is always a copy of the original receipt.
    const saleReceipt = @json($receiptData);
    const saleReceiptOptions = { copy: true, reprintedAt: @json(now()->format('Y-m-d H:i:s')) };
    CuraReceipt.preview(document.getElementById('sale-receipt-preview'), saleReceipt, saleReceiptOptions);
</script>
@endpush

@push('scripts')
<script>
    (function () {
        const refundableLeft = {{ json_encode(round($refundableLeft, 2)) }};
        const quantityInputs = Array.from(document.querySelectorAll('.refund-qty'));
        const totalEl = document.getElementById('refund-total');
        const submitBtn = document.getElementById('refund-submit');
        const submitLabel = document.getElementById('refund-submit-label');

        const money = (amount) => '₦' + Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        function clamp(input) {
            const max = parseInt(input.max, 10) || 0;
            const value = Math.min(max, Math.max(0, parseInt(input.value, 10) || 0));
            input.value = value;
            return value;
        }

        function recalculate() {
            if (!totalEl) return;

            let total = 0;
            quantityInputs.forEach((input) => {
                total += clamp(input) * parseFloat(input.dataset.unit);
            });
            // The server never refunds more than is left on the sale.
            total = Math.min(Math.round(total * 100) / 100, refundableLeft);

            totalEl.textContent = money(total);
            submitBtn.disabled = total <= 0;
            submitLabel.textContent = total > 0 ? 'Refund ' + money(total) : 'Choose items to refund';
        }

        quantityInputs.forEach((input) => input.addEventListener('input', recalculate));

        document.querySelectorAll('.refund-step').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.parentElement.querySelector('.refund-qty');
                input.value = (parseInt(input.value, 10) || 0) + parseInt(button.dataset.step, 10);
                recalculate();
            });
        });

        recalculate();

        // Came back with a mistake in the form: reopen the popup it was in.
        const reopen = @json($reopen);
        if (reopen && document.getElementById(reopen + '-modal')) {
            document.addEventListener('DOMContentLoaded', () => CuraModal.open(reopen + '-modal'));
        }
    })();
</script>
@endpush
