{{-- Shared listing layout: all products, one category, and search results.
     Every filter lives in the URL, so any view can be shared or bookmarked. --}}
@php
    $isSearch = $page === 'search';
    $base = match ($page) {
        'search' => route('search'),
        'category' => route('products.category', $category->slug),
        default => route('products.index'),
    };

    // Link to this listing with some filters changed (always back to page 1).
    $url = function (array $overrides = []) use ($filters, $base, $isSearch) {
        $query = $filters->toQuery($overrides);
        if (! $isSearch) {
            unset($query['q'], $query['category']); // the category is in the path
        }

        return $base.($query ? '?'.\Illuminate\Support\Arr::query($query) : '');
    };

    // Switching category resets the filters that depend on it (tags, price).
    $categoryUrl = function (?string $slug) use ($filters, $url, $isSearch) {
        if ($isSearch) {
            return $url(['category' => $slug, 'tags' => [], 'min' => null, 'max' => null]);
        }
        $query = \Illuminate\Support\Arr::only($filters->toQuery(), ['in_stock', 'sort']);

        return ($slug ? route('products.category', $slug) : route('products.index')).($query ? '?'.\Illuminate\Support\Arr::query($query) : '');
    };

    $clearUrl = $url(['tags' => [], 'min' => null, 'max' => null, 'in_stock' => null]);

    $heading = match (true) {
        $isSearch && $filters->hasQuery() => __('site.listing.search_heading', ['query' => $filters->query]),
        $isSearch => __('site.listing.search_heading_empty'),
        $page === 'category' => $category->name,
        default => __('site.listing.all_products'),
    };
    $intro = match (true) {
        $isSearch => null,
        $page === 'category' => $category->tagline,
        default => __('site.listing.all_products_intro'),
    };

    $breadcrumbs = [['label' => __('site.nav.home'), 'url' => route('home')]];
    if ($isSearch) {
        $breadcrumbs[] = ['label' => __('site.search.submit')];
    } elseif ($page === 'category') {
        $breadcrumbs[] = ['label' => __('site.nav.products'), 'url' => route('products.index')];
        $breadcrumbs[] = ['label' => $category->name];
    } else {
        $breadcrumbs[] = ['label' => __('site.nav.products')];
    }

    $total = $products->total();
    $countText = trans_choice($isSearch && $filters->hasQuery() ? 'site.listing.results_count' : 'site.listing.count', $total, ['count' => $total]);

    $sorts = collect(__('site.listing.sorts'))->when(! $filters->hasQuery(), fn ($sorts) => $sorts->except('relevance'));
    $currentSort = $filters->effectiveSort();

    $priceChip = null;
    if ($filters->min !== null || $filters->max !== null) {
        $priceChip = match (true) {
            $filters->min !== null && $filters->max !== null => currency()->format($filters->min).' – '.currency()->format($filters->max),
            $filters->min !== null => '≥ '.currency()->format($filters->min),
            default => '≤ '.currency()->format($filters->max),
        };
    }
    $hasChips = $filters->hasNarrowingFilters() || ($isSearch && $category);

    $sectionHeading = 'text-sm font-semibold uppercase tracking-wider text-neutral-600';
    $chip = 'inline-flex min-h-9 items-center gap-1.5 rounded-full bg-primary-100 py-1 pl-3 pr-2 text-sm font-medium text-primary-800 hover:bg-primary-200';
