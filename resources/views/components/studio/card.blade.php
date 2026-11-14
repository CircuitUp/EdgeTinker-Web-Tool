@props(['title' => null, 'intro' => null, 'flush' => false])
{{-- White panel used to group a section of a Studio screen. `flush` removes the body padding (for tables). --}}
<section {{ $attributes->merge(['class' => 'rounded-card border border-neutral-200 bg-white shadow-card']) }}>
    @if ($title)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-200 px-5 py-4">
            <div>
                <h2 class="text-lg font-semibold text-neutral-900">{{ $title }}</h2>
                @if ($intro)
                    <p class="text-sm text-neutral-600">{{ $intro }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $flush ? '' : 'p-5' }}">{{ $slot }}</div>
</section>
