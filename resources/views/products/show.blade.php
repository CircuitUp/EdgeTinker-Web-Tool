@php
    $currencyCode = currency()->code();
    $price = currency()->format($product->priceIn($currencyCode));
    $features = collect($product->key_features ?? [])->filter()->values();
    $gallery = $product->images->map(fn ($image) => ['src' => $image->url, 'alt' => $image->alt])->values();
    $firstImage = $gallery->first();
    $enquireUrl = route('contact', ['subject' => __('site.product.enquiry_subject', ['name' => $product->name, 'sku' => $product->sku])]);
    $seoDescription = $product->seo_description ?: $product->short_description;
@endphp
<x-layouts.site :title="$product->seo_title ?: $product->name" :description="$seoDescription">
    <x-slot:head>
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $product->name }}">
        <meta property="og:description" content="{{ $seoDescription }}">
        <meta property="og:url" content="{{ route('products.show', $product->slug) }}">
        <meta property="og:site_name" content="{{ setting('site.name') }}">
        @if ($firstImage)
            <meta property="og:image" content="{{ $firstImage['src'] }}">
        @endif
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:head>

    <div class="border-b border-neutral-200 bg-neutral-50">
        <div class="container-page py-4">
            <x-breadcrumb :items="[
                ['label' => __('site.nav.home'), 'url' => route('home')],
                ['label' => __('site.nav.products'), 'url' => route('products.index')],
                ['label' => $product->category->name, 'url' => route('products.category', $product->category->slug)],
                ['label' => $product->name],
            ]" />
        </div>
    </div>

    <article>
        {{-- Summary: gallery + key facts ----------------------------------- --}}
        <div class="container-page grid gap-8 py-8 lg:grid-cols-2 lg:gap-12 lg:py-12">

            {{-- Image gallery --}}
            <section aria-label="{{ __('site.product.gallery') }}" x-data="gallery({ images: @js($gallery) })"
                     @keydown.escape.window="close()" class="min-w-0">
                @if ($firstImage)
                    <button type="button" @click="open()" @mouseenter="zoomIn()" @mouseleave="zoomOut()" @mousemove="track($event)"
                            class="relative block aspect-square w-full cursor-zoom-in overflow-hidden rounded-card border border-neutral-200 bg-neutral-50">
                        <img src="{{ $firstImage['src'] }}" alt="{{ $firstImage['alt'] }}" :src="current.src" :alt="current.alt"
                             width="800" height="800" fetchpriority="high" decoding="async"
                             class="h-full w-full object-contain transition-transform duration-150"
                             :style="{ transform: zoomed ? 'scale(2.2)' : 'scale(1)', transformOrigin: origin }">
                        <span class="sr-only">{{ __('site.product.gallery_open') }}</span>
                    </button>

                    @if ($gallery->count() > 1)
                        <ul class="mt-3 flex gap-2 overflow-x-auto p-1 sm:gap-3">
                            @foreach ($gallery as $index => $image)
                                <li class="shrink-0">
                                    <button type="button" @click="select({{ $index }})"
                                            aria-current="{{ $index === 0 ? 'true' : 'false' }}" :aria-current="index === {{ $index }} ? 'true' : 'false'"
                                            :class="index === {{ $index }} ? 'border-primary-600 ring-2 ring-primary-200' : 'border-neutral-200 hover:border-neutral-400'"
                                            class="block h-16 w-16 overflow-hidden rounded-control border bg-neutral-50 sm:h-20 sm:w-20">
                                        <img src="{{ $image['src'] }}" alt="" width="80" height="80" loading="lazy" decoding="async" class="h-full w-full object-contain">
                                        <span class="sr-only">{{ __('site.product.gallery_show', ['number' => $index + 1]) }}: {{ $image['alt'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Lightbox --}}
                    <div x-show="lightbox" x-cloak class="on-dark fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/90 p-4"
                         role="dialog" aria-modal="true" aria-label="{{ __('site.product.gallery_viewer') }}"
                         x-transition.opacity @click.self="close()"
                         @keydown.arrow-left.window="lightbox && previous()" @keydown.arrow-right.window="lightbox && next()">
                        <div x-trap.inert.noscroll="lightbox" class="relative flex max-h-full w-full max-w-4xl flex-col items-center gap-4">
                            <img :src="current.src" :alt="current.alt" class="max-h-[80vh] w-auto max-w-full rounded-card bg-white object-contain">
                            @php $viewerButton = 'inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/50 bg-neutral-900/60 text-white hover:bg-white/15'; @endphp
                            <div class="flex items-center gap-3">
                                @if ($gallery->count() > 1)
                                    <button type="button" class="{{ $viewerButton }}" @click="previous()">
                                        <x-icon name="chevron-left" /><span class="sr-only">{{ __('site.product.gallery_previous') }}</span>
                                    </button>
                                    <p class="min-w-16 text-center font-mono text-sm text-white" aria-live="polite">
                                        <span x-text="index + 1">1</span> / {{ $gallery->count() }}
                                    </p>
                                    <button type="button" class="{{ $viewerButton }}" @click="next()">
                                        <x-icon name="chevron-right" /><span class="sr-only">{{ __('site.product.gallery_next') }}</span>
                                    </button>
                                @endif
                                <button type="button" class="{{ $viewerButton }}" @click="close()">
                                    <x-icon name="x" /><span class="sr-only">{{ __('site.product.gallery_close') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex aspect-square w-full items-center justify-center rounded-card border border-dashed border-neutral-300 bg-neutral-50 text-base text-neutral-600">
                        {{ __('site.product.no_image') }}
                    </div>
                @endif
            </section>

            {{-- Key facts --}}
            <div class="min-w-0">
                <a href="{{ route('products.category', $product->category->slug) }}" class="text-sm font-semibold uppercase tracking-wider text-primary-700 underline-offset-4 hover:underline">
                    {{ $product->category->name }}
                </a>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ $product->name }}</h1>
                <p class="mt-2 text-base text-neutral-600">
                    {{ __('site.product.sku') }}: <span class="font-mono font-medium text-neutral-900">{{ $product->sku }}</span>
                </p>

                <p class="mt-4 text-lg text-neutral-700">{{ $product->short_description }}</p>

                <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <p class="font-mono text-3xl font-semibold text-neutral-900">{{ $price ?? __('site.product.price_on_request') }}</p>
                    <x-stock-badge :status="$product->stock_status" />
                </div>
                @if ($price)
                    <p class="mt-1 text-sm text-neutral-600">{{ __('site.product.price_note', ['currency' => $currencyCode]) }}</p>
                @endif

                <div class="mt-6">
                    <x-button :href="$enquireUrl" size="lg" class="w-full sm:w-auto">
                        <x-icon name="mail" /> {{ __('site.product.enquire') }}
                    </x-button>
                </div>

                @if ($product->specs->isNotEmpty())
                    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wider text-neutral-600">{{ __('site.product.at_a_glance') }}</h2>
                    <dl class="mt-3 divide-y divide-neutral-200 border-y border-neutral-200">
                        @foreach ($product->specs->take(4) as $spec)
                            <div class="flex flex-col gap-0.5 py-2.5 sm:flex-row sm:gap-4">
                                <dt class="shrink-0 text-base text-neutral-600 sm:w-44">{{ $spec->label }}</dt>
                                <dd class="min-w-0 break-words font-mono text-base text-neutral-900">{{ $spec->value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if ($product->tags->isNotEmpty())
                    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wider text-neutral-600">{{ __('site.product.tags') }}</h2>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($product->tags as $tag)
                            <li><x-tag-chip :href="route('search', ['tags' => $tag->slug])">{{ $tag->name }}</x-tag-chip></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        {{-- Details: Overview · Specifications · Documents & Downloads ------ --}}
        <div class="border-t border-neutral-200">
            <div class="container-page py-8 lg:py-12">
                <x-tabs id="product" :label="__('site.product.tabs_label')" :tabs="[
                    'overview' => __('site.product.overview'),
                    'specs' => __('site.product.specifications'),
                    'documents' => __('site.product.documents'),
                ]">
                    <x-slot:overview>
                        <div class="grid gap-10 lg:grid-cols-[2fr_1fr]">
                            <div>
                                <h2 class="sr-only">{{ __('site.product.overview') }}</h2>
                                @if ($description->isNotEmpty())
                                    <div class="rich-text">{{ $description }}</div>
                                @else
                                    <p class="text-base text-neutral-600">{{ __('site.product.no_description') }}</p>
                                @endif
                            </div>

                            @if ($features->isNotEmpty())
                                <div class="rounded-card bg-primary-50 p-5 lg:self-start">
                                    <h2 class="text-lg font-semibold text-neutral-900">{{ __('site.product.key_features') }}</h2>
                                    <ul class="mt-3 flex flex-col gap-2.5">
                                        @foreach ($features as $feature)
                                            <li class="flex gap-2.5 text-base text-neutral-700">
                                                <x-icon name="check" class="mt-0.5 text-primary-600" />
                                                <span>{{ $feature }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </x-slot:overview>

                    <x-slot:specs>
                        <h2 class="sr-only">{{ __('site.product.specifications') }}</h2>
                        @if ($product->specs->isNotEmpty())
                            <x-table :caption="__('site.product.specifications').': '.$product->name" striped class="max-w-3xl">
                                <x-slot:head>
                                    <tr><th scope="col">{{ __('site.product.parameter') }}</th><th scope="col">{{ __('site.product.value') }}</th></tr>
                                </x-slot:head>
                                @foreach ($product->specs as $spec)
                                    <tr><th scope="row">{{ $spec->label }}</th><td class="font-mono">{{ $spec->value }}</td></tr>
                                @endforeach
                                <tr><th scope="row">{{ __('site.product.sku') }}</th><td class="font-mono">{{ $product->sku }}</td></tr>
                            </x-table>
                        @else
                            <x-empty-state :title="__('site.product.no_specs_title')">{{ __('site.product.no_specs_text') }}</x-empty-state>
                        @endif
                    </x-slot:specs>

                    <x-slot:documents>
                        <h2 class="sr-only">{{ __('site.product.documents') }}</h2>
                        {{-- Documents are attached through the Content Studio media library (Phase 3). --}}
                        <x-empty-state icon="book" :title="__('site.product.no_documents_title')" class="max-w-3xl">
                            {{ __('site.product.no_documents_text') }}
                        </x-empty-state>
                    </x-slot:documents>
                </x-tabs>

                {{-- Wiki link --}}
                <div class="mt-4 flex flex-col items-start gap-4 rounded-card border border-primary-200 bg-primary-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-white text-primary-700">
                            <x-icon name="book" class="h-6 w-6" />
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-neutral-900">{{ __('site.product.wiki_heading') }}</h2>
                            <p class="text-base text-neutral-600">{{ __('site.product.wiki_text') }}</p>
                        </div>
                    </div>
                    <x-button :href="route('wiki.index')" variant="secondary" target="_blank" rel="noopener" class="shrink-0">
                        {{ __('site.product.wiki_cta') }} <x-icon name="external" class="h-4 w-4" />
                        <span class="sr-only">{{ __('site.nav.new_tab') }}</span>
                    </x-button>
                </div>
            </div>
        </div>
    </article>

    {{-- Related products ---------------------------------------------------- --}}
    @if ($related->isNotEmpty())
        <section class="bg-neutral-50 py-12 sm:py-16" aria-labelledby="related-products">
            <div class="container-page">
                <x-section-heading id="related-products" :title="__('site.product.related')" />
                <ul class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($related as $relatedProduct)
                        <li><x-product-card :product="$relatedProduct" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
</x-layouts.site>
