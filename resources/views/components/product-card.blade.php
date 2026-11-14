@props(['product', 'heading' => 'h3'])
{{-- The one product card used everywhere products are listed (search, categories, featured, related).
     Expects the product loaded with Product::forCard(). The whole card is one link. --}}
@php
    $thumbnail = $product->thumbnail;
    $price = currency()->format($product->priceIn(currency()->code()));
@endphp
<article {{ $attributes->merge(['class' => 'group relative flex h-full flex-col overflow-hidden rounded-card border border-neutral-200 bg-white shadow-card transition-shadow hover:shadow-overlay has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-primary-600']) }}>
    <div class="aspect-square overflow-hidden border-b border-neutral-200 bg-neutral-50">
        @if ($thumbnail)
            {{-- Uploaded images are served as resized WebP; the browser picks a size from srcset. --}}
            <img src="{{ $thumbnail->sized(400) }}" alt="{{ $thumbnail->alt }}" width="400" height="400" loading="lazy" decoding="async"
                 @if ($srcset = $thumbnail->srcset()) srcset="{{ $srcset }}" sizes="(min-width: 1024px) 300px, 50vw" @endif
                 class="h-full w-full object-contain transition-transform duration-300 group-hover:scale-105">
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2 p-3 sm:p-4">
        <{{ $heading }} class="text-base font-semibold leading-snug text-neutral-900 group-hover:text-primary-700">
            {{-- The ::after overlay stretches this link across the whole card. --}}
            <a href="{{ route('products.show', $product->slug) }}" class="outline-none after:absolute after:inset-0">{{ $product->name }}</a>
        </{{ $heading }}>

        <p class="line-clamp-2 text-sm text-neutral-600">{{ $product->short_description }}</p>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-2 pt-2">
            <x-stock-badge :status="$product->stock_status" />
            <p class="font-mono text-base font-semibold text-neutral-900">
                {{ $price ?? __('site.product.price_on_request') }}
            </p>
        </div>
    </div>
</article>
