@props(['title', 'icon' => 'inbox'])
{{-- Shown when a list has nothing in it. Put a helpful sentence in the slot and, optionally, an action:
     <x-slot:action><x-button href="…">Do something</x-button></x-slot:action> --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-card border border-dashed border-neutral-300 bg-neutral-50 px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-700">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 text-lg font-semibold text-neutral-900">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <p class="mt-1 max-w-md text-base text-neutral-600">{{ $slot }}</p>
    @endif
    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
