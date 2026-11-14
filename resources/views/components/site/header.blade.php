{{-- Global header. $navCategories is provided by a view composer (AppServiceProvider). --}}
@php
    $linkBase = 'relative inline-flex h-16 items-center gap-1 px-3 text-base font-medium transition-colors hover:text-primary-700';
    $isActive = fn (string ...$patterns) => request()->routeIs(...$patterns);
    $linkState = fn (bool $active) => $active
        ? 'text-primary-700 after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-primary-600'
        : 'text-neutral-900';

    // First mega-menu entry is "All Categories"; the rest come from Content Studio.
    $megaItems = collect([[
        'label' => __('site.nav.all_categories'),
        'url' => route('products.index'),
        'tagline' => __('site.nav.all_categories_tagline'),
        'image' => $navCategories->first()?->image_url,
    ]])->concat($navCategories->map(fn ($category) => [
        'label' => $category->name,
        'url' => route('products.category', $category->slug),
        'tagline' => $category->tagline,
        'image' => $category->image_url,
    ]));
@endphp

<header x-data="{ drawer: false }" class="sticky top-0 z-40 border-b border-neutral-200 bg-white">
    <div class="container-page flex h-16 items-center gap-2 lg:gap-6">
        <x-site.logo />

        {{-- Desktop navigation --}}
        <nav aria-label="{{ __('site.nav.main') }}" class="hidden lg:block">
            <ul class="flex items-center">
                <li>
                    <a href="{{ route('home') }}" class="{{ $linkBase }} {{ $linkState($isActive('home')) }}" @if ($isActive('home')) aria-current="page" @endif>{{ __('site.nav.home') }}</a>
                </li>

                {{-- Products mega menu. Kept position:static so the panel spans the full header width. --}}
                <li x-data="megaMenu" @mouseenter="hoverIn()" @mouseleave="hoverOut()" @keydown.escape="close(true)" @focusout="onFocusOut($event)">
                    <button type="button" x-ref="trigger" aria-controls="mega-products" :aria-expanded="open ? 'true' : 'false'" aria-expanded="false"
                            @click="toggle()" @keydown.arrow-down.prevent="openAndFocusFirst()"
                            class="{{ $linkBase }} {{ $linkState($isActive('products.*')) }}">
                        {{ __('site.nav.products') }}
                        <x-icon name="chevron-down" class="h-4 w-4 transition-transform" ::class="open && 'rotate-180'" />
                    </button>

                    <div id="mega-products" x-show="open" x-cloak
                         x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition duration-100 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute inset-x-0 top-full border-b border-neutral-200 bg-white shadow-overlay">
                        <div class="container-page grid grid-cols-[18rem_1fr] gap-8 py-6">
                            <ul x-ref="list" @keydown="onListKeydown($event)" class="flex flex-col gap-0.5">
                                @foreach ($megaItems as $index => $item)
                                    <li>
                                        <a href="{{ $item['url'] }}" data-mega-item
                                           @mouseenter="active = {{ $index }}" @focus="active = {{ $index }}"
                                           :class="active === {{ $index }} ? 'bg-primary-50 text-primary-700' : 'text-neutral-900'"
                                           class="flex min-h-11 items-center justify-between gap-3 rounded-control px-3 py-2 text-base font-medium {{ $loop->first ? 'border-b border-neutral-200 pb-3 mb-1.5' : '' }}">
                                            {{ $item['label'] }}
                                            <x-icon name="chevron-right" class="h-4 w-4 text-primary-600" x-show="active === {{ $index }}" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- Image panel: swaps to the highlighted category. Decorative, so hidden from screen readers. --}}
                            <div class="relative h-72 overflow-hidden rounded-card bg-primary-50" aria-hidden="true">
                                @foreach ($megaItems as $index => $item)
                                    <div x-show="active === {{ $index }}" @if (! $loop->first) x-cloak @endif
                                         x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                         class="absolute inset-0">
                                        @if ($item['image'])
                                            <img src="{{ $item['image'] }}" alt="" width="800" height="600" loading="lazy" decoding="async" class="h-full w-full object-cover">
                                        @endif
                                        <div class="absolute inset-x-0 bottom-0 bg-primary-900/90 px-5 py-4">
                                            <p class="text-lg font-semibold text-white">{{ $item['label'] }}</p>
                                            @if ($item['tagline'])
                                                <p class="text-sm text-primary-100">{{ $item['tagline'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </li>

                <li>
                    <a href="{{ route('services') }}" class="{{ $linkBase }} {{ $linkState($isActive('services')) }}" @if ($isActive('services')) aria-current="page" @endif>{{ __('site.nav.services') }}</a>
                </li>
                <li>
                    {{-- The wiki opens in a new tab. --}}
                    <a href="{{ route('wiki.index') }}" target="_blank" rel="noopener" class="{{ $linkBase }} text-neutral-900">
                        {{ __('site.nav.wiki') }}
                        <x-icon name="external" class="h-3.5 w-3.5 text-neutral-500" />
                        <span class="sr-only">{{ __('site.nav.new_tab') }}</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('contact') }}" class="{{ $linkBase }} {{ $linkState($isActive('contact')) }}" @if ($isActive('contact')) aria-current="page" @endif>{{ __('site.nav.contact') }}</a>
                </li>
            </ul>
        </nav>

        <div class="flex flex-1 items-center justify-end gap-2 lg:gap-3">
            <x-site.search-form id="site-search" class="hidden w-52 lg:block xl:w-72" />
            <x-site.currency-toggle />

            <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control text-neutral-900 hover:bg-neutral-100 lg:hidden"
                    @click="drawer = true" :aria-expanded="drawer ? 'true' : 'false'" aria-expanded="false" aria-controls="mobile-drawer">
                <x-icon name="menu" class="h-6 w-6" /><span class="sr-only">{{ __('site.nav.open_menu') }}</span>
            </button>
        </div>
    </div>

    {{-- Mobile: search sits in its own row under the bar. --}}
    <div class="container-page pb-3 lg:hidden">
        <x-site.search-form id="site-search-mobile" />
    </div>

    {{-- Mobile drawer (replaces the mega menu below 1024 px). --}}
    <div id="mobile-drawer" x-show="drawer" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('site.nav.main') }}"
         @keydown.escape.window="drawer = false">
        <div class="absolute inset-0 bg-neutral-900/60" x-show="drawer" x-transition.opacity @click="drawer = false" aria-hidden="true"></div>

        <div x-show="drawer" x-trap.inert.noscroll="drawer"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             class="absolute inset-y-0 right-0 flex w-80 max-w-[88vw] flex-col bg-white shadow-overlay">
            <div class="flex h-16 items-center justify-between border-b border-neutral-200 px-4">
                <x-site.logo />
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control text-neutral-900 hover:bg-neutral-100" @click="drawer = false">
                    <x-icon name="x" class="h-6 w-6" /><span class="sr-only">{{ __('site.nav.close_menu') }}</span>
                </button>
            </div>

            <nav aria-label="{{ __('site.nav.main') }}" class="flex-1 overflow-y-auto px-2 py-3" x-data="{ products: {{ $isActive('products.*') ? 'true' : 'false' }} }">
                @php $drawerLink = 'flex min-h-12 items-center justify-between gap-2 rounded-control px-3 text-lg font-medium text-neutral-900 hover:bg-primary-50 hover:text-primary-700'; @endphp
                <ul class="flex flex-col">
                    <li><a href="{{ route('home') }}" class="{{ $drawerLink }}" @if ($isActive('home')) aria-current="page" @endif>{{ __('site.nav.home') }}</a></li>
                    <li>
                        <button type="button" class="{{ $drawerLink }} w-full" @click="products = !products" :aria-expanded="products ? 'true' : 'false'" aria-controls="drawer-products">
                            {{ __('site.nav.products') }}
                            <x-icon name="chevron-down" class="transition-transform" ::class="products && 'rotate-180'" />
                        </button>
                        <ul id="drawer-products" x-show="products" x-collapse x-cloak class="ml-3 border-l border-neutral-200 pl-2">
                            @foreach ($megaItems as $item)
                                <li><a href="{{ $item['url'] }}" class="flex min-h-11 items-center rounded-control px-3 text-base text-neutral-700 hover:bg-primary-50 hover:text-primary-700">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                    <li><a href="{{ route('services') }}" class="{{ $drawerLink }}" @if ($isActive('services')) aria-current="page" @endif>{{ __('site.nav.services') }}</a></li>
                    <li>
                        <a href="{{ route('wiki.index') }}" target="_blank" rel="noopener" class="{{ $drawerLink }}">
                            <span>{{ __('site.nav.wiki') }} <span class="sr-only">{{ __('site.nav.new_tab') }}</span></span>
                            <x-icon name="external" class="h-4 w-4 text-neutral-500" />
                        </a>
                    </li>
                    <li><a href="{{ route('contact') }}" class="{{ $drawerLink }}" @if ($isActive('contact')) aria-current="page" @endif>{{ __('site.nav.contact') }}</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>
