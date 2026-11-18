@props(['title' => null, 'description' => null, 'tree' => [], 'current' => null, 'preview' => false, 'noindex' => false])
{{--
    Wiki layout: Primary 900 top bar (search + link back to the main site), a collapsible
    tree on the left (category → product → pages), the article in the centre and, on wide
    screens, an "On this page" list on the right (passed in as the `aside` slot).
    $current = ['category' => slug, 'product' => slug, 'page' => slug] of the page being read.
--}}
@php
    $siteName = setting('site.name');
    $fullTitle = ($title ? "$title | " : '').__('site.wiki.title').' | '.$siteName;
    // The search term shown back in the box. A crafted ?q[]=… must not reach the template as an array.
    $searchTerm = request()->routeIs('wiki.search') && is_string(request('q')) ? request('q') : '';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description ?? __('site.wiki.tagline') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @include('components.favicons')
    <meta property="og:site_name" content="{{ $siteName }} {{ __('site.wiki.title') }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $title ?: __('site.wiki.title') }}">
    <meta property="og:description" content="{{ $description ?? __('site.wiki.tagline') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    @if ($preview || $noindex)
        <meta name="robots" content="noindex, follow">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="flex min-h-screen flex-col bg-white text-neutral-900" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-control focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-primary-900">
        {{ __('site.nav.skip') }}
    </a>

    @if ($preview)
        <div class="bg-accent px-4 py-2 text-center text-base font-semibold text-neutral-900" role="status">
            Preview only. This is how the page would look if the change were approved. It is not on the live site.
        </div>
    @endif

    <header class="on-dark sticky top-0 z-40 bg-primary-900 text-white">
        <div class="mx-auto flex h-16 w-full max-w-[96rem] items-center gap-3 px-4 sm:px-6">
            <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-control hover:bg-white/10 lg:hidden"
                    @click="nav = true" aria-controls="wiki-nav" :aria-expanded="nav ? 'true' : 'false'">
                <x-icon name="menu" class="h-6 w-6" /><span class="sr-only">{{ __('site.wiki.open_contents') }}</span>
            </button>

            <a href="{{ route('wiki.index') }}" class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-control text-xl font-bold tracking-tight text-white">
                <span>Edge<span class="text-primary-300">Tinker</span></span>
                <span class="rounded-control bg-white/15 px-2 py-0.5 text-sm font-semibold uppercase tracking-wider">{{ __('site.wiki.title') }}</span>
            </a>

            <form action="{{ route('wiki.search') }}" method="GET" role="search" class="relative ml-auto hidden w-full max-w-md sm:block">
                <label for="wiki-search" class="sr-only">{{ __('site.wiki.search_label') }}</label>
                <input id="wiki-search" type="search" name="q" value="{{ $searchTerm }}" maxlength="100"
                       placeholder="{{ __('site.wiki.search_placeholder') }}"
                       class="block min-h-11 w-full rounded-control border border-white/30 bg-white/10 py-2 pl-3 pr-11 text-base text-white placeholder:text-primary-200 hover:border-white/60 focus:bg-white focus:text-neutral-900 focus:placeholder:text-neutral-500">
                <button type="submit" class="absolute right-0 top-0 inline-flex h-11 w-11 items-center justify-center rounded-control text-primary-100 hover:text-white">
                    <x-icon name="search" /><span class="sr-only">{{ __('site.search.submit') }}</span>
                </button>
            </form>

            <a href="{{ route('home') }}" class="ml-auto inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-control px-2 text-base font-medium text-primary-100 underline-offset-4 hover:text-white hover:underline sm:ml-0">
                <x-icon name="chevron-left" class="h-4 w-4" /><span class="hidden sm:inline">{{ __('site.wiki.back_to_site') }}</span><span class="sm:hidden">{{ __('site.nav.home') }}</span>
            </a>
        </div>

        {{-- Search gets its own row on small screens. --}}
        <form action="{{ route('wiki.search') }}" method="GET" role="search" class="relative px-4 pb-3 sm:hidden">
            <label for="wiki-search-mobile" class="sr-only">{{ __('site.wiki.search_label') }}</label>
            <input id="wiki-search-mobile" type="search" name="q" value="{{ $searchTerm }}" maxlength="100"
                   placeholder="{{ __('site.wiki.search_placeholder') }}"
                   class="block min-h-11 w-full rounded-control border border-white/30 bg-white/10 py-2 pl-3 pr-11 text-base text-white placeholder:text-primary-200 focus:bg-white focus:text-neutral-900 focus:placeholder:text-neutral-500">
            <button type="submit" class="absolute right-4 top-0 inline-flex h-11 w-11 items-center justify-center text-primary-100">
                <x-icon name="search" /><span class="sr-only">{{ __('site.search.submit') }}</span>
            </button>
        </form>
    </header>

    <div x-show="nav" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-neutral-900/60 lg:hidden" @click="nav = false" aria-hidden="true"></div>

    <div class="mx-auto flex w-full max-w-[96rem] flex-1">
        {{-- Left: wiki tree --}}
        <nav id="wiki-nav" aria-label="{{ __('site.wiki.contents') }}" x-trap.noscroll="nav"
             :class="nav ? 'max-lg:translate-x-0! max-lg:visible!' : ''"
             class="w-72 shrink-0 border-r border-neutral-200 bg-white max-lg:invisible max-lg:fixed max-lg:inset-y-0 max-lg:left-0 max-lg:z-50 max-lg:max-w-[88vw] max-lg:-translate-x-full max-lg:overflow-y-auto max-lg:shadow-overlay max-lg:transition-[transform,visibility] max-lg:duration-200">
            <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 lg:hidden">
                <p class="text-lg font-semibold">{{ __('site.wiki.contents') }}</p>
                <button type="button" class="inline-flex h-11 w-11 items-center justify-center rounded-control hover:bg-neutral-100" @click="nav = false">
                    <x-icon name="x" class="h-6 w-6" /><span class="sr-only">{{ __('site.wiki.close_contents') }}</span>
                </button>
            </div>

            <div class="p-3 lg:sticky lg:top-16 lg:max-h-[calc(100vh-4rem)] lg:overflow-y-auto">
                @forelse ($tree as $category)
                    @php $categoryOpen = ($current['category'] ?? null) === $category['slug'] || $current === null; @endphp
                    <details class="group/category" @if ($categoryOpen) open @endif>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 rounded-control px-3 text-sm font-semibold uppercase tracking-wider text-neutral-600 hover:bg-neutral-50 [&::-webkit-details-marker]:hidden">
                            {{ $category['name'] }}
                            <x-icon name="chevron-down" class="h-4 w-4 transition-transform group-open/category:rotate-180" />
                        </summary>
                        <ul class="mb-2 flex flex-col">
                            @foreach ($category['products'] as $product)
                                @php $productOpen = $categoryOpen && ($current['product'] ?? null) === $product['slug']; @endphp
                                <li>
                                    <details class="group/product" @if ($productOpen) open @endif>
                                        <summary class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-2 rounded-control px-3 text-base font-medium hover:bg-primary-50 [&::-webkit-details-marker]:hidden {{ $productOpen ? 'text-primary-700' : 'text-neutral-900' }}">
                                            <span class="min-w-0 truncate">{{ $product['name'] }}</span>
                                            <x-icon name="chevron-right" class="h-4 w-4 shrink-0 transition-transform group-open/product:rotate-90" />
                                        </summary>
                                        <ul class="ml-3 flex flex-col border-l border-neutral-200 py-1 pl-2">
                                            @foreach ($product['pages'] as $item)
                                                @php $active = $productOpen && ($current['page'] ?? null) === $item['slug']; @endphp
                                                <li>
                                                    <a href="{{ route('wiki.show', [$category['slug'], $product['slug'], $item['slug']]) }}" @if ($active) aria-current="page" @endif
                                                       class="flex min-h-9 items-center rounded-control px-3 text-base {{ $active ? 'bg-primary-100 font-semibold text-primary-800' : 'text-neutral-700 hover:bg-primary-50 hover:text-primary-700' }}">
                                                        {{ $item['title'] }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @empty
                    <p class="px-3 py-2 text-base text-neutral-600">{{ __('site.wiki.empty_text') }}</p>
                @endforelse
            </div>
        </nav>

        {{-- Centre: article --}}
        <main id="main" tabindex="-1" class="min-w-0 flex-1 px-4 py-8 outline-none sm:px-8 lg:px-12">
            <div class="mx-auto w-full max-w-3xl">{{ $slot }}</div>
        </main>

        {{-- Right: "On this page" --}}
        @isset($aside)
            <aside class="hidden w-64 shrink-0 xl:block">
                <div class="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto px-4 py-8">{{ $aside }}</div>
            </aside>
        @endisset
    </div>

    <footer class="border-t border-neutral-200 bg-neutral-50">
        <p class="mx-auto w-full max-w-[96rem] px-4 py-5 text-sm text-neutral-600 sm:px-6">
            &copy; {{ now()->year }} {{ $siteName }} ·
            <a href="{{ route('home') }}" class="font-medium text-primary-700 underline-offset-2 hover:underline">{{ __('site.wiki.back_to_site') }}</a>
        </p>
    </footer>
</body>
</html>
