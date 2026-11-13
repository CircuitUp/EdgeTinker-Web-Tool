@props(['tabs' => [], 'label' => 'Sections', 'id' => 'tabs'])
{{--
    Usage:
    <x-tabs id="product" :tabs="['overview' => 'Overview', 'specs' => 'Specifications']">
        <x-slot:overview>…</x-slot:overview>
        <x-slot:specs>…</x-slot:specs>
    </x-tabs>
    Tab keys must be valid variable names (letters, digits, underscores).
--}}
@php $first = array_key_first($tabs); @endphp
<div x-data="tabs({ initial: @js($first) })" {{ $attributes }}>
    <div x-ref="tablist" role="tablist" aria-label="{{ $label }}" @keydown="onKeydown($event)"
         class="flex gap-1 overflow-x-auto border-b border-neutral-200">
        @foreach ($tabs as $key => $text)
            <button type="button" role="tab" data-tab="{{ $key }}"
                    id="{{ $id }}-tab-{{ $key }}" aria-controls="{{ $id }}-panel-{{ $key }}"
                    aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                    :aria-selected="selected === @js($key) ? 'true' : 'false'"
                    :tabindex="selected === @js($key) ? 0 : -1"
                    @click="select(@js($key))"
                    :class="selected === @js($key) ? 'border-primary-600 text-primary-700' : 'border-transparent text-neutral-600 hover:border-neutral-300 hover:text-neutral-900'"
                    class="-mb-px min-h-11 whitespace-nowrap border-b-2 px-4 py-2.5 text-base font-medium transition-colors">
                {{ $text }}
            </button>
        @endforeach
    </div>

    @foreach ($tabs as $key => $text)
        <div role="tabpanel" id="{{ $id }}-panel-{{ $key }}" aria-labelledby="{{ $id }}-tab-{{ $key }}" tabindex="0"
             x-show="selected === @js($key)" @if (! $loop->first) x-cloak @endif class="py-6">
            {{ ${$key} ?? '' }}
        </div>
    @endforeach
</div>
