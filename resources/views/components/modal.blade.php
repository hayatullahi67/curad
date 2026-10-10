@props([
    'id',
    'title' => null,
    'subtitle' => null,
    'maxWidth' => 'lg',
    'zIndex' => 100,
    'bodyClass' => 'px-6 sm:px-8 py-6',
])

@php
$maxWidthClass = [
    'xs' => 'max-w-xs',
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    '3xl' => 'max-w-3xl',
    '4xl' => 'max-w-4xl',
][$maxWidth] ?? 'max-w-lg';
@endphp

{{--
    Shared modal shell. Use it for any dialog/confirmation/form popup instead of a
    one-off <div class="fixed inset-0 ..."> block, so every modal in the project gets
    the same responsive behaviour for free:

    - Centered on screen, with a comfortable margin (p-4) on small devices.
    - Card height is capped to the viewport (max-h-[90dvh]) and the body scrolls
      internally, so long forms never get cut off or push buttons off-screen.
    - Header/footer stay pinned (shrink-0) while only the middle content scrolls.
    - Closes on backdrop click, on any [data-cura-modal-close] element, and on Escape.
    - Locks background scroll while open.

    Usage:
        <x-modal id="my-modal" title="Edit item" subtitle="Optional context line">
            ... body markup / form fields ...
            <x-slot:footer>
                <button type="submit">Save</button>
            </x-slot:footer>
        </x-modal>

    Open/close from anywhere with:
        CuraModal.open('my-modal');
        CuraModal.close('my-modal');
    or declaratively with: <button data-cura-modal-open="my-modal">
--}}
<div
    id="{{ $id }}"
    data-cura-modal
    class="hidden fixed inset-0 z-[{{ $zIndex }}] flex items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4 opacity-0 pointer-events-none transition-opacity duration-300"
    aria-hidden="true"
    role="dialog"
    aria-modal="true"
    @if($title) aria-label="{{ $title }}" @endif
>
    <div
        id="{{ $id }}-card"
        data-cura-modal-card
        class="w-full {{ $maxWidthClass }} max-h-[90dvh] flex flex-col bg-white rounded-[2rem] shadow-2xl ring-1 ring-black/5 scale-95 transition-all duration-300 overflow-hidden {{ $attributes->get('class') }}"
    >
        @if($title)
            <div class="px-6 sm:px-8 py-5 sm:py-6 border-b border-gray-50 flex justify-between items-start gap-4 shrink-0">
                <div class="min-w-0">
                    <h3 class="text-lg sm:text-xl font-black text-gray-900 tracking-tight truncate">{{ $title }}</h3>
                    @if($subtitle)
                        <p class="text-gray-400 font-bold uppercase text-[9px] tracking-widest mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                <button type="button" data-cura-modal-close class="w-9 h-9 shrink-0 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto overscroll-contain {{ $bodyClass }}">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="px-6 sm:px-8 py-5 border-t border-gray-50 shrink-0 bg-white">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>

@once
    <script>
        window.CuraModal = window.CuraModal || (function () {
            function card(id) {
                return document.getElementById(id + '-card');
            }

            function anyOpen() {
                return document.querySelector('[data-cura-modal]:not(.hidden)') !== null;
            }

            function open(id) {
                const modal = document.getElementById(id);
                const cardEl = card(id);
                if (!modal) return;

                modal.classList.remove('hidden');
                requestAnimationFrame(() => {
                    modal.classList.remove('opacity-0', 'pointer-events-none');
                    modal.setAttribute('aria-hidden', 'false');
                    if (cardEl) cardEl.classList.remove('scale-95');
                });
                document.body.style.overflow = 'hidden';
            }

            function close(id) {
                const modal = document.getElementById(id);
                const cardEl = card(id);
                if (!modal) return;

                modal.classList.add('opacity-0', 'pointer-events-none');
                modal.setAttribute('aria-hidden', 'true');
                if (cardEl) cardEl.classList.add('scale-95');

                setTimeout(() => {
                    modal.classList.add('hidden');
                    if (!anyOpen()) document.body.style.overflow = '';
                }, 300);
            }

            function closeAll() {
                document.querySelectorAll('[data-cura-modal]:not(.hidden)').forEach((modal) => close(modal.id));
            }

            document.addEventListener('click', function (event) {
                const opener = event.target.closest('[data-cura-modal-open]');
                if (opener) {
                    open(opener.getAttribute('data-cura-modal-open'));
                    return;
                }

                const closer = event.target.closest('[data-cura-modal-close]');
                if (closer) {
                    const modal = closer.closest('[data-cura-modal]');
                    if (modal) close(modal.id);
                    return;
                }

                if (event.target.matches('[data-cura-modal]')) {
                    close(event.target.id);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') closeAll();
            });

            return { open: open, close: close, closeAll: closeAll };
        })();
    </script>
@endonce
