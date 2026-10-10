@extends('layouts.app')

@section('title', 'POS | ' . ($pharmacy->name ?? 'CURA'))
@section('header_title', 'POS Terminal')

@php
    $sheet = 'pos-overlay fixed inset-0 z-[60] hidden items-end justify-center bg-slate-900/50 backdrop-blur-sm sm:items-center sm:p-4';
    $card = 'pos-scroll max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white shadow-2xl sm:rounded-2xl';
    $close = 'flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700';
    $input = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10';
    $primary = 'inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 font-semibold text-white shadow-sm shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none';
    $ghost = 'inline-flex items-center justify-center gap-2 rounded-xl font-semibold text-slate-600 transition hover:bg-slate-100';
    // Keyboard shortcut labels. Shown only on devices with a mouse and keyboard.
    $kbd = 'pos-kbd h-5 min-w-[1.25rem] items-center justify-center rounded-[5px] border border-slate-200 bg-white px-1.5 font-sans text-[10px] font-semibold leading-none text-slate-500 shadow-[0_1px_0_0_#e2e8f0]';
    $kbdOnDark = 'pos-kbd h-5 min-w-[1.25rem] items-center justify-center rounded-[5px] bg-white/20 px-1.5 font-sans text-[10px] font-semibold leading-none text-white group-disabled:bg-slate-300/60 group-disabled:text-slate-500';
@endphp

