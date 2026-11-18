{{-- Section text comes from Content Studio → Pages → Home ($content); featured products are chosen there too.
     The banner comes from Content Studio → Home banner ($banner, prepared by BannerPresenter). --}}
<x-layouts.site :title="$seo['title'] ?? null" :description="$seo['description'] ?? null" :preview="$isPreview ?? false">
    <x-slot:head>
        {{-- Fonts chosen in the banner editor, self-hosted. Preloaded so the headline does not change font after it appears. --}}
        @foreach ($banner['fonts']['preload'] as $font)
            <link rel="preload" href="{{ $font }}" as="font" type="font/woff2" crossorigin>
        @endforeach
        @foreach ($banner['fonts']['css'] as $stylesheet)
            <link rel="stylesheet" href="{{ $stylesheet }}">
        @endforeach
        @unless ($isPreview ?? false)
            {{-- Tells search engines who runs the site and that it has a search box. --}}
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'Organization', 'name' => setting('site.name'), 'url' => route('home'), 'description' => setting('site.tagline')],
                    ['@type' => 'WebSite', 'name' => setting('site.name'), 'url' => route('home'),
                        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endunless
    </x-slot:head>

    <h1 class="sr-only">{{ setting('site.name') }} – {{ setting('site.tagline') }}</h1>

    {{-- 1. Hero banner (slideshow) ------------------------------------------ --}}
    <x-banner.hero :banner="$banner" />

    {{-- 2. Category tiles ------------------------------------------------- --}}
    @if ($categories->isNotEmpty())
        <section class="py-12 sm:py-16" aria-labelledby="home-categories">
            <div class="container-page">
                <x-section-heading id="home-categories" :title="$content['categories_heading']" :intro="$content['categories_intro']" />

                <ul class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-5 lg:grid-cols-5">
                    @foreach ($categories as $category)
                        <li>
                            <a href="{{ route('products.category', $category->slug) }}"
                               class="group flex h-full flex-col overflow-hidden rounded-card border border-neutral-200 bg-white shadow-card transition-shadow hover:shadow-overlay">
                                <div class="aspect-[4/3] overflow-hidden bg-primary-50">
                                    @if ($category->image_url)
                                        <img src="{{ $category->image_url }}" alt="" width="400" height="300" loading="lazy" decoding="async"
                                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                    @endif
                                </div>
                                <div class="flex flex-1 flex-col gap-1 p-3 sm:p-4">
                                    <h3 class="text-base font-semibold leading-snug text-neutral-900 group-hover:text-primary-700">{{ $category->name }}</h3>
                                    @if ($category->tagline)
                                        <p class="hidden text-sm text-neutral-600 sm:block">{{ $category->tagline }}</p>
                                    @endif
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- 3. Featured products ---------------------------------------------- --}}
    <section class="bg-neutral-50 py-12 sm:py-16" aria-labelledby="home-featured">
        <div class="container-page">
            <x-section-heading id="home-featured" :title="$content['featured_heading']" :intro="$content['featured_intro']">
                <x-button :href="route('products.index')" variant="secondary">
                    {{ __('site.home.featured_all') }} <x-icon name="arrow-right" class="h-4 w-4" />
                </x-button>
            </x-section-heading>

            @if ($featured->isNotEmpty())
                <ul class="mt-8 grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                    @foreach ($featured as $product)
                        <li><x-product-card :product="$product" /></li>
                    @endforeach
                </ul>
            @else
                <x-empty-state class="mt-8" :title="__('site.home.featured_empty_title')">{{ __('site.home.featured_empty_text') }}</x-empty-state>
            @endif
        </div>
    </section>

    {{-- 4. Services overview ---------------------------------------------- --}}
    <section class="py-12 sm:py-16" aria-labelledby="home-services">
        <div class="container-page grid gap-10 lg:grid-cols-[1fr_2fr] lg:gap-16">
            <div>
                <h2 id="home-services" class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">{{ $content['services_heading'] }}</h2>
                <p class="mt-3 text-lg text-neutral-600">{{ $content['services_intro'] }}</p>
                <x-button :href="route('services')" class="mt-6">
                    {{ __('site.home.services_cta') }} <x-icon name="arrow-right" class="h-4 w-4" />
                </x-button>
            </div>

            <ul class="grid gap-x-8 gap-y-8 sm:grid-cols-2">
                @foreach ($services as $service)
                    <li class="flex gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-card bg-primary-100 text-primary-700">
                            <x-icon :name="$service['icon']" class="h-6 w-6" />
                        </span>
                        <div>
                            <h3 class="text-lg font-semibold text-neutral-900">{{ $service['title'] }}</h3>
                            <p class="mt-1 text-base text-neutral-600">{{ $service['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 5. Why EdgeTinker ------------------------------------------------- --}}
    <section class="bg-primary-50 py-12 sm:py-16" aria-labelledby="home-why">
        <div class="container-page">
            <x-section-heading id="home-why" :title="$content['why_heading']" />

            <ul class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($content['why'] as $reason)
                    <li class="rounded-card border border-primary-200 bg-white p-5">
                        <span class="block h-1 w-10 rounded-full bg-accent" aria-hidden="true"></span>
                        <h3 class="mt-4 text-lg font-semibold text-neutral-900">{{ $reason['title'] }}</h3>
                        <p class="mt-2 text-base text-neutral-600">{{ $reason['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 6. Contact call-to-action ----------------------------------------- --}}
    <section class="py-12 sm:py-16" aria-labelledby="home-contact">
        <div class="container-page">
            <div class="on-dark flex flex-col items-start gap-6 rounded-panel bg-primary-900 px-6 py-10 sm:px-10 lg:flex-row lg:items-center lg:justify-between lg:py-12">
                <div class="max-w-2xl">
                    <h2 id="home-contact" class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $content['contact_heading'] }}</h2>
                    <p class="mt-2 text-lg text-primary-100">{{ $content['contact_text'] }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <x-button :href="route('contact')" variant="inverse" size="lg">{{ __('site.home.contact_cta') }}</x-button>
                    <x-button :href="route('wiki.index')" variant="outline-inverse" size="lg" target="_blank" rel="noopener">
                        <x-icon name="book" /> {{ __('site.home.contact_secondary') }}
                        <span class="sr-only">{{ __('site.nav.new_tab') }}</span>
                    </x-button>
                </div>
            </div>
        </div>
    </section>

    {{-- 7. Partners -------------------------------------------------------
         Logos only, moving continuously from right to left, just above the footer.
         The strip is two identical groups side by side; sliding it left by exactly one
         group's width and starting again makes the movement seamless (see .logo-marquee in app.css).
         It pauses while the pointer is over it and with the pause button, and visitors
         who ask for reduced motion get a still, wrapped row instead. --}}
    @if (! empty($partners))
        @php
            // Repeat a short list so that one group is always wider than the screen.
            $repeat = (int) max(1, ceil(10 / count($partners)));
            $perGroup = count($partners) * $repeat;
        @endphp
        <section class="border-t border-neutral-200 py-10 sm:py-12" aria-labelledby="home-partners" x-data="{ paused: false }">
            <div class="container-page flex items-center justify-between gap-4">
                <h2 id="home-partners" class="text-xl font-semibold tracking-tight text-neutral-900 sm:text-2xl">{{ $content['partners_heading'] }}</h2>
                <button type="button" @click="paused = ! paused" :aria-pressed="paused ? 'true' : 'false'"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-neutral-300 text-neutral-600 hover:border-primary-600 hover:text-primary-700 motion-reduce:hidden">
                    <span x-show="! paused"><x-icon name="pause" class="h-4 w-4" /></span>
                    <span x-show="paused" x-cloak><x-icon name="play" class="h-4 w-4" /></span>
                    <span class="sr-only">{{ __('site.home.partners_pause') }}</span>
                </button>
            </div>

            <div class="logo-marquee mt-6" :class="paused && 'is-paused'">
                <div class="logo-marquee-track" style="--marquee-duration: {{ $perGroup * 4 }}s">
                    @foreach ([false, true] as $duplicate)
                        <ul class="logo-marquee-group" @if ($duplicate) aria-hidden="true" @endif>
                            @for ($round = 0; $round < $repeat; $round++)
                                @foreach ($partners as $partner)
                                    {{-- Each partner is announced once; the repeats exist only to fill the strip.
                                         Not lazy-loaded: logos slide in from the side, where lazy loading would show
                                         them blank for a moment. They are small, so they load at low priority instead. --}}
                                    @php $announce = ! $duplicate && $round === 0; @endphp
                                    <li @if (! $announce && ! $duplicate) aria-hidden="true" @endif>
                                        <img src="{{ $partner['src'] }}" alt="{{ $announce ? $partner['name'] : '' }}"
                                             width="{{ $partner['width'] }}" height="{{ $partner['height'] }}" fetchpriority="low" decoding="async">
                                    </li>
                                @endforeach
                            @endfor
                        </ul>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.site>
