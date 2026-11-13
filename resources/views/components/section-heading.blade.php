@props(['title', 'intro' => null, 'id' => null, 'level' => 'h2'])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-x-8 gap-y-4']) }}>
    <div class="max-w-2xl">
        <{{ $level }} @if ($id) id="{{ $id }}" @endif class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">{{ $title }}</{{ $level }}>
        @if ($intro)
            <p class="mt-2 text-lg text-neutral-600">{{ $intro }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="shrink-0">{{ $slot }}</div>
    @endif
</div>