@push('styles')
<style>

    .pos-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
    .pos-scroll::-webkit-scrollbar-track { background: transparent; }
    .pos-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
    .pos-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .pos-chips { scrollbar-width: none; }
    .pos-chips::-webkit-scrollbar { display: none; }

    /* Pop-ups are hidden with Tailwind's "hidden" and laid out with flex when shown. */
    .pos-overlay:not(.hidden) { display: flex; }

    /* The sale panel slides up on phones, in from the right on tablets,
       and simply sits beside the products on wide screens. */
    @keyframes posSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
    @keyframes posSlideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    .mobile-drawer-open { animation: posSlideUp 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
    @media (min-width: 768px) { .mobile-drawer-open { animation-name: posSlideIn; } }
    @media (min-width: 1280px) { .mobile-drawer-open { animation: none; } }

    @keyframes toastFadeIn { from { opacity: 0; transform: translateY(10px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .pos-toast-animate { animation: toastFadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

    .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    /* Quantity boxes: no browser spin arrows, the - and + buttons do that job. */
    .pos-qty::-webkit-outer-spin-button, .pos-qty::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .pos-qty { -moz-appearance: textfield; appearance: textfield; }

    /* Shortcut labels only where there is a physical keyboard (mouse users);
       phones and tablets keep a clean touch layout. */
    .pos-kbd, .pos-keys { display: none; }
    @media (hover: hover) and (pointer: fine) {
        .pos-kbd { display: inline-flex; }
        .pos-keys { display: flex; }
    }
</style>
@endpush

@section('content')
<div id="pos-root" class="mx-auto -mt-5 max-w-[1600px] pb-28 text-slate-800 xl:pb-0">
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_400px] 2xl:grid-cols-[minmax(0,1fr)_440px]">

        {{-- ==================== PRODUCTS ==================== --}}
        <section class="min-w-0">
            {{-- Search and categories stay in view while the catalogue scrolls --}}
            <div class="sticky -top-8 z-20 -mx-1 bg-slate-50/95 px-1 pb-3 pt-3 backdrop-blur">
                <div class="relative">
                    <i class="fas fa-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input
                        type="text"
                        id="pos-search-input"
                        placeholder="Search a product or scan a barcode"
                        class="h-12 w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 sm:pr-32"
                        autofocus
                        autocomplete="off"
                    >
                    <span class="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 items-center gap-1.5 rounded-lg bg-slate-100 py-1 pl-2.5 pr-1 text-[11px] font-medium text-slate-500 sm:flex">
                        <i class="fas fa-barcode"></i> Scanner ready
                        <kbd class="{{ $kbd }}" title="Press F2 to jump here">F2</kbd>
                    </span>
                </div>

                <div id="category-pill-group" class="pos-chips mt-3 flex items-center gap-2 overflow-x-auto">
                    <button type="button" onclick="filterCategory(this.dataset.cat)" class="category-pill active whitespace-nowrap rounded-full bg-slate-900 px-4 py-2 text-[13px] font-semibold text-white shadow-sm transition" data-cat="ALL">
                        All <span class="ml-1 opacity-60">{{ count($medications) }}</span>
                    </button>
                    @foreach ($categories as $category)
                        <button type="button" onclick="filterCategory(this.dataset.cat)" class="category-pill whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-2 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900" data-cat="{{ $category }}">
                            {{ $category }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Who is on the till --}}
            <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-slate-500 sm:flex-nowrap">
                <p class="flex min-w-0 flex-1 items-center gap-2">
                    <span class="relative flex h-2 w-2 flex-shrink-0"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span>
                    <span class="truncate"><span class="font-semibold text-slate-700">{{ $user->full_name ?? $user->name ?? 'Staff' }}</span> · {{ $user->role === 'vendor' ? 'Owner' : \App\Support\StaffRoles::label($user->staff_role) }}@if ($pharmacy?->name) · {{ $pharmacy->name }}@endif</span>
                </p>
                <div class="flex flex-shrink-0 items-center gap-2">
                    <button type="button" onclick="openShortcutsModal()" class="pos-keys items-center gap-1.5 rounded-lg px-2 py-1 font-medium text-slate-500 transition hover:bg-white hover:text-slate-800" title="See every keyboard shortcut">
                        <i class="far fa-keyboard"></i> Shortcuts <kbd class="{{ $kbd }}">F1</kbd>
                    </button>
                    <p class="hidden px-1 2xl:block"><i class="far fa-clock mr-1"></i><span id="pos-shift-clock"></span></p>
                </div>
            </div>

            <div id="product-grid" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3"></div>

            <div id="no-products-view" class="hidden rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-search"></i></div>
                <p class="mt-4 text-sm font-semibold text-slate-800">No product matches</p>
                <p class="mt-1 text-xs text-slate-500">Try another name, SKU or barcode, or pick a different category.</p>
            </div>
        </section>

        {{-- ==================== CURRENT SALE ====================
             One panel: a side column on wide screens, a slide-up sheet on
             phones and tablets (opened from the bar at the bottom). --}}
        <div id="mobile-cart-drawer-backdrop" onclick="closeMobileCartDrawer()" class="fixed inset-0 z-[55] hidden bg-slate-900/50 backdrop-blur-sm xl:hidden"></div>

        <aside id="mobile-cart-drawer" class="fixed inset-x-0 bottom-0 z-[56] hidden max-h-[92dvh] flex-col rounded-t-3xl bg-white shadow-2xl md:inset-y-0 md:left-auto md:right-0 md:max-h-none md:w-[26rem] md:rounded-none md:rounded-l-3xl xl:sticky xl:inset-auto xl:-top-5 xl:z-10 xl:mt-3 xl:flex xl:h-[calc(100dvh-7.25rem)] xl:w-auto xl:self-start xl:rounded-2xl xl:border xl:border-slate-200 xl:shadow-sm">
            <div class="mx-auto mt-2.5 h-1.5 w-10 flex-shrink-0 rounded-full bg-slate-200 md:hidden"></div>

            {{-- Heading --}}
            <div class="flex flex-shrink-0 items-center justify-between gap-3 px-4 pt-3 sm:px-5 xl:pt-4">
                <div class="flex min-w-0 items-center gap-2.5">
                    <h3 class="whitespace-nowrap text-base font-semibold text-slate-900">Current sale</h3>
                    <span id="cart-item-count-badge" class="whitespace-nowrap rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">0 items</span>
                </div>
                <div class="flex items-center gap-1">
                    <button type="button" onclick="openRecentSalesModal()" title="Your sales today: reprint a receipt" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        <i class="fas fa-clock-rotate-left"></i>
                        <span class="hidden sm:inline xl:hidden 2xl:inline">Recent</span>
                    </button>
                    <button type="button" onclick="openSavedCartsModal()" title="Sales saved for later" class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-50">
                        <i class="fas fa-bookmark"></i>
                        <span>Saved</span>
                        <span id="saved-carts-count-desktop" class="rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800">0</span>
                        <kbd class="{{ $kbd }}">F8</kbd>
                    </button>
                    <button type="button" onclick="closeMobileCartDrawer()" aria-label="Close" class="{{ $close }} xl:hidden"><i class="fas fa-times"></i></button>
                </div>
            </div>

            {{-- Customer: type a name or phone to find a returning customer --}}
            <div id="pos-customer-box" class="relative flex-shrink-0 px-4 pt-3 sm:px-5">
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 transition focus-within:border-emerald-500 focus-within:bg-white focus-within:ring-4 focus-within:ring-emerald-500/10">
                    <i class="fas fa-user text-xs text-slate-400"></i>
                    <input type="text" id="pos-customer-name" value="Walk-in Customer" autocomplete="off" title="Customer name" aria-label="Customer name"
                        class="min-w-0 flex-1 border-none bg-transparent p-0 text-sm font-medium text-slate-800 outline-none focus:ring-0">
                    <span class="h-4 w-px flex-shrink-0 bg-slate-200"></span>
                    <i class="fas fa-phone text-[11px] text-slate-400"></i>
                    <input type="text" id="pos-customer-phone" placeholder="Phone" autocomplete="off" inputmode="tel" aria-label="Customer phone"
                        class="w-[7.5rem] flex-shrink-0 border-none bg-transparent p-0 text-sm tabular-nums text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0">
                    <button type="button" id="pos-customer-clear" onclick="clearCustomer()" title="Back to walk-in customer" aria-label="Back to walk-in customer" class="hidden h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300">
                        <i class="fas fa-times text-[9px]"></i>
                    </button>
                    <kbd class="{{ $kbd }} flex-shrink-0" title="Press F3 to type the customer">F3</kbd>
                </div>
                <p id="pos-customer-status" class="mt-1 text-[10px] text-slate-400">Add a phone number to keep this customer's purchase history</p>
                <div id="pos-customer-suggestions" class="absolute left-4 right-4 top-full z-30 -mt-3 hidden max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl sm:left-5 sm:right-5"></div>
            </div>

            {{-- Items --}}
            <div id="cart-items-list-desktop" class="pos-scroll mt-3 hidden min-h-[5.5rem] flex-1 divide-y divide-slate-100 overflow-y-auto px-4 sm:px-5"></div>

            <div id="cart-empty-view-desktop" class="flex flex-1 flex-col items-center justify-center px-6 py-8 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-shopping-basket"></i></div>
                <p class="mt-3 text-sm font-semibold text-slate-700">Nothing in this sale yet</p>
                <p class="mt-0.5 text-xs text-slate-500">Tap a product or scan a barcode to add it.</p>
            </div>

            {{-- Totals and actions --}}
            <div class="flex-shrink-0 border-t border-slate-100 px-4 pb-4 pt-3 sm:px-5">
                <div class="space-y-1.5 text-sm">
                    <div class="flex items-center justify-between text-slate-500">
                        <span>Subtotal</span>
                        <span id="summary-subtotal-desktop" class="font-medium tabular-nums text-slate-800">₦0</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-500">
                        <button type="button" onclick="openDiscountModal()" class="inline-flex items-center gap-1.5 font-medium text-emerald-700 hover:underline">
                            <i class="fas fa-tag text-xs"></i> Discount <kbd class="{{ $kbd }}">F4</kbd>
                        </button>
                        <span class="font-medium tabular-nums text-emerald-700">−<span id="summary-discount-desktop">₦0</span></span>
                    </div>
                </div>

                <div class="mt-2.5 flex items-baseline justify-between border-t border-dashed border-slate-200 pt-2.5">
                    <span class="text-sm font-semibold text-slate-900">Total to pay</span>
                    <span id="summary-net-total-desktop" class="text-2xl font-bold tabular-nums tracking-tight text-slate-900">₦0</span>
                </div>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" onclick="saveCartForLater()" title="Save this sale for later and serve the next customer (F7)" aria-label="Save for later" class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 text-amber-600 transition hover:border-amber-300 hover:bg-amber-50">
                        <i class="fas fa-bookmark"></i>
                    </button>
                    <button type="button" onclick="promptClearCart()" title="Remove everything from this sale (Delete)" aria-label="Clear the sale" class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button type="button" id="checkout-btn-desktop" onclick="openCheckoutModal()" disabled class="{{ $primary }} group h-12 flex-1 text-sm">
                        Checkout <i class="fas fa-arrow-right text-xs"></i> <kbd class="{{ $kbdOnDark }}">F9</kbd>
                    </button>
                </div>
                <p class="pos-keys mt-2.5 items-center justify-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                    <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}">F7</kbd> Save</span>
                    <span class="inline-flex items-center gap-1"><kbd class="{{ $kbd }}">Del</kbd> Clear</span>
                    <button type="button" onclick="openShortcutsModal()" class="inline-flex items-center gap-1 hover:text-slate-700"><kbd class="{{ $kbd }}">F1</kbd> All shortcuts</button>
                </p>
            </div>
        </aside>
    </div>

    {{-- Bottom bar on phones and tablets: the running total, and the way into the sale --}}
    <div id="mobile-sticky-cart-bar" class="fixed inset-x-3 bottom-3 z-30 flex items-center justify-between gap-3 rounded-2xl bg-slate-900 p-2.5 pl-4 text-white shadow-2xl shadow-slate-900/30 sm:inset-x-6 lg:left-[19.5rem] xl:hidden">
        <button type="button" onclick="openMobileCartDrawer()" class="flex min-w-0 flex-1 items-center gap-3 text-left">
            <span class="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white/10"><i class="fas fa-shopping-basket"></i></span>
            <span class="min-w-0">
                <span id="mobile-bar-qty" class="block text-[11px] font-medium text-slate-400">0 items</span>
                <span id="mobile-bar-total" class="block truncate text-lg font-bold leading-tight tabular-nums">₦0</span>
            </span>
        </button>
        <button type="button" onclick="openMobileCartDrawer()" class="inline-flex h-11 flex-shrink-0 items-center gap-2 rounded-xl bg-emerald-500 px-5 text-sm font-semibold text-white transition hover:bg-emerald-400">
            View sale <i class="fas fa-chevron-up text-xs"></i>
        </button>
    </div>
</div>

{{-- ==================== PRODUCT DETAILS ==================== --}}
<div id="product-detail-modal" class="{{ $sheet }}">
    <div class="{{ $card }} sm:max-w-lg">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <span id="pdetail-category-badge" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">Category</span>
                <span id="pdetail-rx-badge" class="hidden rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/15">Prescription only</span>
            </div>
            <button type="button" onclick="closeProductDetailModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div class="flex items-center gap-4">
                <div id="pdetail-photo" class="flex-shrink-0"></div>
                <div class="min-w-0">
                    <h3 id="pdetail-title" class="text-lg font-semibold leading-snug tracking-tight text-slate-900">Product</h3>
                    <p id="pdetail-dosage" class="mt-0.5 text-sm text-slate-500"></p>
                    <p class="mt-2 flex flex-wrap items-baseline gap-x-3">
                        <span id="pdetail-price" class="text-xl font-bold tabular-nums text-slate-900">₦0</span>
                        <span id="pdetail-stock" class="text-xs font-medium text-emerald-700"></span>
                    </p>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-2.5 rounded-xl bg-slate-50 px-4 py-3 text-sm">
                <div><dt class="text-xs text-slate-500">SKU</dt><dd id="pdetail-sku" class="font-medium tabular-nums text-slate-900">—</dd></div>
                <div><dt class="text-xs text-slate-500">Barcode</dt><dd id="pdetail-barcode" class="font-medium tabular-nums text-slate-900">—</dd></div>
                <div><dt class="text-xs text-slate-500">Batch</dt><dd id="pdetail-batch" class="font-medium tabular-nums text-slate-900">—</dd></div>
                <div><dt class="text-xs text-slate-500">Expiry</dt><dd id="pdetail-expiry" class="font-medium text-slate-900">—</dd></div>
            </dl>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Description</h4>
                <p id="pdetail-description" class="mt-1 text-sm leading-relaxed text-slate-600"></p>
            </div>
        </div>

        <div class="sticky bottom-0 flex items-center gap-3 border-t border-slate-100 bg-white px-5 py-4">
            <div class="inline-flex h-12 items-center rounded-xl border border-slate-200">
                <button type="button" onclick="adjustDetailModalQty(-1)" aria-label="One less" class="h-full px-4 text-lg font-medium text-slate-500 hover:text-slate-900">−</button>
                <span id="pdetail-modal-qty" class="w-8 text-center text-sm font-semibold tabular-nums text-slate-900">1</span>
                <button type="button" onclick="adjustDetailModalQty(1)" aria-label="One more" class="h-full px-4 text-lg font-medium text-slate-500 hover:text-slate-900">+</button>
            </div>
            <button type="button" id="pdetail-add-btn" onclick="addDetailModalToCart()" class="{{ $primary }} h-12 flex-1 text-sm">
                <i class="fas fa-plus text-xs"></i> Add to sale
            </button>
        </div>
    </div>
</div>

{{-- ==================== CHECKOUT ==================== --}}
<div id="checkout-modal" class="{{ $sheet }}">
    <div class="{{ $card }} sm:max-w-md">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Take payment</h3>
                <p class="text-xs text-slate-500">Choose how the customer is paying.</p>
            </div>
            <button type="button" onclick="closeCheckoutModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div class="rounded-2xl bg-slate-900 px-5 py-4 text-center text-white">
                <p class="text-xs font-medium text-slate-400">Amount due</p>
                <p id="modal-payable-total" class="mt-1 text-3xl font-bold tabular-nums tracking-tight">₦0</p>
            </div>

            {{-- Split payment: part cash, part card or transfer --}}
            <div id="split-payment-section" class="hidden space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-900">Split payment <span class="font-normal text-slate-500">· up to 4 ways</span></p>
                    <button type="button" onclick="setSplitMode(false)" class="text-xs font-semibold text-slate-500 hover:text-slate-900">Use one method</button>
                </div>
                <div id="split-lines" class="space-y-2"></div>
                <button type="button" id="split-add-btn" onclick="addSplitLine()" class="flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 text-xs font-semibold text-slate-600 transition hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700">
                    <i class="fas fa-plus text-[10px]"></i> Add another payment
                </button>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-xl bg-slate-50 px-3.5 py-2.5">
                        <p class="text-xs text-slate-500">Paid so far</p>
                        <p id="split-paid" class="font-bold tabular-nums text-slate-900">₦0</p>
                    </div>
                    <div id="split-balance-box" class="rounded-xl bg-rose-50 px-3.5 py-2.5 ring-1 ring-inset ring-rose-600/15">
                        <p id="split-balance-label" class="text-xs text-rose-700">Still to pay</p>
                        <p id="split-balance" class="font-bold tabular-nums text-rose-700">₦0</p>
                    </div>
                </div>
                <p class="text-[11px] leading-relaxed text-slate-500">Only cash can be more than what is left, and the change comes from it. Confirm card and transfer payments have gone through before completing.</p>
            </div>

            <div id="single-payment-section" class="space-y-4">
            <div class="grid grid-cols-4 gap-2">
                @foreach ([['cash', 'fa-money-bill-wave', 'Cash'], ['pos_card', 'fa-credit-card', 'Card'], ['bank_transfer', 'fa-university', 'Transfer'], ['valu_wallet', 'fa-wallet', 'Valu']] as [$methodKey, $methodIcon, $methodName])
                    <button type="button" onclick="choosePayment('{{ $methodKey }}')" class="pay-method-btn" data-method="{{ $methodKey }}">
                        <i class="fas {{ $methodIcon }} text-base"></i><span>{{ $methodName }}</span>
                        <kbd class="{{ $kbd }} absolute right-1.5 top-1.5">{{ $loop->iteration }}</kbd>
                    </button>
                @endforeach
            </div>

            {{-- Cash --}}
            <div id="cash-payment-section" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="cash-tendered-input" class="mb-1.5 block text-[13px] font-medium text-slate-700">Cash received</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">₦</span>
                            <input type="number" id="cash-tendered-input" step="100" inputmode="decimal" oninput="calculateCashChange()" class="{{ $input }} pl-8 font-semibold tabular-nums">
                        </div>
                    </div>
                    <div>
                        <span class="mb-1.5 block text-[13px] font-medium text-slate-700">Change to give</span>
                        <div id="cash-change-display" class="flex h-[42px] items-center rounded-xl bg-emerald-50 px-3.5 text-sm font-bold tabular-nums text-emerald-700 ring-1 ring-inset ring-emerald-600/15">₦0</div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-1.5 sm:grid-cols-6">
                    <button type="button" onclick="setCashTender('EXACT')" class="rounded-full border border-slate-200 bg-white px-1 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-400">Exact</button>
                    @foreach ([1000, 2000, 5000, 10000, 20000] as $note)
                        <button type="button" onclick="setCashTender({{ $note }})" class="rounded-full border border-slate-200 bg-white px-1 py-1.5 text-xs font-semibold tabular-nums text-slate-700 transition hover:border-slate-400">₦{{ number_format($note) }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Card --}}
            <div id="card-payment-section" class="hidden space-y-2">
                <p class="text-sm text-slate-600">Take the payment on your card terminal, then complete the sale here.</p>
                <input type="text" id="pos-card-ref" maxlength="100" placeholder="Approval code or RRN (optional)" class="{{ $input }}">
            </div>

            {{-- Valu wallet --}}
            <div id="wallet-payment-section" class="hidden rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <p class="font-semibold text-slate-800"><i class="fas fa-wallet mr-1.5 text-slate-400"></i>Paid with Valu wallet</p>
                <p class="mt-1 text-xs">Confirm the customer's transfer has reached the pharmacy's wallet before you complete the sale. This records the method; it does not move the money.</p>
            </div>

            {{-- Transfer --}}
            <div id="transfer-payment-section" class="hidden space-y-2">
                <p class="rounded-xl bg-amber-50 px-4 py-3 text-xs text-amber-900 ring-1 ring-inset ring-amber-600/15">
                    <i class="fas fa-exclamation-triangle mr-1"></i> <span class="font-semibold">Confirm the money has arrived first.</span> Check the pharmacy's bank alert before you complete this sale.
                </p>
                <input type="text" id="pos-transfer-ref" maxlength="100" placeholder="Sender name or transfer reference (optional)" class="{{ $input }}">
            </div>

            <button type="button" onclick="setSplitMode(true)" class="flex w-full items-center justify-center gap-2 rounded-xl py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-50">
                <i class="fas fa-code-branch text-[11px]"></i> Customer paying two ways? Split the payment
            </button>
            </div>
        </div>

        <div class="sticky bottom-0 flex items-center gap-2 border-t border-slate-100 bg-white px-5 py-4">
            <button type="button" onclick="closeCheckoutModal()" class="{{ $ghost }} h-12 px-4 text-sm">Back</button>
            <button type="button" id="submit-sale-btn" onclick="submitPOSSale()" title="Complete the sale (F10 or Enter)" class="{{ $primary }} group h-12 flex-1 text-sm">
                <i class="fas fa-print"></i> Complete & Print <kbd class="{{ $kbdOnDark }}">F10</kbd>
            </button>
        </div>
    </div>
</div>

{{-- ==================== RECEIPT ==================== --}}
<div id="thermal-receipt-modal" class="{{ $sheet }}">
    <div id="thermal-receipt-card" class="{{ $card }} sm:max-w-sm">
        <div class="no-print flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-2.5">
                <span id="rcpt-modal-icon" class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"><i class="fas fa-check"></i></span>
                <div>
                    <h3 id="rcpt-modal-title" class="text-base font-semibold text-slate-900">Sale complete</h3>
                    <p id="rcpt-modal-subtitle" class="text-xs text-slate-500">Stock has been updated.</p>
                </div>
            </div>
            <button type="button" onclick="closeReceiptModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>

        {{-- The receipt as it will print: 80mm paper on a grey desk --}}
        <div class="bg-slate-100 px-5 py-5">
            <div class="pos-scroll mx-auto max-h-[52dvh] w-fit overflow-y-auto pb-1">
                <div class="receipt-paper w-[80mm] bg-white shadow-md shadow-slate-900/10">
                    <iframe id="receipt-preview" title="Receipt preview" class="block w-[80mm] border-0" style="height: 420px"></iframe>
                </div>
            </div>
        </div>

        <div class="no-print sticky bottom-0 space-y-2 border-t border-slate-100 bg-white px-5 py-4">
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="printReceipt()" class="flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <i class="fas fa-print"></i> Print <kbd class="{{ $kbd }}">P</kbd>
                </button>
                <button type="button" onclick="downloadReceipt()" class="flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                    <i class="fas fa-download"></i> Save
                </button>
                <button type="button" onclick="shareReceiptToWhatsApp()" class="flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 transition hover:border-green-300 hover:bg-green-50 hover:text-green-700">
                    <i class="fab fa-whatsapp text-sm"></i> WhatsApp
                </button>
            </div>
            <button type="button" onclick="startNewSale()" class="{{ $primary }} group h-12 w-full text-sm">
                <i class="fas fa-plus text-xs"></i> Next sale <kbd class="{{ $kbdOnDark }}">Enter</kbd>
            </button>
        </div>
    </div>
</div>

{{-- ==================== SAVED SALES ==================== --}}
<div id="saved-carts-modal" class="{{ $sheet }}">
    <div class="{{ $card }} sm:max-w-lg">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><i class="fas fa-bookmark"></i></span>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Saved sales</h3>
                    <p class="text-xs text-slate-500">Pick one up when the customer comes back. Any till can resume it.</p>
                </div>
            </div>
            <button type="button" onclick="closeSavedCartsModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>
        <div id="saved-carts-list" class="space-y-2 px-5 py-4"></div>
    </div>
</div>

{{-- ==================== MESSAGE ==================== --}}
<div id="pos-alert-modal" class="pos-overlay fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
        <div id="pos-alert-icon-container" class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full text-xl"></div>
        <h4 id="pos-alert-title" class="text-base font-semibold text-slate-900"></h4>
        <p id="pos-alert-message" class="mt-1.5 text-sm leading-relaxed text-slate-500"></p>
        <button type="button" onclick="closePosAlert()" class="mt-5 h-11 w-full rounded-xl bg-slate-900 text-sm font-semibold text-white transition hover:bg-slate-800">OK</button>
    </div>
</div>

{{-- ==================== CONFIRM ==================== --}}
<div id="pos-confirm-modal" class="pos-overlay fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
        <div id="pos-confirm-icon-container" class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-xl text-amber-600"><i class="fas fa-question"></i></div>
        <h4 id="pos-confirm-title" class="text-base font-semibold text-slate-900"></h4>
        <p id="pos-confirm-message" class="mt-1.5 text-sm leading-relaxed text-slate-500"></p>
        <div class="mt-5 grid grid-cols-2 gap-2">
            <button type="button" id="pos-confirm-cancel-btn" onclick="closePosConfirm(false)" class="h-11 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</button>
            <button type="button" id="pos-confirm-action-btn" onclick="closePosConfirm(true)" class="h-11 rounded-xl bg-rose-600 text-sm font-semibold text-white transition hover:bg-rose-700">Confirm</button>
        </div>
    </div>
</div>

{{-- ==================== RESUME: WHAT ABOUT THE CURRENT SALE? ==================== --}}
<div id="pos-resume-choice-modal" class="pos-overlay fixed inset-0 z-[65] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h4 class="text-base font-semibold text-slate-900">You already have a sale open</h4>
        <p class="mt-1 text-sm text-slate-500">What should happen to it before you resume <span id="resume-target-name" class="font-semibold text-slate-800"></span>?</p>
        <div class="mt-5 space-y-2">
            <button type="button" onclick="executeResumeChoice('park_current')" class="flex w-full items-center justify-between gap-3 rounded-xl bg-emerald-600 px-4 py-3 text-left text-sm font-semibold text-white transition hover:bg-emerald-700">
                <span><i class="fas fa-bookmark mr-2"></i>Save the current sale, then resume</span><i class="fas fa-arrow-right text-xs"></i>
            </button>
            <button type="button" onclick="executeResumeChoice('overwrite')" class="flex w-full items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-left text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                <span><i class="fas fa-trash-alt mr-2"></i>Discard the current sale, then resume</span><i class="fas fa-arrow-right text-xs"></i>
            </button>
            <button type="button" onclick="closeResumeChoiceModal()" class="h-11 w-full rounded-xl text-sm font-semibold text-slate-500 transition hover:bg-slate-100">Cancel</button>
        </div>
    </div>
</div>

{{-- ==================== DISCOUNT ==================== --}}
<div id="pos-discount-modal" class="{{ $sheet }}">
    <div class="{{ $card }} sm:max-w-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900">Discount</h3>
                <p class="text-xs text-slate-500">Sale subtotal <span id="discount-modal-subtotal" class="font-semibold tabular-nums text-slate-800">₦0</span></p>
            </div>
            <button type="button" onclick="closeDiscountModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div class="grid grid-cols-4 gap-2">
                @foreach ([5, 10, 15, 20] as $percent)
                    <button type="button" onclick="setDiscountPercent({{ $percent }})" class="h-10 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 transition hover:border-emerald-400 hover:bg-emerald-50 hover:text-emerald-700">{{ $percent }}%</button>
                @endforeach
            </div>

            <div>
                <label for="custom-discount-input" class="mb-1.5 block text-[13px] font-medium text-slate-700">Amount off</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">₦</span>
                    <input type="number" id="custom-discount-input" oninput="refreshDiscountApproval()" placeholder="0" step="50" min="0" inputmode="decimal" class="{{ $input }} pl-8 font-semibold tabular-nums">
                </div>
                <p id="discount-limit-note" class="mt-1.5 hidden text-xs text-slate-500"></p>
            </div>

            <div>
                <label for="discount-reason-input" class="mb-1.5 block text-[13px] font-medium text-slate-700">Reason <span id="discount-reason-required" class="hidden font-normal text-rose-600">· required</span></label>
                <div class="mb-2 flex flex-wrap gap-1.5">
                    @foreach (['Regular customer', 'Staff purchase', 'Bulk purchase', 'Price match', 'Near expiry'] as $quick)
                        <button type="button" onclick="document.getElementById('discount-reason-input').value = this.textContent.trim()" class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:border-slate-400 hover:text-slate-900">{{ $quick }}</button>
                    @endforeach
                </div>
                <input type="text" id="discount-reason-input" maxlength="255" placeholder="Pick one above or type your own" class="{{ $input }}">
            </div>

            {{-- Shown only when the discount is above this user's limit --}}
            <div id="discount-approval" class="hidden space-y-2 rounded-xl bg-blue-50 p-4 ring-1 ring-inset ring-blue-600/15">
                <p class="text-sm font-semibold text-blue-950"><i class="fas fa-user-shield mr-1.5 text-blue-600"></i>Manager approval needed</p>
                <p class="text-xs text-blue-800">Ask the owner or a supervisor to enter their own login. It is checked when the sale is completed, and you stay signed in.</p>
                <input type="email" id="discount-manager-email" autocomplete="off" placeholder="Manager's email" class="{{ $input }}">
                <input type="password" id="discount-manager-password" autocomplete="new-password" placeholder="Manager's password" class="{{ $input }}">
            </div>
        </div>

        <div class="sticky bottom-0 flex items-center gap-2 border-t border-slate-100 bg-white px-5 py-4">
            <button type="button" onclick="removeDiscount()" class="h-12 rounded-xl px-4 text-sm font-semibold text-rose-600 transition hover:bg-rose-50">Remove</button>
            <button type="button" onclick="applyDiscountModal()" class="{{ $primary }} h-12 flex-1 text-sm">Apply discount</button>
        </div>
    </div>
</div>

{{-- ==================== TODAY'S SALES (reprint) ==================== --}}
<div id="recent-sales-modal" class="{{ $sheet }}">
    <div class="{{ $card }} sm:max-w-lg">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600"><i class="fas fa-clock-rotate-left"></i></span>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Your sales today</h3>
                    <p class="text-xs text-slate-500">Reprint a receipt, or open a sale to void or refund it.</p>
                </div>
            </div>
            <button type="button" onclick="closeRecentSalesModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>
        <div id="recent-sales-list" class="space-y-2 px-5 py-4"></div>
    </div>
</div>

{{-- ==================== KEYBOARD SHORTCUTS (F1) ==================== --}}
@php
    $shortcutGroups = [
        'Selling' => [
            ['F2', 'Search or scan a product'],
            ['F3', 'Type the customer’s name or phone'],
            ['F4', 'Give a discount'],
            ['F9', 'Checkout: take payment'],
            ['Del', 'Clear the current sale'],
        ],
        'Saved sales' => [
            ['F7', 'Save this sale for later'],
            ['F8', 'Open saved sales'],
        ],
        'Taking payment' => [
            ['1 – 4', 'Cash, Card, Transfer, Valu (hold Alt while typing in a box). In a split payment, it sets the line you are on'],
            ['F10', 'Complete the sale (Enter works too)'],
        ],
        'After the sale' => [
            ['P', 'Print the receipt'],
            ['Enter', 'Start the next sale'],
        ],
        'Anywhere' => [
            ['F1', 'Show this list'],
            ['Esc', 'Close the pop-up, or clear the search'],
        ],
    ];
@endphp
<div id="pos-shortcuts-modal" class="{{ str_replace('z-[60]', 'z-[66]', $sheet) }}">
    <div class="{{ $card }} sm:max-w-lg">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white"><i class="far fa-keyboard"></i></span>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Keyboard shortcuts</h3>
                    <p class="text-xs text-slate-500">Run the whole sale without touching the mouse.</p>
                </div>
            </div>
            <button type="button" onclick="closeShortcutsModal()" aria-label="Close" class="{{ $close }}"><i class="fas fa-times"></i></button>
        </div>

        <div class="space-y-5 px-5 py-4">
            <p class="rounded-xl bg-emerald-50 px-4 py-3 text-xs leading-relaxed text-emerald-900 ring-1 ring-inset ring-emerald-600/15">
                <i class="fas fa-barcode mr-1"></i> <span class="font-semibold">Just scan.</span> The scanner and typing always go to the search box, even if you have not clicked it.
            </p>
            @foreach ($shortcutGroups as $groupName => $shortcuts)
                <div>
                    <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $groupName }}</h4>
                    <dl class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">
                        @foreach ($shortcuts as [$keys, $action])
                            <div class="flex items-center justify-between gap-4 px-3.5 py-2.5">
                                <dt class="text-sm text-slate-700">{{ $action }}</dt>
                                <dd class="flex-shrink-0"><kbd class="inline-flex h-6 min-w-[1.75rem] items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-2 font-sans text-xs font-semibold text-slate-700 shadow-[0_1px_0_0_#e2e8f0]">{{ $keys }}</kbd></dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ==================== TOAST ==================== --}}
<div id="pos-toast" class="pos-overlay pointer-events-none fixed inset-x-4 bottom-24 z-[80] mx-auto hidden w-fit max-w-sm items-center gap-3 rounded-xl bg-slate-900 px-4 py-3 text-white shadow-2xl xl:bottom-6 xl:left-auto xl:right-6 xl:mx-0">
    <div id="pos-toast-icon" class="text-base text-emerald-400"><i class="fas fa-check-circle"></i></div>
    <p id="pos-toast-msg" class="text-sm font-medium leading-snug"></p>
</div>

@endsection

@push('scripts')
@include('partials.receipt-script')
<script>
    // --- STATE ---
    const rawCatalog = @json($medications);
    const posPharmacyName = @json($pharmacy->name ?? '');
    const posConfig = @json($posConfig);
    const posCsrfToken = "{{ csrf_token() }}";
    const posSaleRoute = posConfig.saleUrl;

    // Whole days from today until a YYYY-MM-DD date (negative = already past).
    function daysUntil(dateText) {
        if (!dateText) return null;
        return Math.round((new Date(dateText + 'T00:00:00') - new Date(posConfig.today + 'T00:00:00')) / 86400000);
    }

    let catalog = rawCatalog.map(item => {
        const expiresIn = daysUntil(item.expiry_date);
        return {
            id: item.id,
            name: item.name,
            dosage: item.dosage || '',
            category: item.category || 'General',
            is_rx: (item.category || '').toLowerCase().includes('prescription') || (item.category || '').toLowerCase().includes('rx'),
            price: parseFloat(item.price) || 0,
            stock: parseInt(item.stock_level) || 0,
            // Left empty when the product has none — a made-up batch or expiry
            // on a pharmacy receipt would be worse than a blank.
            sku: item.sku || '',
            barcode: item.barcode || '',
            batch_number: item.batch_number || '',
            expiry_date: item.expiry_date || '',
            expiresIn: expiresIn,
            expired: expiresIn !== null && expiresIn < 0,
            description: item.description || '',
            photo: item.photo_url || ''
        };
    });

    let cart = [];
    let currentDiscount = 0;
    let discountReason = '';
    // A manager's login typed in to approve a discount above the seller's
    // limit. Kept in memory only, never saved, and cleared after the sale.
    let discountApproval = null;
    // The customer record attached to this sale, or null for a walk-in.
    let selectedCustomer = null;
    let selectedPayment = 'cash';
    let activeCategory = 'ALL';
    let searchQuery = '';
    let selectedModalProduct = null;
    let modalSelectedQty = 1;
    let pendingResumeIndex = null;
    let confirmCallback = null;
    let toastTimeout = null;

    // Sales parked with "Save for Later". They live on the server, so they
    // survive a refresh and can be resumed on any till in the pharmacy.
    let savedCarts = [];

    // Split payment lines while "Split the payment" is on.
    let splitMode = false;
    let splitLines = [];
    let activeSplitLine = 0;

    // --- INITIALIZATION ---
    document.addEventListener('DOMContentLoaded', () => {
        updateShiftClock();
        setInterval(updateShiftClock, 30000);
        restoreCartState();
        renderProductGrid();
        renderCart();
        setupSearchListeners();
        setupCustomerLookup();
        setupKeyboardShortcuts();
        loadSavedCarts();
    });

    // --- TODAY'S SALES: REPRINT A RECEIPT ---
    async function openRecentSalesModal() {
        const list = document.getElementById('recent-sales-list');
        list.innerHTML = '<p class="py-10 text-center text-sm text-slate-400"><i class="fas fa-spinner fa-spin mr-2"></i>Loading your sales…</p>';
        document.getElementById('recent-sales-modal').classList.remove('hidden');

        try {
            const data = await posApi('GET', posConfig.recentUrl);
            renderRecentSales(data.sales || []);
        } catch (error) {
            list.innerHTML = `<p class="py-10 text-center text-sm text-rose-600">${escapeHtml(error.message)}</p>`;
        }
    }

    function closeRecentSalesModal() {
        document.getElementById('recent-sales-modal').classList.add('hidden');
    }

    function renderRecentSales(sales) {
        const list = document.getElementById('recent-sales-list');
        if (sales.length === 0) {
            list.innerHTML = `
                <div class="px-4 py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-receipt"></i></div>
                    <p class="mt-3 text-sm font-semibold text-slate-800">No sales yet today</p>
                    <p class="mx-auto mt-1 max-w-xs text-xs text-slate-500">Every sale you complete today shows here, ready to reprint.</p>
                </div>`;
            return;
        }

        const statusChip = {
            voided: 'bg-slate-100 text-slate-600 ring-slate-500/15',
            partially_refunded: 'bg-amber-50 text-amber-700 ring-amber-600/15',
            refunded: 'bg-rose-50 text-rose-700 ring-rose-600/15'
        };

        list.innerHTML = sales.map(sale => `
            <div class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:border-slate-300">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold tabular-nums text-slate-900">${escapeHtml(sale.receipt_no)}</span>
                        ${statusChip[sale.status] ? `<span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset ${statusChip[sale.status]}">${escapeHtml(sale.status_label)}</span>` : ''}
                    </div>
                    <p class="mt-0.5 truncate text-xs text-slate-500">${escapeHtml(sale.time)} · ${escapeHtml(sale.customer)} · ${sale.items} ${sale.items === 1 ? 'product' : 'products'}</p>
                    <p class="mt-0.5 truncate text-xs text-slate-400">${escapeHtml(sale.payment)}</p>
                </div>
                <div class="flex-shrink-0 text-right">
                    <p class="text-sm font-bold tabular-nums ${sale.status === 'voided' ? 'text-slate-400 line-through' : 'text-slate-900'}">₦${formatMoney(sale.total)}</p>
                    <div class="mt-1.5 flex items-center justify-end gap-1">
                        <a href="${escapeHtml(sale.url)}" target="_blank" rel="noopener" title="Open this sale (void or refund)" class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"><i class="fas fa-up-right-from-square text-xs"></i></a>
                        <button type="button" onclick="reprintSale(${sale.id}, this)" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 text-xs font-semibold text-slate-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700 disabled:opacity-50">
                            <i class="fas fa-print text-[10px]"></i> Reprint
                        </button>
                    </div>
                </div>
            </div>`).join('');
    }

    async function reprintSale(id, button) {
        if (button.disabled) return;
        button.disabled = true;
        try {
            const data = await posApi('POST', posConfig.reprintUrl.replace('__SALE__', id));
            closeRecentSalesModal();
            populateAndOpenReceipt(data.receipt, { copy: true });
        } catch (error) {
            showPosAlert('Could not reprint', error.message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    function updateShiftClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const el = document.getElementById('pos-shift-clock');
        if (el) el.textContent = timeStr;
    }

    function focusSearchInput() {
        const input = document.getElementById('pos-search-input');
        if (input) {
            input.focus();
            input.select();
        }
    }

    // Talks to the server for customers and held sales.
    async function posApi(method, url, body) {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': posCsrfToken },
            body: body ? JSON.stringify(body) : undefined
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Something went wrong. Please try again.');
        return data;
    }

    // A product's photo, or a tile with its initials when it has none.
    function productTile(item, sizeClass, radius = 'rounded-xl') {
        const initials = escapeHtml((item.name.replace(/[^A-Za-z]/g, '') || '?').slice(0, 2).toUpperCase());
        // The initials sit underneath; a photo that fails to load removes
        // itself and leaves them showing instead of a broken-image icon.
        return `
            <div class="${sizeClass} ${radius} relative flex flex-shrink-0 items-center justify-center overflow-hidden bg-emerald-50 font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                ${initials}
                ${item.photo ? `<img src="${escapeHtml(item.photo)}" alt="" onerror="this.remove()" class="absolute inset-0 w-full h-full object-cover">` : ''}
            </div>`;
    }

    // --- KEEP THE CURRENT SALE THROUGH A PAGE REFRESH ---
    function saveCartState() {
        try {
            if (cart.length === 0 && !selectedCustomer) {
                localStorage.removeItem(posConfig.cartKey);
                return;
            }
            localStorage.setItem(posConfig.cartKey, JSON.stringify({
                items: cart.map(i => ({ id: i.id, quantity: i.quantity })),
                discount: currentDiscount,
                reason: discountReason,
                customer: selectedCustomer,
                customerName: document.getElementById('pos-customer-name')?.value || '',
                customerPhone: document.getElementById('pos-customer-phone')?.value || ''
            }));
        } catch (e) { /* storage full or blocked: the sale simply is not remembered */ }
    }

    // Rebuilds a cart from product ids and quantities using today's catalog,
    // so prices and stock are current. Returns the names it had to leave out.
    function buildCartFrom(items) {
        const built = [];
        const leftOut = [];

        (items || []).forEach(saved => {
            const product = catalog.find(p => p.id === saved.id);
            if (!product || product.stock < 1 || product.expired) {
                leftOut.push(saved.name || (product ? product.name : 'A product'));
                return;
            }
            built.push({
                id: product.id,
                name: product.name,
                batch_number: product.batch_number,
                photo: product.photo,
                price: product.price,
                stock: product.stock,
                quantity: Math.max(1, Math.min(parseInt(saved.quantity) || 1, product.stock))
            });
        });

        return { built, leftOut };
    }

    function restoreCartState() {
        let state = null;
        try { state = JSON.parse(localStorage.getItem(posConfig.cartKey) || 'null'); } catch (e) { state = null; }
        if (!state) return;

        const { built, leftOut } = buildCartFrom(state.items);
        cart = built;
        currentDiscount = cart.length ? (parseFloat(state.discount) || 0) : 0;
        discountReason = state.reason || '';

        // A discount above the limit needed a manager's login, which is never
        // saved. It has to be approved again.
        if (isOverDiscountLimit(currentDiscount)) {
            currentDiscount = 0;
            discountReason = '';
        }

        if (state.customerName) document.getElementById('pos-customer-name').value = state.customerName;
        if (state.customerPhone) document.getElementById('pos-customer-phone').value = state.customerPhone;
        selectedCustomer = state.customer || null;
        showCustomerStatus();

        if (cart.length) showPosToast('Your unfinished sale was restored', 'info');
        if (leftOut.length) showPosAlert('Some items were left out', `No longer available: ${leftOut.join(', ')}.`, 'info');
    }

    // --- SEARCH & FILTER ---
    function setupSearchListeners() {
        const input = document.getElementById('pos-search-input');
        if (!input) return;

        input.addEventListener('input', (e) => {
            searchQuery = e.target.value.trim().toLowerCase();
            renderProductGrid();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleBarcodeEnterScan(input.value.trim());
            }
        });
    }

    function filteredCatalog() {
        return catalog.filter(item => {
            const matchesCat = (activeCategory === 'ALL') || (item.category === activeCategory);
            const matchesSearch = !searchQuery ||
                item.name.toLowerCase().includes(searchQuery) ||
                item.sku.toLowerCase().includes(searchQuery) ||
                item.barcode.toLowerCase().includes(searchQuery) ||
                item.category.toLowerCase().includes(searchQuery);
            return matchesCat && matchesSearch;
        });
    }

    function renderProductGrid() {
        const grid = document.getElementById('product-grid');
        const empty = document.getElementById('no-products-view');
        const filtered = filteredCatalog();

        if (filtered.length === 0) {
            grid.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }

        empty.classList.add('hidden');

        grid.innerHTML = filtered.map(product => {
            const badge = getCategoryBadge(product.category);
            const isOutOfStock = product.stock <= 0;
            const blocked = isOutOfStock || product.expired;
            const isLow = !isOutOfStock && product.stock <= 20;
            const stockDot = isOutOfStock ? 'bg-rose-500' : (isLow ? 'bg-amber-500' : 'bg-emerald-500');
            const stockClass = isOutOfStock ? 'text-rose-600' : (isLow ? 'text-amber-700' : 'text-slate-500');
            const stockText = isOutOfStock ? 'Out of stock' : `${product.stock} in stock`;
            const chip = 'rounded-full px-2 py-0.5 text-[10px] font-medium';

            let expiryBadge = '';
            if (product.expired) {
                expiryBadge = `<span class="${chip} bg-rose-600 text-white">Expired</span>`;
            } else if (product.expiresIn !== null && product.expiresIn <= 90) {
                expiryBadge = `<span class="${chip} bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/15" title="Expires ${escapeHtml(product.expiry_date)}">Exp. ${product.expiresIn}d</span>`;
            }

            return `
                <div
                    onclick="openProductDetailModal(${product.id})"
                    class="group relative flex cursor-pointer flex-col rounded-[5px] border border-slate-200 bg-white p-3 transition hover:border-emerald-300 hover:shadow-lg hover:shadow-slate-200/60 sm:p-3.5"
                >
                    <span data-in-cart="${product.id}" class="absolute -right-1.5 -top-1.5 z-[1] hidden h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-slate-900 px-1.5 text-[11px] font-semibold tabular-nums text-white ring-2 ring-white"></span>

                    <div class="flex items-start gap-2.5 sm:gap-3 ${blocked ? 'opacity-60' : ''}">
                        ${productTile(product, 'h-10 w-10 text-xs sm:h-11 sm:w-11', 'rounded-[5px]')}
                        <div class="min-w-0 flex-1">
                            <h4 class="line-clamp-2 break-words text-[13px] font-semibold leading-snug text-slate-900 sm:text-sm" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</h4>
                            <p class="mt-0.5 truncate text-[11px] text-slate-500">${escapeHtml(product.dosage || product.sku) || '&nbsp;'}</p>
                        </div>
                    </div>

                    <div class="mt-2.5 flex flex-wrap items-center gap-1 ${blocked ? 'opacity-60' : ''}">
                        <span class="${chip} max-w-full truncate ${badge.bg} ${badge.text}">${escapeHtml(badge.label)}</span>
                        ${product.is_rx ? `<span class="${chip} bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/15">Rx</span>` : ''}
                        ${expiryBadge}
                    </div>

                    <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                        <div class="min-w-0">
                            <p class="truncate text-[15px] font-bold tabular-nums leading-tight text-slate-900 sm:text-base">₦${formatMoney(product.price)}</p>
                            <p class="mt-1 flex items-center gap-1.5 text-[11px] font-medium ${stockClass}">
                                <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full ${stockDot}"></span>${stockText}
                            </p>
                        </div>

                        <button
                            type="button"
                            onclick="event.stopPropagation(); addToCart(${product.id})"
                            ${blocked ? 'disabled' : ''}
                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-[5px] transition ${blocked ? 'cursor-not-allowed bg-slate-100 text-slate-300' : 'bg-emerald-600 text-white shadow-sm shadow-emerald-600/20 hover:bg-emerald-700 active:scale-95'}"
                            title="${product.expired ? 'Expired: cannot be sold' : (isOutOfStock ? 'Out of stock' : 'Add to sale')}"
                            aria-label="Add ${escapeHtml(product.name)} to the sale"
                        >
                            <i class="fas fa-plus text-sm"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        syncCartBadges();
    }

    // The small dark number on a product card: how many are already in the sale.
    function syncCartBadges() {
        document.querySelectorAll('[data-in-cart]').forEach(el => {
            const qty = cartQuantityOf(Number(el.dataset.inCart));
            el.textContent = qty;
            el.classList.toggle('hidden', qty === 0);
            el.classList.toggle('flex', qty > 0);
        });
    }

    function getCategoryBadge(category) {
        switch (category) {
            case 'Prescription (Rx)':
                return { label: 'Rx Meds', bg: 'bg-emerald-50', text: 'text-emerald-700' };
            case 'Medical Devices':
                return { label: 'Devices', bg: 'bg-teal-50', text: 'text-teal-700' };
            case 'Personal Care & Hygiene':
                return { label: 'Personal', bg: 'bg-purple-50', text: 'text-purple-700' };
            case 'Baby & Child Care':
                return { label: 'Baby Care', bg: 'bg-amber-50', text: 'text-amber-700' };
            case 'OTC Medications':
                return { label: 'OTC', bg: 'bg-blue-50', text: 'text-blue-700' };
            default:
                // Any other category shows under its own name.
                return { label: category, bg: 'bg-slate-100', text: 'text-slate-700' };
        }
    }

    function filterCategory(cat) {
        activeCategory = cat;
        document.querySelectorAll('.category-pill').forEach(btn => {
            if (btn.dataset.cat === cat) {
                btn.className = 'category-pill active whitespace-nowrap rounded-full bg-slate-900 px-4 py-2 text-[13px] font-semibold text-white shadow-sm transition';
            } else {
                btn.className = 'category-pill whitespace-nowrap rounded-full border border-slate-200 bg-white px-4 py-2 text-[13px] font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900';
            }
        });
        renderProductGrid();
    }

    // --- PRODUCT FULL DETAILS MODAL ---
    function openProductDetailModal(productId) {
        const item = catalog.find(p => p.id === productId);
        if (!item) return;

        selectedModalProduct = item;
        modalSelectedQty = 1;

        document.getElementById('pdetail-photo').innerHTML = productTile(item, 'h-24 w-24 text-2xl sm:h-28 sm:w-28');
        document.getElementById('pdetail-title').textContent = item.name;
        document.getElementById('pdetail-dosage').textContent = item.dosage ? item.dosage : `Category: ${item.category}`;
        document.getElementById('pdetail-price').textContent = `₦${formatMoney(item.price)}`;
        const stockEl = document.getElementById('pdetail-stock');
        stockEl.textContent = item.stock > 0 ? `${item.stock} in stock` : 'Out of stock';
        stockEl.className = 'text-xs font-medium ' + (item.stock > 0 ? 'text-emerald-700' : 'text-rose-600');
        document.getElementById('pdetail-sku').textContent = item.sku || '—';
        document.getElementById('pdetail-barcode').textContent = item.barcode || '—';
        document.getElementById('pdetail-batch').textContent = item.batch_number || '—';
        document.getElementById('pdetail-expiry').textContent = item.expiry_date
            ? item.expiry_date + (item.expired ? ' (expired)' : (item.expiresIn <= 90 ? ` (${item.expiresIn} days left)` : ''))
            : '—';
        document.getElementById('pdetail-description').textContent = item.description || 'No description has been added for this product.';
        document.getElementById('pdetail-modal-qty').textContent = '1';

        const catBadge = document.getElementById('pdetail-category-badge');
        catBadge.textContent = item.category;

        const rxBadge = document.getElementById('pdetail-rx-badge');
        rxBadge.classList.toggle('hidden', !item.is_rx);

        const addBtn = document.getElementById('pdetail-add-btn');
        const blocked = item.stock < 1 || item.expired;
        addBtn.disabled = blocked;

        document.getElementById('product-detail-modal').classList.remove('hidden');
    }

    function closeProductDetailModal() {
        document.getElementById('product-detail-modal').classList.add('hidden');
        selectedModalProduct = null;
    }

    function adjustDetailModalQty(delta) {
        if (!selectedModalProduct) return;
        const newQty = modalSelectedQty + delta;
        if (newQty < 1) return;
        if (newQty > selectedModalProduct.stock) {
            showPosAlert('Stock Limit', `Only ${selectedModalProduct.stock} unit(s) available in stock.`, 'warning');
            return;
        }
        modalSelectedQty = newQty;
        document.getElementById('pdetail-modal-qty').textContent = modalSelectedQty;
    }

    function addDetailModalToCart() {
        if (!selectedModalProduct) return;

        const before = cartQuantityOf(selectedModalProduct.id);
        for (let i = 0; i < modalSelectedQty; i++) {
            addToCart(selectedModalProduct.id, false);
        }
        const added = cartQuantityOf(selectedModalProduct.id) - before;
        if (added > 0) showPosToast(`Added ${added}x ${selectedModalProduct.name} to sale`);
        closeProductDetailModal();
    }

    function cartQuantityOf(productId) {
        const line = cart.find(item => item.id === productId);
        return line ? line.quantity : 0;
    }

    // --- BARCODE / SKU ENTER ---
    // A scan (or a typed code) adds the product straight away. Typed words
    // only add when a single product is left, so the wrong one is never
    // added by a near match.
    function handleBarcodeEnterScan(code) {
        if (!code) return;
        const input = document.getElementById('pos-search-input');
        const needle = code.toLowerCase();

        const exact = catalog.find(item =>
            (item.barcode && item.barcode.toLowerCase() === needle) ||
            (item.sku && item.sku.toLowerCase() === needle)
        );
        const visible = filteredCatalog();
        const chosen = exact || (visible.length === 1 ? visible[0] : null);

        if (chosen) {
            addToCart(chosen.id);
            input.value = '';
            searchQuery = '';
            renderProductGrid();
        } else if (visible.length === 0) {
            showPosAlert('Item Not Found', `No product found matching: "${code}"`, 'info');
        } else {
            showPosToast(`${visible.length} products match. Tap the one you want.`, 'info');
        }
    }

    // --- CART ACTIONS ---
    function addToCart(productId, showToastAlert = true) {
        const product = catalog.find(p => p.id === productId);
        if (!product) return;

        if (product.expired) {
            showPosAlert('Expired Product', `"${product.name}" expired on ${product.expiry_date} and cannot be sold.`, 'error');
            return;
        }

        const existing = cart.find(item => item.id === productId);

        if (existing) {
            if (existing.quantity + 1 > product.stock) {
                showPosAlert('Stock Limit', `Maximum available stock for "${product.name}" is ${product.stock}.`, 'warning');
                return;
            }
            existing.quantity += 1;
        } else {
            if (product.stock < 1) {
                showPosAlert('Out of Stock', `"${product.name}" is currently out of stock.`, 'error');
                return;
            }
            cart.push({
                id: product.id,
                name: product.name,
                batch_number: product.batch_number,
                photo: product.photo,
                price: product.price,
                stock: product.stock,
                quantity: 1
            });
        }

        if (showToastAlert) {
            showPosToast(`Added ${product.name} to cart`);
        }
        renderCart();
    }

    function updateCartQty(productId, delta, event) {
        if (event) event.stopPropagation();
        const item = cart.find(i => i.id === productId);
        if (!item) return;
        setCartQty(productId, item.quantity + delta);
    }

    // Sets a line to an exact quantity (typed in, or from the - / + buttons).
    function setCartQty(productId, value) {
        const item = cart.find(i => i.id === productId);
        if (!item) return;

        const newQty = parseInt(value, 10);

        if (isNaN(newQty) || newQty <= 0) {
            cart = cart.filter(i => i.id !== productId);
            showPosToast(`Removed ${item.name} from sale`, 'info');
        } else if (newQty > item.stock) {
            item.quantity = item.stock;
            showPosAlert('Stock Limit', `Only ${item.stock} of "${item.name}" are in stock, so the quantity was set to ${item.stock}.`, 'warning');
        } else {
            item.quantity = newQty;
        }

        renderCart();
    }

    function resetSale() {
        cart = [];
        currentDiscount = 0;
        discountReason = '';
        discountApproval = null;
        clearCustomer(false);
    }

    function promptClearCart() {
        if (cart.length === 0) {
            showPosToast('Current sale is already empty', 'info');
            return;
        }
        showPosConfirm({
            title: 'Clear Current Sale?',
            message: `This will remove all ${cart.length} item(s) from the current sale cart. Are you sure?`,
            confirmText: 'Yes, Clear Cart',
            cancelText: 'Cancel',
            type: 'danger',
            onConfirm: () => {
                resetSale();
                renderCart();
                showPosToast('Cart cleared', 'info');
            }
        });
    }

    // --- CUSTOMER: FIND A RETURNING CUSTOMER, OR JUST TYPE A NEW ONE ---
    function setupCustomerLookup() {
        const nameInput = document.getElementById('pos-customer-name');
        const phoneInput = document.getElementById('pos-customer-phone');
        const panel = document.getElementById('pos-customer-suggestions');
        if (!nameInput || !phoneInput || !panel) return;

        let timer = null;

        const lookup = (term) => {
            clearTimeout(timer);
            term = term.trim();
            if (term.length < 2 || term.toLowerCase() === 'walk-in customer') {
                panel.classList.add('hidden');
                return;
            }
            timer = setTimeout(async () => {
                try {
                    const data = await posApi('GET', posConfig.customersUrl + '?q=' + encodeURIComponent(term));
                    renderCustomerSuggestions(data.customers || []);
                } catch (e) {
                    panel.classList.add('hidden');
                }
            }, 250);
        };

        [nameInput, phoneInput].forEach(input => {
            input.addEventListener('input', () => {
                // Typing over a picked customer means it is someone else now.
                if (selectedCustomer) { selectedCustomer = null; }
                showCustomerStatus();
                saveCartState();
                lookup(input.value);
            });
        });

        nameInput.addEventListener('focus', () => nameInput.select());

        panel.addEventListener('click', (event) => {
            const row = event.target.closest('[data-customer]');
            if (!row) return;
            selectCustomer(JSON.parse(row.dataset.customer));
        });

        document.addEventListener('mousedown', (event) => {
            if (!event.target.closest('#pos-customer-box')) panel.classList.add('hidden');
        });
    }

    function renderCustomerSuggestions(customers) {
        const panel = document.getElementById('pos-customer-suggestions');
        if (customers.length === 0) {
            panel.classList.add('hidden');
            return;
        }

        panel.innerHTML = `
            <p class="border-b border-slate-100 bg-slate-50 px-3.5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Returning customers</p>
            ${customers.map(customer => `
                <button type="button" data-customer="${escapeHtml(JSON.stringify(customer))}" class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-3.5 py-2.5 text-left transition last:border-0 hover:bg-emerald-50">
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-900">${escapeHtml(customer.name || 'No name yet')}</span>
                        <span class="block text-xs tabular-nums text-slate-500">${escapeHtml(customer.phone)}</span>
                    </span>
                    <span class="flex-shrink-0 text-right text-[11px] text-slate-500">
                        ${customer.purchases} ${customer.purchases === 1 ? 'purchase' : 'purchases'}${customer.last_purchase ? '<br>last ' + escapeHtml(customer.last_purchase) : ''}
                    </span>
                </button>
            `).join('')}
        `;
        panel.classList.remove('hidden');
    }

    function selectCustomer(customer) {
        selectedCustomer = { id: customer.id, name: customer.name || '', phone: customer.phone, purchases: customer.purchases || 0 };
        document.getElementById('pos-customer-name').value = customer.name || 'Walk-in Customer';
        document.getElementById('pos-customer-phone').value = customer.phone;
        document.getElementById('pos-customer-suggestions').classList.add('hidden');
        showCustomerStatus();
        saveCartState();
    }

    function clearCustomer(save = true) {
        selectedCustomer = null;
        const nameInput = document.getElementById('pos-customer-name');
        const phoneInput = document.getElementById('pos-customer-phone');
        if (nameInput) nameInput.value = 'Walk-in Customer';
        if (phoneInput) phoneInput.value = '';
        showCustomerStatus();
        if (save) saveCartState();
    }

    // The small line under the customer boxes: who this sale is attached to.
    function showCustomerStatus() {
        const status = document.getElementById('pos-customer-status');
        const clearBtn = document.getElementById('pos-customer-clear');
        if (!status) return;

        const name = document.getElementById('pos-customer-name').value.trim();
        const phone = document.getElementById('pos-customer-phone').value.replace(/\D/g, '');
        const isWalkIn = !selectedCustomer && phone.length < 7;

        if (selectedCustomer) {
            status.innerHTML = `<i class="fas fa-check-circle text-emerald-600"></i> Returning customer · ${selectedCustomer.purchases} previous ${selectedCustomer.purchases === 1 ? 'purchase' : 'purchases'}`;
            status.className = 'mt-1 text-[10px] font-medium text-emerald-700';
        } else if (!isWalkIn) {
            status.innerHTML = '<i class="fas fa-user-plus text-blue-500"></i> Will be saved as a customer with this phone number';
            status.className = 'mt-1 text-[10px] font-medium text-blue-700';
        } else {
            status.innerHTML = 'Add a phone number to keep this customer\'s purchase history';
            status.className = 'mt-1 text-[10px] text-slate-400';
        }

        if (clearBtn) {
            const untouched = isWalkIn && (name === '' || name === 'Walk-in Customer');
            clearBtn.classList.toggle('hidden', untouched);
            clearBtn.classList.toggle('flex', !untouched);
        }
    }

    // --- DISCOUNT ---
    // The most this user may take off the current sale without a manager,
    // in naira. null = no limit (owner and supervisors).
    function discountLimitAmount() {
        if (posConfig.discountLimit === null) return null;
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        return Math.floor(subtotal * posConfig.discountLimit) / 100;
    }

    function isOverDiscountLimit(amount) {
        const limit = discountLimitAmount();
        return limit !== null && amount > limit;
    }

    function refreshDiscountApproval() {
        const amount = parseFloat(document.getElementById('custom-discount-input').value) || 0;
        const over = isOverDiscountLimit(amount);
        document.getElementById('discount-approval').classList.toggle('hidden', !over);
        document.getElementById('discount-reason-required').classList.toggle('hidden', !over);
    }

    function openDiscountModal() {
        if (cart.length === 0) {
            showPosToast('Add a product before giving a discount', 'info');
            return;
        }
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        document.getElementById('discount-modal-subtotal').textContent = `₦${formatMoney(subtotal)}`;
        document.getElementById('custom-discount-input').value = currentDiscount > 0 ? currentDiscount : '';
        document.getElementById('discount-reason-input').value = discountReason;
        document.getElementById('discount-manager-email').value = discountApproval ? discountApproval.email : '';
        document.getElementById('discount-manager-password').value = '';

        const limitNote = document.getElementById('discount-limit-note');
        const limit = discountLimitAmount();
        limitNote.classList.toggle('hidden', limit === null);
        if (limit !== null) {
            limitNote.textContent = `You can give up to ${posConfig.discountLimit}% (₦${formatMoney(limit)}) on your own. Anything more needs a manager's approval.`;
        }

        refreshDiscountApproval();
        document.getElementById('pos-discount-modal').classList.remove('hidden');
    }

    function closeDiscountModal() {
        document.getElementById('pos-discount-modal').classList.add('hidden');
    }

    function setDiscountPercent(percent) {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const calcDiscount = Math.round((subtotal * percent) / 100);
        document.getElementById('custom-discount-input').value = calcDiscount;
        refreshDiscountApproval();
    }

    function applyDiscountModal() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const val = parseFloat(document.getElementById('custom-discount-input').value) || 0;
        const reason = document.getElementById('discount-reason-input').value.trim();

        if (val < 0) {
            showPosAlert('Invalid Discount', 'Discount cannot be negative.', 'warning');
            return;
        }
        if (val > subtotal) {
            showPosAlert('Discount Exceeds Total', 'Discount cannot be greater than the sale subtotal.', 'warning');
            return;
        }

        if (isOverDiscountLimit(val)) {
            const email = document.getElementById('discount-manager-email').value.trim();
            const password = document.getElementById('discount-manager-password').value;

            if (!reason) {
                showPosAlert('Reason Needed', 'Say why this customer is getting a discount this large.', 'warning');
                return;
            }
            if (!email || !password) {
                showPosAlert('Manager Approval Needed', `This discount is above your ${posConfig.discountLimit}% limit. Ask the owner or a supervisor to enter their email and password.`, 'warning');
                return;
            }
            discountApproval = { email, password };
        } else {
            discountApproval = null;
        }

        currentDiscount = val;
        discountReason = val > 0 ? reason : '';
        recalculateTotals();
        saveCartState();
        closeDiscountModal();
        showPosToast(val > 0 ? `Applied ₦${formatMoney(currentDiscount)} discount` : 'Discount removed', val > 0 ? 'success' : 'info');
    }

    function removeDiscount() {
        currentDiscount = 0;
        discountReason = '';
        discountApproval = null;
        recalculateTotals();
        saveCartState();
        closeDiscountModal();
        showPosToast('Discount removed', 'info');
    }

    // --- RENDER CART ---
    // One sale panel serves every screen size: a side column on wide
    // screens, a slide-in sheet on phones and tablets.
    function renderCart() {
        const listDesktop = document.getElementById('cart-items-list-desktop');
        const emptyDesktop = document.getElementById('cart-empty-view-desktop');
        const checkoutDesktop = document.getElementById('checkout-btn-desktop');
        const countBadge = document.getElementById('cart-item-count-badge');
        const headerBadge = document.getElementById('header-cart-badge');

        const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);

        if (countBadge) {
            countBadge.textContent = `${totalQty} ${totalQty === 1 ? 'item' : 'items'}`;
        }
        if (headerBadge) {
            headerBadge.textContent = totalQty;
            headerBadge.classList.toggle('hidden', totalQty === 0);
        }

        if (cart.length === 0) {
            // Nothing left to discount.
            currentDiscount = 0;
            discountReason = '';
            discountApproval = null;

            if (listDesktop) {
                listDesktop.innerHTML = '';
                listDesktop.classList.add('hidden');
            }
            if (emptyDesktop) emptyDesktop.classList.remove('hidden');
            if (checkoutDesktop) checkoutDesktop.disabled = true;

            syncCartBadges();
            recalculateTotals();
            saveCartState();
            return;
        }

        if (emptyDesktop) emptyDesktop.classList.add('hidden');
        if (checkoutDesktop) checkoutDesktop.disabled = false;

        const itemsHtml = cart.map(item => `
            <div class="flex items-center gap-3 py-2">
                ${productTile(item, 'h-10 w-10 text-xs')}

                <div class="min-w-0 flex-1">
                    <h5 class="truncate text-sm font-medium text-slate-900" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h5>
                    <p class="mt-0.5 truncate text-xs tabular-nums text-slate-500">
                        ₦${formatMoney(item.price)} each${item.batch_number ? ' · Batch ' + escapeHtml(item.batch_number) : ''}
                    </p>
                </div>

                <div class="flex flex-shrink-0 flex-col items-end gap-1">
                    <span class="text-sm font-semibold tabular-nums text-slate-900">₦${formatMoney(item.price * item.quantity)}</span>

                    <div class="inline-flex h-7 items-center rounded-lg border border-slate-200 bg-white">
                        <button type="button" onclick="updateCartQty(${item.id}, -1, event)" class="flex h-full w-8 items-center justify-center text-slate-500 transition hover:text-rose-600" aria-label="One less">
                            <i class="fas ${item.quantity === 1 ? 'fa-trash-alt' : 'fa-minus'} text-[10px]"></i>
                        </button>
                        <input type="number" min="1" max="${item.stock}" value="${item.quantity}" inputmode="numeric" aria-label="Quantity of ${escapeHtml(item.name)}"
                            onchange="setCartQty(${item.id}, this.value)" onclick="this.select()" onkeydown="if (event.key === 'Enter') this.blur()"
                            class="pos-qty h-full w-9 border-x border-slate-200 bg-transparent p-0 text-center text-sm font-semibold tabular-nums text-slate-900 focus:bg-emerald-50 focus:outline-none focus:ring-0">
                        <button type="button" onclick="updateCartQty(${item.id}, 1, event)" class="flex h-full w-8 items-center justify-center text-slate-500 transition hover:text-emerald-700" aria-label="One more">
                            <i class="fas fa-plus text-[10px]"></i>
                        </button>
                    </div>
                </div>
            </div>
        `).join('');

        if (listDesktop) {
            listDesktop.innerHTML = itemsHtml;
            listDesktop.classList.remove('hidden');
        }

        syncCartBadges();
        recalculateTotals();
        saveCartState();
    }

    function recalculateTotals() {
        const totalItemsCount = cart.reduce((sum, item) => sum + item.quantity, 0);
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        // Removing items can leave a discount bigger than the sale itself.
        if (currentDiscount > subtotal) currentDiscount = subtotal;
        const netTotal = Math.max(0, subtotal - currentDiscount);

        const qtyLabel = `${totalItemsCount} ${totalItemsCount === 1 ? 'item' : 'items'}`;

        const subDesk = document.getElementById('summary-subtotal-desktop');
        if (subDesk) subDesk.textContent = `₦${formatMoney(subtotal)}`;
        const discDesk = document.getElementById('summary-discount-desktop');
        if (discDesk) discDesk.textContent = `₦${formatMoney(currentDiscount)}`;
        const netDesk = document.getElementById('summary-net-total-desktop');
        if (netDesk) netDesk.textContent = `₦${formatMoney(netTotal)}`;

        const barQty = document.getElementById('mobile-bar-qty');
        if (barQty) barQty.textContent = qtyLabel;
        const barTotal = document.getElementById('mobile-bar-total');
        if (barTotal) barTotal.textContent = `₦${formatMoney(netTotal)}`;

        document.getElementById('modal-payable-total').textContent = `₦${formatMoney(netTotal)}`;

        calculateCashChange();
    }

    // --- SALE PANEL ON PHONES AND TABLETS ---
    // On wide screens the panel is always showing and these do nothing visible.
    function openMobileCartDrawer() {
        document.getElementById('mobile-cart-drawer-backdrop').classList.remove('hidden');
        const drawer = document.getElementById('mobile-cart-drawer');
        drawer.classList.remove('hidden');
        drawer.classList.add('flex', 'mobile-drawer-open');
    }

    function closeMobileCartDrawer() {
        document.getElementById('mobile-cart-drawer-backdrop').classList.add('hidden');
        const drawer = document.getElementById('mobile-cart-drawer');
        drawer.classList.add('hidden');
        drawer.classList.remove('flex', 'mobile-drawer-open');
    }

    // --- SAVE CART FOR LATER / RESUME SYSTEM (STORED ON THE SERVER) ---
    async function loadSavedCarts() {
        try {
            const data = await posApi('GET', posConfig.heldUrl);
            savedCarts = data.held || [];
        } catch (e) {
            savedCarts = [];
        }
        updateSavedCartsBadge();
    }

    async function saveCartForLater() {
        if (cart.length === 0) {
            showPosAlert('Empty Cart', 'There are no items in the current sale to save.', 'info');
            return false;
        }

        const customerName = document.getElementById('pos-customer-name')?.value.trim() || 'Walk-in Customer';
        const customerPhone = document.getElementById('pos-customer-phone')?.value.trim() || '';

        try {
            const data = await posApi('POST', posConfig.heldUrl, {
                items: cart.map(i => ({ id: i.id, name: i.name, price: i.price, quantity: i.quantity })),
                customer_id: selectedCustomer ? selectedCustomer.id : null,
                customer_name: customerName,
                customer_phone: customerPhone,
                // A discount that needed a manager is not carried over: the
                // approval was for that moment and has to be given again.
                discount_amount: isOverDiscountLimit(currentDiscount) ? 0 : currentDiscount
            });
            savedCarts.unshift(data.held);
        } catch (error) {
            showPosAlert('Could Not Save', error.message, 'error');
            return false;
        }

        updateSavedCartsBadge();
        resetSale();
        renderCart();
        closeMobileCartDrawer();

        showPosToast(`Saved cart for "${customerName}"`, 'success');
        return true;
    }

    function updateSavedCartsBadge() {
        const count = savedCarts.length;
        const el1 = document.getElementById('saved-carts-count-desktop');
        if (el1) el1.textContent = count;
    }

    async function openSavedCartsModal() {
        document.getElementById('saved-carts-modal').classList.remove('hidden');
        // Another till may have parked or resumed a sale since this page loaded.
        await loadSavedCarts();
        renderSavedCarts();
    }

    function renderSavedCarts() {
        const list = document.getElementById('saved-carts-list');
        if (savedCarts.length === 0) {
            list.innerHTML = `
                <div class="px-4 py-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><i class="fas fa-bookmark"></i></div>
                    <p class="mt-3 text-sm font-semibold text-slate-800">No saved sales</p>
                    <p class="mx-auto mt-1 max-w-xs text-xs text-slate-500">
                        When a customer steps away, tap the bookmark button on the sale to keep their items and serve the next person.
                    </p>
                </div>
            `;
            return;
        }

        list.innerHTML = savedCarts.map((h, idx) => {
            const total = h.items.reduce((sum, i) => sum + (i.price * i.quantity), 0) - (h.discount || 0);
            const itemsSummary = h.items.map(i => `${i.name} (x${i.quantity})`).join(', ');

            return `
                <div class="flex flex-col gap-3 rounded-xl border border-slate-200 p-3.5 transition hover:border-slate-300 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="truncate text-sm font-semibold text-slate-900">${escapeHtml(h.customer)}</span>
                            <span class="flex-shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium tabular-nums text-amber-700 ring-1 ring-inset ring-amber-600/15">${escapeHtml(h.saved_at)}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-slate-500" title="${escapeHtml(itemsSummary)}">${escapeHtml(itemsSummary)}</p>
                        <p class="mt-1.5 text-xs text-slate-500">
                            <span class="text-sm font-semibold tabular-nums text-slate-900">₦${formatMoney(Math.max(0, total))}</span>
                            · ${h.items.length} ${h.items.length === 1 ? 'item' : 'items'} · saved by ${escapeHtml(h.saved_by)}
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-2">
                        <button type="button" onclick="requestResumeSale(${idx})" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 sm:flex-none">
                            <i class="fas fa-play text-[10px]"></i> Resume
                        </button>
                        <button type="button" onclick="promptDiscardSavedSale(${idx})" class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-400 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" title="Discard this saved sale" aria-label="Discard this saved sale">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function closeSavedCartsModal() {
        document.getElementById('saved-carts-modal').classList.add('hidden');
    }

    function requestResumeSale(idx) {
        if (cart.length > 0) {
            pendingResumeIndex = idx;
            const target = savedCarts[idx];
            document.getElementById('resume-target-name').textContent = `"${target.customer}" (${target.items.length} items)`;
            closeSavedCartsModal();
            document.getElementById('pos-resume-choice-modal').classList.remove('hidden');
            return;
        }

        executeResumeSale(savedCarts[idx]);
    }

    function closeResumeChoiceModal() {
        document.getElementById('pos-resume-choice-modal').classList.add('hidden');
        pendingResumeIndex = null;
    }

    async function executeResumeChoice(action) {
        if (pendingResumeIndex === null) return;
        const target = savedCarts[pendingResumeIndex];
        closeResumeChoiceModal();

        if (action === 'park_current') {
            const parked = await saveCartForLater();
            if (!parked) return;
        }
        executeResumeSale(target);
    }

    async function executeResumeSale(target) {
        if (!target) return;

        // Take it off the server first, so two tills cannot resume the same sale.
        try {
            await posApi('DELETE', posConfig.heldUrl + '/' + target.id);
        } catch (error) {
            showPosAlert('Could Not Resume', error.message, 'error');
            return;
        }
        savedCarts = savedCarts.filter(h => h.id !== target.id);

        const { built, leftOut } = buildCartFrom(target.items);
        cart = built;
        currentDiscount = cart.length ? (target.discount || 0) : 0;
        discountReason = '';
        discountApproval = null;

        document.getElementById('pos-customer-name').value = target.customer || 'Walk-in Customer';
        document.getElementById('pos-customer-phone').value = target.phone || '';
        selectedCustomer = target.customer_id ? { id: target.customer_id, name: target.customer, phone: target.phone, purchases: 0 } : null;
        showCustomerStatus();

        updateSavedCartsBadge();
        closeSavedCartsModal();
        renderCart();

        showPosToast(`Resumed sale for "${target.customer}"`, 'success');
        if (leftOut.length) showPosAlert('Some items were left out', `No longer available: ${leftOut.join(', ')}.`, 'info');
    }

    function promptDiscardSavedSale(idx) {
        const target = savedCarts[idx];
        showPosConfirm({
            title: 'Discard Saved Cart?',
            message: `Are you sure you want to permanently discard the saved cart for "${target.customer}"?`,
            confirmText: 'Yes, Discard',
            cancelText: 'Cancel',
            type: 'danger',
            onConfirm: async () => {
                try {
                    await posApi('DELETE', posConfig.heldUrl + '/' + target.id);
                } catch (error) {
                    showPosAlert('Could Not Discard', error.message, 'error');
                    return;
                }
                savedCarts = savedCarts.filter(h => h.id !== target.id);
                updateSavedCartsBadge();
                renderSavedCarts();
                document.getElementById('saved-carts-modal').classList.remove('hidden');
                showPosToast('Saved cart discarded', 'info');
            }
        });
    }

    // --- CHECKOUT & TENDER ---
    function openCheckoutModal() {
        if (cart.length === 0) return;
        recalculateTotals();

        // The cart may have shrunk since the discount was approved.
        if (isOverDiscountLimit(currentDiscount) && !discountApproval) {
            openDiscountModal();
            showPosToast('This discount needs a manager\'s approval', 'info');
            return;
        }

        // Every checkout starts as a single payment; Split is one tap away.
        splitMode = false;
        document.getElementById('split-payment-section').classList.add('hidden');
        document.getElementById('single-payment-section').classList.remove('hidden');

        selectPaymentMethod('cash');
        document.getElementById('checkout-modal').classList.remove('hidden');
        setCashTender('EXACT');
        focusPaymentField();
    }

    function amountDue() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        return Math.max(0, Math.round((subtotal - currentDiscount) * 100) / 100);
    }

    // --- SPLIT PAYMENT ---
    // Up to four payment lines, e.g. part cash and part transfer. Only cash
    // may be more than what is left; the change comes from it.
    const splitMethodLabels = { cash: 'Cash', pos_card: 'Card', bank_transfer: 'Transfer', valu_wallet: 'Valu' };
    const splitReferenceHints = { pos_card: 'Approval code or RRN (optional)', bank_transfer: 'Sender or transfer reference (optional)', valu_wallet: 'Valu reference (optional)' };

    function setSplitMode(on) {
        splitMode = on;
        document.getElementById('split-payment-section').classList.toggle('hidden', !on);
        document.getElementById('single-payment-section').classList.toggle('hidden', on);

        if (on) {
            splitLines = [
                { method: 'cash', amount: '', reference: '' },
                { method: 'bank_transfer', amount: '', reference: '' }
            ];
            activeSplitLine = 0;
            renderSplitLines();
            focusSplitAmount(0);
        } else {
            selectPaymentMethod('cash');
            setCashTender('EXACT');
            focusPaymentField();
        }
    }

    function renderSplitLines() {
        const field = 'rounded-lg border border-slate-200 bg-white text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10';
        document.getElementById('split-lines').innerHTML = splitLines.map((line, i) => `
            <div data-split-line="${i}" class="rounded-xl border p-2.5 transition ${i === activeSplitLine ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200'}">
                <div class="flex items-center gap-2">
                    <select aria-label="Payment method ${i + 1}" onchange="updateSplitLine(${i}, 'method', this.value)" onfocus="setActiveSplitLine(${i})" class="${field} h-10 w-[6.75rem] flex-shrink-0 px-2 text-sm font-medium">
                        ${Object.entries(splitMethodLabels).map(([value, label]) => `<option value="${value}" ${line.method === value ? 'selected' : ''}>${label}</option>`).join('')}
                    </select>
                    <div class="relative min-w-0 flex-1">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-400">₦</span>
                        <input type="number" min="0" step="50" inputmode="decimal" value="${line.amount}" placeholder="0" aria-label="Amount ${i + 1}" data-split-amount="${i}"
                            oninput="updateSplitLine(${i}, 'amount', this.value)" onfocus="setActiveSplitLine(${i})" class="pos-qty ${field} h-10 w-full pl-7 pr-2 text-sm font-semibold tabular-nums">
                    </div>
                    <button type="button" onclick="fillSplitRest(${i})" title="Put whatever is left to pay on this line" class="h-10 flex-shrink-0 rounded-lg bg-slate-100 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200">Rest</button>
                    ${splitLines.length > 2 ? `<button type="button" onclick="removeSplitLine(${i})" aria-label="Remove this payment" class="flex h-10 w-8 flex-shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"><i class="fas fa-times text-xs"></i></button>` : ''}
                </div>
                ${line.method !== 'cash' ? `<input type="text" maxlength="100" value="${escapeHtml(line.reference)}" placeholder="${splitReferenceHints[line.method]}" aria-label="Reference ${i + 1}"
                    oninput="updateSplitLine(${i}, 'reference', this.value)" onfocus="setActiveSplitLine(${i})" class="${field} mt-2 h-9 w-full px-3 text-xs">` : ''}
            </div>`).join('');

        document.getElementById('split-add-btn').classList.toggle('hidden', splitLines.length >= 4);
        refreshSplitTotals();
    }

    function setActiveSplitLine(i) {
        activeSplitLine = i;
        document.querySelectorAll('[data-split-line]').forEach(el => {
            const active = Number(el.dataset.splitLine) === i;
            el.classList.toggle('border-emerald-300', active);
            el.classList.toggle('bg-emerald-50/40', active);
            el.classList.toggle('border-slate-200', !active);
        });
    }

    function focusSplitAmount(i) {
        setTimeout(() => {
            const input = document.querySelector(`[data-split-amount="${i}"]`);
            if (!input) return;
            input.focus();
            input.select();
        }, 0);
    }

    function updateSplitLine(i, field, value) {
        splitLines[i][field] = value;
        if (field === 'method') {
            renderSplitLines();
            focusSplitAmount(i);
        } else {
            refreshSplitTotals();
        }
    }

    function addSplitLine() {
        if (splitLines.length >= 4) return;
        const used = splitLines.map(line => line.method);
        const method = Object.keys(splitMethodLabels).find(m => !used.includes(m)) || 'pos_card';
        splitLines.push({ method, amount: '', reference: '' });
        activeSplitLine = splitLines.length - 1;
        renderSplitLines();
        fillSplitRest(activeSplitLine);
    }

    function removeSplitLine(i) {
        splitLines.splice(i, 1);
        activeSplitLine = Math.min(activeSplitLine, splitLines.length - 1);
        renderSplitLines();
    }

    // Puts whatever is still owed on this line.
    function fillSplitRest(i) {
        const others = splitLines.reduce((sum, line, j) => sum + (j === i ? 0 : (parseFloat(line.amount) || 0)), 0);
        const rest = Math.max(0, Math.round((amountDue() - others) * 100) / 100);
        splitLines[i].amount = rest > 0 ? String(rest) : '';
        activeSplitLine = i;
        renderSplitLines();
        focusSplitAmount(i);
    }

    function refreshSplitTotals() {
        const due = amountDue();
        const paid = Math.round(splitLines.reduce((sum, line) => sum + (parseFloat(line.amount) || 0), 0) * 100) / 100;
        const balance = Math.round((due - paid) * 100) / 100;
        const box = document.getElementById('split-balance-box');
        const label = document.getElementById('split-balance-label');
        const value = document.getElementById('split-balance');

        document.getElementById('split-paid').textContent = `₦${formatMoney(paid)}`;

        let tone, text, amount;
        if (balance > 0) {
            [tone, text, amount] = ['rose', 'Still to pay', balance];
        } else if (balance < 0) {
            [tone, text, amount] = ['emerald', 'Change to give (from cash)', -balance];
        } else {
            [tone, text, amount] = ['emerald', 'Fully paid', 0];
        }
        box.className = `rounded-xl px-3.5 py-2.5 ring-1 ring-inset bg-${tone}-50 ring-${tone}-600/15`;
        label.className = `text-xs text-${tone}-700`;
        value.className = `font-bold tabular-nums text-${tone}-700`;
        label.textContent = text;
        value.textContent = `₦${formatMoney(amount)}`;
    }

    function splitPaymentsPayload() {
        return splitLines
            .filter(line => (parseFloat(line.amount) || 0) > 0)
            .map(line => ({ method: line.method, amount: Math.round(parseFloat(line.amount) * 100) / 100, reference: line.method === 'cash' ? '' : line.reference.trim() }));
    }

    function closeCheckoutModal() {
        document.getElementById('checkout-modal').classList.add('hidden');
    }

    function selectPaymentMethod(method) {
        selectedPayment = method;
        document.querySelectorAll('.pay-method-btn').forEach(btn => {
            const base = 'pay-method-btn relative flex flex-col items-center justify-center gap-1.5 rounded-xl border px-1 py-3 text-xs font-semibold transition ';
            if (btn.dataset.method === method) {
                btn.className = base + 'active border-emerald-600 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-600/20';
            } else {
                btn.className = base + 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50';
            }
        });

        document.getElementById('cash-payment-section').classList.toggle('hidden', method !== 'cash');
        document.getElementById('card-payment-section').classList.toggle('hidden', method !== 'pos_card');
        document.getElementById('wallet-payment-section').classList.toggle('hidden', method !== 'valu_wallet');
        document.getElementById('transfer-payment-section').classList.toggle('hidden', method !== 'bank_transfer');
    }

    function setCashTender(val) {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const netTotal = Math.max(0, subtotal - currentDiscount);
        const input = document.getElementById('cash-tendered-input');
        if (val === 'EXACT') {
            input.value = netTotal;
        } else {
            input.value = val;
        }
        calculateCashChange();
    }

    function calculateCashChange() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const netTotal = Math.max(0, subtotal - currentDiscount);
        const tendered = parseFloat(document.getElementById('cash-tendered-input').value) || 0;
        const change = Math.max(0, tendered - netTotal);
        document.getElementById('cash-change-display').textContent = `₦${formatMoney(change)}`;
    }

    // --- SUBMIT POS SALE ---
    async function submitPOSSale() {
        const submitBtn = document.getElementById('submit-sale-btn');
        // Already sending (a double click, or F10 and Enter together): one sale only.
        if (submitBtn.disabled || cart.length === 0) return;
        const submitLabel = submitBtn.innerHTML;
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const netTotal = Math.max(0, subtotal - currentDiscount);
        const tendered = parseFloat(document.getElementById('cash-tendered-input').value) || netTotal;
        const splitPayments = splitMode ? splitPaymentsPayload() : null;

        if (splitMode) {
            if (splitPayments.length === 0) {
                showPosAlert('Enter the amounts', 'Type how much is being paid each way, or tap Rest to put what is left on a line.', 'warning');
                return;
            }
            const paid = splitPayments.reduce((sum, line) => sum + line.amount, 0);
            if (paid + 0.005 < netTotal) {
                showPosAlert('Not fully paid', `₦${formatMoney(Math.round((netTotal - paid) * 100) / 100)} is still to be paid.`, 'warning');
                return;
            }
        } else if (selectedPayment === 'cash' && tendered < netTotal) {
            showPosAlert('Insufficient Cash', `Amount tendered (₦${formatMoney(tendered)}) is less than total payable (₦${formatMoney(netTotal)}).`, 'warning');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Processing...`;

        const custName = document.getElementById('pos-customer-name')?.value.trim() || 'Walk-in Customer';
        const custPhone = document.getElementById('pos-customer-phone')?.value.trim() || '';

        const payload = {
            _token: posCsrfToken,
            items: cart.map(i => ({ id: i.id, quantity: i.quantity, batch_number: i.batch_number })),
            customer_id: selectedCustomer ? selectedCustomer.id : null,
            customer_name: custName,
            customer_phone: custPhone,
            payment_method: splitMode ? null : selectedPayment,
            payment_reference: splitMode ? '' : (selectedPayment === 'pos_card'
                ? document.getElementById('pos-card-ref').value.trim()
                : (selectedPayment === 'bank_transfer' ? document.getElementById('pos-transfer-ref').value.trim() : '')),
            discount_amount: currentDiscount,
            discount_reason: discountReason,
            manager_email: discountApproval ? discountApproval.email : null,
            manager_password: discountApproval ? discountApproval.password : null,
            amount_tendered: splitMode ? null : (selectedPayment === 'cash' ? tendered : netTotal)
        };
        if (splitMode) payload.payments = splitPayments;

        try {
            const response = await fetch(posSaleRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': posCsrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to process sale.');
            }

            cart.forEach(cartItem => {
                const catItem = catalog.find(c => c.id === cartItem.id);
                if (catItem) {
                    catItem.stock = Math.max(0, catItem.stock - cartItem.quantity);
                }
            });

            closeCheckoutModal();
            closeMobileCartDrawer();
            populateAndOpenReceipt(data.receipt);

            resetSale();
            renderCart();
            renderProductGrid();

        } catch (error) {
            showPosAlert('Checkout Error', error.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = submitLabel;
        }
    }

    // --- RECEIPT ---
    // Drawn by CuraReceipt (partials/receipt-script), so the preview, the
    // printout, the saved file and WhatsApp all show the same receipt.
    let currentReceipt = null;
    let currentReceiptOptions = {};

    // options.copy: a reprint of an earlier sale, marked COPY on the paper.
    function populateAndOpenReceipt(rcpt, options = {}) {
        const isCopy = !!options.copy;
        currentReceipt = rcpt;
        currentReceiptOptions = isCopy ? { copy: true, reprintedAt: localTimestamp() } : {};

        document.getElementById('rcpt-modal-title').textContent = isCopy ? 'Receipt copy' : 'Sale complete';
        document.getElementById('rcpt-modal-subtitle').textContent = isCopy
            ? `Original sale ${CuraReceipt.when(rcpt.created_at)}`
            : `Receipt ${rcpt.receipt_no} · stock has been updated`;
        const icon = document.getElementById('rcpt-modal-icon');
        icon.className = `flex h-9 w-9 items-center justify-center rounded-full ${isCopy ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-600'}`;
        icon.innerHTML = `<i class="fas ${isCopy ? 'fa-copy' : 'fa-check'}"></i>`;

        document.getElementById('thermal-receipt-modal').classList.remove('hidden');
        CuraReceipt.preview(document.getElementById('receipt-preview'), rcpt, currentReceiptOptions);
    }

    // Now, as "YYYY-MM-DD HH:MM:SS" in the till's own clock.
    function localTimestamp() {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:00`;
    }

    function printReceipt() {
        if (currentReceipt) CuraReceipt.print(currentReceipt, currentReceiptOptions);
    }

    function downloadReceipt() {
        if (currentReceipt) CuraReceipt.download(currentReceipt, currentReceiptOptions);
    }

    function shareReceiptToWhatsApp() {
        if (!currentReceipt) return;
        const phone = (currentReceipt.customer.phone || '').replace(/\D/g, '');
        const recipient = phone ? (phone.startsWith('0') ? `234${phone.slice(1)}` : phone) : '';
        window.open(`https://wa.me/${recipient}?text=${encodeURIComponent(CuraReceipt.text(currentReceipt, currentReceiptOptions))}`, '_blank', 'noopener');
    }

    function closeReceiptModal() {
        document.getElementById('thermal-receipt-modal').classList.add('hidden');
    }

    function startNewSale() {
        closeReceiptModal();
        focusSearchInput();
    }

    // --- REUSABLE UI ALERT & CONFIRM SYSTEM (NO BROWSER ALERTS) ---
    function showPosAlert(title, message, type = 'warning') {
        const modal = document.getElementById('pos-alert-modal');
        const iconContainer = document.getElementById('pos-alert-icon-container');
        const titleEl = document.getElementById('pos-alert-title');
        const msgEl = document.getElementById('pos-alert-message');

        titleEl.textContent = title;
        msgEl.textContent = message;

        if (type === 'error') {
            iconContainer.className = 'w-12 h-12 rounded-full mx-auto flex items-center justify-center text-xl mb-4 bg-rose-50 text-rose-600 border border-rose-200';
            iconContainer.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        } else if (type === 'success') {
            iconContainer.className = 'w-12 h-12 rounded-full mx-auto flex items-center justify-center text-xl mb-4 bg-emerald-50 text-emerald-600 border border-emerald-200';
            iconContainer.innerHTML = '<i class="fas fa-check-circle"></i>';
        } else if (type === 'info') {
            iconContainer.className = 'w-12 h-12 rounded-full mx-auto flex items-center justify-center text-xl mb-4 bg-blue-50 text-blue-600 border border-blue-200';
            iconContainer.innerHTML = '<i class="fas fa-info-circle"></i>';
        } else {
            iconContainer.className = 'w-12 h-12 rounded-full mx-auto flex items-center justify-center text-xl mb-4 bg-amber-50 text-amber-600 border border-amber-200';
            iconContainer.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
        }

        modal.classList.remove('hidden');
    }

    function closePosAlert() {
        document.getElementById('pos-alert-modal').classList.add('hidden');
    }

    function showPosConfirm({ title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'danger', onConfirm }) {
        const modal = document.getElementById('pos-confirm-modal');
        document.getElementById('pos-confirm-title').textContent = title;
        document.getElementById('pos-confirm-message').textContent = message;

        const actionBtn = document.getElementById('pos-confirm-action-btn');
        actionBtn.textContent = confirmText;
        actionBtn.className = 'h-11 rounded-xl text-sm font-semibold text-white transition '
            + (type === 'danger' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700');

        document.getElementById('pos-confirm-cancel-btn').textContent = cancelText;

        confirmCallback = onConfirm;
        modal.classList.remove('hidden');
    }

    function closePosConfirm(agreed) {
        document.getElementById('pos-confirm-modal').classList.add('hidden');
        if (agreed && typeof confirmCallback === 'function') {
            confirmCallback();
        }
        confirmCallback = null;
    }

    function showPosToast(message, type = 'success') {
        const toast = document.getElementById('pos-toast');
        const msgEl = document.getElementById('pos-toast-msg');
        const iconEl = document.getElementById('pos-toast-icon');

        if (!toast || !msgEl) return;

        msgEl.textContent = message;

        if (type === 'error') {
            iconEl.className = 'text-rose-400 text-base';
            iconEl.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
        } else if (type === 'info') {
            iconEl.className = 'text-blue-400 text-base';
            iconEl.innerHTML = '<i class="fas fa-info-circle"></i>';
        } else {
            iconEl.className = 'text-emerald-400 text-base';
            iconEl.innerHTML = '<i class="fas fa-check-circle"></i>';
        }

        toast.classList.remove('hidden');
        toast.classList.add('pos-toast-animate');

        if (toastTimeout) clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => {
            toast.classList.add('hidden');
            toast.classList.remove('pos-toast-animate');
        }, 2800);
    }

    // --- KEYBOARD SHORTCUTS ---
    // Every step of a sale has a key, so a cashier can sell without the
    // mouse. The keys are labelled on the buttons (on keyboard devices) and
    // listed in full with F1. F5, F11 and F12 are left to the browser.
    const hasKeyboard = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    const paymentKeys = { '1': 'cash', '2': 'pos_card', '3': 'bank_transfer', '4': 'valu_wallet' };

    function isShown(id) {
        const el = document.getElementById(id);
        return !!el && !el.classList.contains('hidden');
    }

    function isTyping(target) {
        return !!target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));
    }

    // Pop-ups that need an answer first; while one is open, shortcuts wait.
    function blockingPopup() {
        return ['pos-alert-modal', 'pos-confirm-modal', 'pos-resume-choice-modal'].find(isShown) || null;
    }

    // The other pop-ups, topmost first.
    function openPopup() {
        return ['pos-shortcuts-modal', 'pos-discount-modal', 'saved-carts-modal', 'recent-sales-modal', 'product-detail-modal', 'checkout-modal', 'thermal-receipt-modal'].find(isShown) || null;
    }

    function openShortcutsModal() {
        document.getElementById('pos-shortcuts-modal').classList.remove('hidden');
    }

    function closeShortcutsModal() {
        document.getElementById('pos-shortcuts-modal').classList.add('hidden');
    }

    function closeAllPopups() {
        closeShortcutsModal();
        closeDiscountModal();
        closeSavedCartsModal();
        closeRecentSalesModal();
        closeProductDetailModal();
        closeCheckoutModal();
        closeReceiptModal();
        closeMobileCartDrawer();
    }

    // The customer box is in the sale panel, a slide-in sheet below wide screens.
    function focusCustomer() {
        if (window.innerWidth < 1280) openMobileCartDrawer();
        const input = document.getElementById('pos-customer-name');
        input.focus();
        input.select();
    }

    function choosePayment(method) {
        // In a split payment, the key sets the method of the line being worked on.
        if (splitMode) {
            splitLines[activeSplitLine].method = method;
            renderSplitLines();
            focusSplitAmount(activeSplitLine);
            return;
        }
        selectPaymentMethod(method);
        focusPaymentField();
    }

    // Puts the cursor where the chosen payment needs typing. Skipped on
    // touch screens, where it would pop the on-screen keyboard over the total.
    function focusPaymentField() {
        if (!hasKeyboard) return;
        const fieldId = { cash: 'cash-tendered-input', pos_card: 'pos-card-ref', bank_transfer: 'pos-transfer-ref' }[selectedPayment] || 'submit-sale-btn';
        const field = document.getElementById(fieldId);
        setTimeout(() => {
            field.focus();
            if (field.select) field.select();
        }, 0);
    }

    // Esc closes only the pop-up on top, so a second press goes one step further back.
    function closeTopmost() {
        if (isShown('pos-alert-modal')) return closePosAlert();
        if (isShown('pos-confirm-modal')) return closePosConfirm(false);
        if (isShown('pos-resume-choice-modal')) return closeResumeChoiceModal();
        if (isShown('pos-shortcuts-modal')) return closeShortcutsModal();
        if (isShown('pos-discount-modal')) return closeDiscountModal();
        if (isShown('saved-carts-modal')) return closeSavedCartsModal();
        if (isShown('recent-sales-modal')) return closeRecentSalesModal();
        if (isShown('product-detail-modal')) return closeProductDetailModal();
        if (isShown('checkout-modal')) return closeCheckoutModal();
        if (isShown('thermal-receipt-modal')) return startNewSale();
        if (window.innerWidth < 1280 && isShown('mobile-cart-drawer')) return closeMobileCartDrawer();

        // Nothing open: Esc clears the search and goes back to scanning.
        const search = document.getElementById('pos-search-input');
        if (search.value) {
            search.value = '';
            searchQuery = '';
            renderProductGrid();
        }
        focusSearchInput();
    }

    function setupKeyboardShortcuts() {
        window.addEventListener('keydown', (e) => {
            // Ctrl/Cmd combinations stay with the browser (copy, paste, print, find).
            if (e.ctrlKey || e.metaKey) return;

            const key = e.key;
            const typing = isTyping(e.target);
            const onButton = e.target && ['BUTTON', 'A'].includes(e.target.tagName);

            if (key === 'Escape') {
                e.preventDefault();
                closeTopmost();
                return;
            }

            const isOurFKey = ['F1', 'F2', 'F3', 'F4', 'F7', 'F8', 'F9', 'F10'].includes(key);
            // Stop the browser's own use of these keys (F1 help, F3 find, F7 caret mode, F10 menu).
            if (isOurFKey) e.preventDefault();
            // A key held down acts once, not over and over.
            if (isOurFKey && e.repeat) return;

            const blocking = blockingPopup();
            if (blocking) {
                // Enter answers a message; a yes/no question needs a deliberate click or Tab.
                if (key === 'Enter' && blocking === 'pos-alert-modal') {
                    e.preventDefault();
                    closePosAlert();
                }
                return;
            }

            const popup = openPopup();

            switch (key) {
                case 'F1':
                    isShown('pos-shortcuts-modal') ? closeShortcutsModal() : openShortcutsModal();
                    return;
                case 'F2':
                    closeAllPopups();
                    focusSearchInput();
                    return;
                case 'F3':
                    if (!popup) focusCustomer();
                    return;
                case 'F4':
                    if (popup === 'pos-discount-modal') closeDiscountModal();
                    else if (!popup) openDiscountModal();
                    return;
                case 'F7':
                    if (!popup) saveCartForLater();
                    return;
                case 'F8':
                    if (popup === 'saved-carts-modal') closeSavedCartsModal();
                    else if (!popup) openSavedCartsModal();
                    return;
                case 'F9':
                    if (popup) return;
                    if (cart.length === 0) showPosToast('Add a product first, then press F9 to take payment', 'info');
                    else openCheckoutModal();
                    return;
                case 'F10':
                    if (popup === 'checkout-modal') submitPOSSale();
                    else if (!popup && cart.length) showPosToast('Press F9 to take payment first', 'info');
                    return;
            }

            // Keys that only mean something inside one pop-up.
            if (popup === 'checkout-modal') {
                if (paymentKeys[key] && (e.altKey || !typing)) {
                    e.preventDefault();
                    choosePayment(paymentKeys[key]);
                } else if (key === 'Enter' && !onButton) {
                    e.preventDefault();
                    submitPOSSale();
                }
                return;
            }

            if (popup === 'pos-discount-modal') {
                if (key === 'Enter' && !onButton) {
                    e.preventDefault();
                    applyDiscountModal();
                }
                return;
            }

            if (popup === 'product-detail-modal') {
                if (typing) return;
                if (key === 'Enter' && !onButton) {
                    e.preventDefault();
                    addDetailModalToCart();
                } else if (key === '+' || key === '=') {
                    adjustDetailModalQty(1);
                } else if (key === '-') {
                    adjustDetailModalQty(-1);
                }
                return;
            }

            if (popup === 'thermal-receipt-modal') {
                if (typing) return;
                if (key === 'p' || key === 'P') {
                    e.preventDefault();
                    printReceipt();
                } else if (key === 'Enter' && !onButton) {
                    e.preventDefault();
                    startNewSale();
                }
                return;
            }

            if (popup) return;

            // The selling screen itself.
            const drawerCovering = window.innerWidth < 1280 && isShown('mobile-cart-drawer');
            if (typing || drawerCovering) return;

            if (key === 'Delete') {
                e.preventDefault();
                promptClearCart();
                return;
            }

            // A scan or typed letter anywhere goes into the search box, so the
            // cashier never has to click it first. The key lands in the box
            // because focus moves before the browser types the character.
            if (key.length === 1 && !e.altKey && !(key === ' ' && onButton)) {
                const search = document.getElementById('pos-search-input');
                search.focus();
            }
        });
    }

    function formatMoney(amount) {
        return Number(amount).toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>
@endpush
