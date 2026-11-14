@props(['title', 'intro' => null, 'back' => null, 'backLabel' => 'Back'])
<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    @if ($back)
        <a href="{{ $back }}" class="mb-2 inline-flex min-h-9 items-center gap-1 text-sm font-semibold text-primary-700 underline-offset-2 hover:underline">
            <x-icon name="chevron-left" class="h-4 w-4" /> {{ $backLabel }}
        </a>
    @endif
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">{{ $title }}</h1>
            @if ($intro)
                <p class="mt-1 max-w-3xl text-base text-neutral-600">{{ $intro }}</p>
            @endif
        </div>
        @if ($slot->isNotEmpty())
            <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>
