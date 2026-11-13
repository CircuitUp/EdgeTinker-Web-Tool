@props(['name', 'title', 'size' => 'md']) {{-- size: sm | md | lg --}}
{{--
    Open:  $dispatch('open-modal', 'name')      Close: $dispatch('close-modal', 'name'), Escape, or the backdrop.
    Focus is trapped inside while open and returns to the opener on close.
    Optional footer: <x-slot:footer>…buttons…</x-slot:footer>
--}}
@php $widths = ['sm' => 'sm:max-w-md', 'md' => 'sm:max-w-xl', 'lg' => 'sm:max-w-3xl']; @endphp
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
     x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
     role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}-title">
    <div class="absolute inset-0 bg-neutral-900/60" x-show="open" x-transition.opacity @click="open = false" aria-hidden="true"></div>

    <div x-show="open" x-transition x-trap.inert.noscroll="open"
         class="relative flex max-h-full w-full flex-col overflow-hidden rounded-panel bg-white shadow-overlay {{ $widths[$size] ?? $widths['md'] }}">
        <div class="flex items-center justify-between gap-4 border-b border-neutral-200 px-6 py-4">
            <h2 id="modal-{{ $name }}-title" class="text-xl font-semibold text-neutral-900">{{ $title }}</h2>
            <button type="button" class="-mr-2 inline-flex h-11 w-11 items-center justify-center rounded-control text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900" @click="open = false">
                <x-icon name="x" /><span class="sr-only">Close</span>
            </button>
        </div>
        <div class="overflow-y-auto px-6 py-5 text-base text-neutral-700">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-wrap justify-end gap-3 border-t border-neutral-200 bg-neutral-50 px-6 py-4">{{ $footer }}</div>
        @endisset
    </div>
</div>