@endphp
<x-layouts.site :title="$heading" :description="$intro">
    <x-slot:head>
        {{-- Search results and filtered views are not useful to index. --}}
        @if ($isSearch || $filters->hasNarrowingFilters() || $filters->sort)
            <meta name="robots" content="noindex, follow">
        @endif
    </x-slot:head>

    <div class="border-b border-neutral-200 bg-primary-50">
        <div class="container-page py-6 sm:py-8">
            <x-breadcrumb :items="$breadcrumbs" />
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ $heading }}</h1>
            @if ($intro)
                <p class="mt-2 max-w-3xl text-lg text-neutral-600">{{ $intro }}</p>
            @endif
        </div>
    </div>

    <noscript>
        {{-- Without JavaScript the filter drawer cannot open, so show the filters in the page. --}}
        <style>#filter-panel { position: static !important; transform: none !important; visibility: visible !important; width: auto !important; max-width: none !important; box-shadow: none !important; padding: 0 !important; }</style>
    </noscript>

    <div class="container-page grid gap-8 py-8 lg:grid-cols-[17rem_1fr] lg:py-10" x-data="filterPanel" @keydown.escape.window="open = false">

        {{-- Backdrop for the mobile filter drawer --}}
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-neutral-900/60 lg:hidden" @click="open = false" aria-hidden="true"></div>

        {{-- Filters: sidebar on desktop, slide-in drawer on mobile ---------- --}}
        <aside id="filter-panel" aria-label="{{ __('site.listing.filters') }}" x-trap.noscroll="trapped"
               :class="open ? 'max-lg:translate-x-0! max-lg:visible!' : ''"
               class="min-w-0 max-lg:invisible max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:z-50 max-lg:w-80 max-lg:max-w-[88vw] max-lg:-translate-x-full max-lg:overflow-y-auto max-lg:bg-white max-lg:p-4 max-lg:shadow-overlay max-lg:transition-[transform,visibility] max-lg:duration-200">

            <div class="mb-4 flex items-center justify-between lg:hidden">
                <h2 class="text-xl font-semibold text-neutral-900">{{ __('site.listing.filters') }}</h2>
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control text-neutral-900 hover:bg-neutral-100" @click="open = false">
                    <x-icon name="x" class="h-6 w-6" /><span class="sr-only">{{ __('site.listing.close_filters') }}</span>
                </button>
            </div>

            <form id="filters-form" method="GET" action="{{ $base }}" @change="changed($el, $event)" @submit="tidy($el)" class="flex flex-col gap-7">
                @if ($isSearch && $filters->hasQuery())
                    <input type="hidden" name="q" value="{{ $filters->query }}">
                @endif
                @if ($isSearch && $category)
                    <input type="hidden" name="category" value="{{ $category->slug }}">
                @endif

                {{-- Category --}}
                <nav aria-labelledby="filter-category">
                    <h2 id="filter-category" class="{{ $sectionHeading }}">{{ __('site.listing.category') }}</h2>
                    <ul class="mt-2 flex flex-col">
                        @php
                            $categoryLink = fn (bool $active) => 'flex min-h-10 items-center rounded-control px-3 text-base '
                                .($active ? 'bg-primary-100 font-semibold text-primary-800' : 'text-neutral-700 hover:bg-primary-50 hover:text-primary-700');
                        @endphp
                        <li>
                            <a href="{{ $categoryUrl(null) }}" class="{{ $categoryLink(! $category) }}" @if (! $category) aria-current="true" @endif>{{ __('site.listing.all_categories') }}</a>
                        </li>
                        @foreach ($categories as $item)
                            @php $active = $category && $category->slug === $item->slug; @endphp
                            <li>
                                <a href="{{ $categoryUrl($item->slug) }}" class="{{ $categoryLink($active) }}" @if ($active) aria-current="true" @endif>{{ $item->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </nav>

                {{-- Tags --}}
                @if ($results->tags->isNotEmpty())
                    <fieldset>
                        <legend class="{{ $sectionHeading }}">{{ __('site.listing.tags') }}</legend>
                        <ul class="mt-2 flex max-h-72 flex-col overflow-y-auto pr-1">
                            @foreach ($results->tags as $tag)
                                <li>
                                    <label class="flex min-h-10 cursor-pointer items-center gap-3 rounded-control px-3 text-base text-neutral-700 hover:bg-primary-50">
                                        <input type="checkbox" name="tags[]" value="{{ $tag->slug }}" @checked(in_array($tag->slug, $filters->tags, true))
                                               class="h-5 w-5 shrink-0 accent-primary-600">
                                        <span class="min-w-0 flex-1 truncate">{{ $tag->name }}</span>
                                        <span class="font-mono text-sm text-neutral-500" aria-hidden="true">{{ $tag->products_count }}</span>
                                        <span class="sr-only">({{ trans_choice('site.listing.count', $tag->products_count, ['count' => $tag->products_count]) }})</span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    </fieldset>
                @endif

                {{-- Price range (in the selected currency) --}}
                @if ($slider)
                    <fieldset x-data="priceRange(@js($slider))">
                        <legend class="{{ $sectionHeading }}">{{ __('site.listing.price', ['currency' => $filters->currency]) }}</legend>
                        <div class="range-dual mx-1 mt-1">
                            <div class="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full bg-neutral-200"></div>
                            <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-primary-600" :style="fillStyle"
                                 style="left: {{ ($slider['from'] - $slider['min']) / ($slider['max'] - $slider['min']) * 100 }}%; right: {{ 100 - ($slider['to'] - $slider['min']) / ($slider['max'] - $slider['min']) * 100 }}%"></div>
                            <input type="range" min="{{ $slider['min'] }}" max="{{ $slider['max'] }}" step="{{ $slider['step'] }}" value="{{ $slider['from'] }}"
                                   x-model.number="from" @input="clampFrom()" :name="fromActive ? 'min' : null"
                                   @if ($slider['from'] > $slider['min']) name="min" @endif
                                   aria-label="{{ __('site.listing.price_min') }}" :aria-valuetext="format(from)">
                            <input type="range" min="{{ $slider['min'] }}" max="{{ $slider['max'] }}" step="{{ $slider['step'] }}" value="{{ $slider['to'] }}"
                                   x-model.number="to" @input="clampTo()" :name="toActive ? 'max' : null"
                                   @if ($slider['to'] < $slider['max']) name="max" @endif
                                   aria-label="{{ __('site.listing.price_max') }}" :aria-valuetext="format(to)">
                        </div>
                        <p class="flex justify-between font-mono text-sm text-neutral-900" aria-hidden="true">
                            <span x-text="format(from)">{{ $slider['prefix'].number_format($slider['from']) }}</span>
                            <span x-text="format(to)">{{ $slider['prefix'].number_format($slider['to']) }}</span>
                        </p>
                    </fieldset>
                @endif

                {{-- Availability --}}
                <fieldset>
                    <legend class="{{ $sectionHeading }}">{{ __('site.listing.availability') }}</legend>
                    <label class="mt-2 flex min-h-10 cursor-pointer items-center gap-3 rounded-control px-3 text-base text-neutral-700 hover:bg-primary-50">
                        <input type="checkbox" name="in_stock" value="1" @checked($filters->inStock) class="h-5 w-5 shrink-0 accent-primary-600">
                        <span>{{ __('site.listing.in_stock_only') }}</span>
                    </label>
                </fieldset>

                <div class="flex flex-col gap-2">
                    {{-- On desktop filters apply as they change; this button is for the mobile drawer. --}}
                    <x-button type="submit" class="lg:hidden">{{ __('site.listing.show_results') }}</x-button>
                    <noscript><x-button type="submit" class="w-full">{{ __('site.listing.apply') }}</x-button></noscript>
                    @if ($filters->hasNarrowingFilters())
                        <x-button :href="$clearUrl" variant="ghost">{{ __('site.listing.clear_all') }}</x-button>
                    @endif
                </div>
            </form>
        </aside>

        {{-- Results ----------------------------------------------------------- --}}
        <section aria-labelledby="results-count" class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p id="results-count" class="text-base font-medium text-neutral-900" role="status">{{ $countText }}</p>

                <div class="flex items-center gap-2">
                    <x-button variant="secondary" class="lg:hidden" @click="open = true" aria-controls="filter-panel" ::aria-expanded="open ? 'true' : 'false'">
                        <x-icon name="filter" class="h-4 w-4" /> {{ __('site.listing.show_filters') }}
                        @if ($filters->hasNarrowingFilters())
                            <span class="rounded-full bg-primary-600 px-2 text-sm text-white">{{ count($filters->tags) + ($priceChip ? 1 : 0) + ($filters->inStock ? 1 : 0) }}</span>
                        @endif
                    </x-button>

                    <label for="sort" class="hidden text-sm text-neutral-600 sm:block">{{ __('site.listing.sort') }}</label>
                    <select id="sort" name="sort" form="filters-form" aria-label="{{ __('site.listing.sort') }}" data-default="{{ $filters->hasQuery() ? 'relevance' : 'newest' }}"
                            @change="submit(document.getElementById('filters-form'))"
                            class="min-h-11 rounded-control border border-neutral-300 bg-white px-3 py-2 text-base text-neutral-900 hover:border-neutral-400">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <noscript><x-button type="submit" form="filters-form" variant="secondary">{{ __('site.listing.apply') }}</x-button></noscript>
                </div>
            </div>

            {{-- Typo help --}}
            @if ($results->didYouMean)
                <p class="mt-4 rounded-card border border-primary-200 bg-primary-50 px-4 py-3 text-base text-neutral-900">
                    {{ __('site.listing.did_you_mean') }}
                    <a href="{{ $url(['q' => $results->didYouMean]) }}" class="font-semibold text-primary-700 underline underline-offset-2 hover:text-primary-800">{{ $results->didYouMean }}</a>?
                </p>
            @endif
            @if ($results->loose && $total > 0)
                <p class="mt-4 text-base text-neutral-600">{{ __('site.listing.loose_notice', ['query' => $filters->query]) }}</p>
            @endif

            {{-- Active filters --}}
            @if ($hasChips)
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <h2 class="sr-only">{{ __('site.listing.active_filters') }}</h2>
                    @if ($isSearch && $category)
                        <a href="{{ $categoryUrl(null) }}" class="{{ $chip }}">
                            <span class="sr-only">{{ __('site.listing.remove_filter') }}</span>{{ $category->name }}<x-icon name="x" class="h-4 w-4" />
                        </a>
                    @endif
                    @foreach ($selectedTags as $tag)
                        <a href="{{ $url(['tags' => array_values(array_diff($filters->tags, [$tag->slug]))]) }}" class="{{ $chip }}">
                            <span class="sr-only">{{ __('site.listing.remove_filter') }}</span>{{ $tag->name }}<x-icon name="x" class="h-4 w-4" />
                        </a>
                    @endforeach
                    @if ($priceChip)
                        <a href="{{ $url(['min' => null, 'max' => null]) }}" class="{{ $chip }}">
                            <span class="sr-only">{{ __('site.listing.remove_filter') }}</span>{{ __('site.listing.price_chip', ['range' => $priceChip]) }}<x-icon name="x" class="h-4 w-4" />
                        </a>
                    @endif
                    @if ($filters->inStock)
                        <a href="{{ $url(['in_stock' => null]) }}" class="{{ $chip }}">
                            <span class="sr-only">{{ __('site.listing.remove_filter') }}</span>{{ __('site.listing.in_stock_only') }}<x-icon name="x" class="h-4 w-4" />
                        </a>
                    @endif
                    @if ($filters->hasNarrowingFilters())
                        <a href="{{ $clearUrl }}" class="inline-flex min-h-9 items-center px-2 text-sm font-semibold text-primary-700 underline-offset-2 hover:underline">{{ __('site.listing.clear_all') }}</a>
                    @endif
                </div>
            @endif

            @if ($total > 0)
                <ul class="mt-6 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3">
                    @foreach ($products as $product)
                        <li><x-product-card :product="$product" heading="h2" /></li>
                    @endforeach
                </ul>

                <div class="mt-8">{{ $products->links() }}</div>
            @else
                <x-empty-state class="mt-6" icon="search" :title="__('site.listing.empty_title')">
                    @if ($filters->hasNarrowingFilters())
                        {{ __('site.listing.empty_filtered') }}
                    @elseif ($filters->hasQuery())
                        {{ __('site.listing.empty_search', ['query' => $filters->query]) }}
                    @else
                        {{ __('site.listing.empty_category') }}
                    @endif
                    <x-slot:action>
                        <div class="flex flex-wrap justify-center gap-3">
                            @if ($filters->hasNarrowingFilters())
                                <x-button :href="$clearUrl">{{ __('site.listing.clear_all') }}</x-button>
                            @endif
                            <x-button :href="route('products.index')" variant="secondary">{{ __('site.listing.browse_all') }}</x-button>
                        </div>
                    </x-slot:action>
                </x-empty-state>
            @endif
        </section>
    </div>
</x-layouts.site>
