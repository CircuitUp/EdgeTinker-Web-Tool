{{-- Section text comes from Content Studio → Pages → Home ($content); slides and featured products are chosen there too. --}}
<x-layouts.site :title="$seo['title'] ?? null" :description="$seo['description'] ?? null" :preview="$isPreview ?? false">
    @unless ($isPreview ?? false)
        <x-slot:head>
            {{-- Tells search engines who runs the site and that it has a search box. --}}
            <script type="application/ld+json">{!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    ['@type' => 'Organization', 'name' => setting('site.name'), 'url' => route('home'), 'description' => setting('site.tagline')],
                    ['@type' => 'WebSite', 'name' => setting('site.name'), 'url' => route('home'),
                        'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search').'?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        </x-slot:head>
    @endunless

    <h1 class="sr-only">{{ setting('site.name') }} – {{ setting('site.tagline') }}</h1>

    {{-- 1. Hero slideshow ------------------------------------------------- --}}
    @if ($slides->isNotEmpty())
        <section x-data="slideshow({ count: {{ $slides->count() }}, interval: {{ (int) config('edgetinker.home.slide_interval_ms') }} })"
                 class="on-dark relative overflow-hidden bg-primary-900"
                 aria-roledescription="carousel" aria-label="{{ __('site.home.slideshow_label') }}"
                 @mouseenter="setHovered(true)" @mouseleave="setHovered(false)"
                 @focusin="setFocused(true)" @focusout="setFocused(false)"
                 @keydown.arrow-left="previous()" @keydown.arrow-right="next()"
                 @touchstart.passive="onTouchStart($event)" @touchend.passive="onTouchEnd($event)">

            <div class="relative h-[30rem] sm:h-[32rem] lg:h-[36rem]" :aria-live="playing ? 'off' : 'polite'">
                @foreach ($slides as $index => $slide)
                    <div x-show="current === {{ $index }}" @if (! $loop->first) x-cloak @endif
                         x-transition:enter="transition-opacity duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity duration-700" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute inset-0" role="group" aria-roledescription="slide"
                         aria-label="{{ __('site.home.slide_of', ['current' => $index + 1, 'total' => $slides->count()]) }}">
                        @if ($slide->image_url)
                            <img src="{{ $slide->image_url }}" alt="{{ $slide->image_alt }}" width="1920" height="900"
                                 class="absolute inset-0 h-full w-full object-cover"
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif decoding="async">
                        @endif
                        {{-- Primary 900 overlay keeps the white text readable over any image. --}}
                        <div class="absolute inset-0 bg-linear-to-r from-primary-900 via-primary-900/90 to-primary-900/70 lg:via-primary-900/85 lg:to-primary-900/20" aria-hidden="true"></div>

                        <div class="container-page relative flex h-full items-center pb-16">
                            <div class="max-w-xl">
                                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">{{ $slide->heading }}</h2>
                                @if ($slide->sub_text)
                                    <p class="mt-4 text-lg text-primary-100 sm:text-xl">{{ $slide->sub_text }}</p>
                                @endif
                                @if ($slide->button_label && $slide->button_url)
                                    <x-button :href="$slide->button_url" variant="inverse" size="lg" class="mt-8">
                                        {{ $slide->button_label }} <x-icon name="arrow-right" />
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($slides->count() > 1)
                @php $control = 'inline-flex h-11 w-11 items-center justify-center rounded-full border border-white/50 bg-primary-900/60 text-white transition-colors hover:bg-white/15'; @endphp
                <div class="absolute inset-x-0 bottom-5">
                    <div class="container-page flex items-center justify-between gap-4">
                        <div class="flex items-center gap-1">
                            @foreach ($slides as $index => $slide)
                                <button type="button" class="group inline-flex h-11 w-8 items-center justify-center" @click="goTo({{ $index }})"
                                        :aria-current="current === {{ $index }} ? 'true' : 'false'">
                                    <span class="h-2.5 rounded-full transition-all" :class="current === {{ $index }} ? 'w-6 bg-white' : 'w-2.5 bg-white/50 group-hover:bg-white/80'"></span>
                                    <span class="sr-only">{{ __('site.home.go_to_slide', ['number' => $index + 1]) }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-2">
                            {{-- Not shown when the visitor prefers reduced motion: the slideshow never auto-plays then. --}}
                            <button type="button" class="{{ $control }}" @click="togglePlay()" x-show="!reducedMotion">
                                <span x-show="playing"><x-icon name="pause" class="h-4 w-4" /></span>
                                <span x-show="!playing" x-cloak><x-icon name="play" class="h-4 w-4" /></span>
                                <span class="sr-only" x-text="playing ? @js(__('site.home.pause')) : @js(__('site.home.play'))">{{ __('site.home.pause') }}</span>
                            </button>
                            <button type="button" class="{{ $control }}" @click="previous()">
                                <x-icon name="chevron-left" /><span class="sr-only">{{ __('site.home.previous_slide') }}</span>
                            </button>
                            <button type="button" class="{{ $control }}" @click="next()">
                                <x-icon name="chevron-right" /><span class="sr-only">{{ __('site.home.next_slide') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </section>
    @endif

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
</x-layouts.site>
