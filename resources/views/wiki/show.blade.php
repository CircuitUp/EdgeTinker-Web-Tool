@php
    $category = $product->category;
    $current = ['category' => $category->slug, 'product' => $product->slug, 'page' => $page->slug];
    $pageLink = fn ($item) => route('wiki.show', [$category->slug, $product->slug, $item->slug]);
@endphp
<x-layouts.wiki :title="$page->title.' – '.$product->name" :description="\App\Support\WikiMarkdown::plainText($page->body, 155)"
                :tree="$tree" :current="$current" :preview="$isPreview ?? false">
    <x-breadcrumb :items="[
        ['label' => __('site.wiki.title'), 'url' => route('wiki.index')],
        ['label' => $category->name],
        ['label' => $product->name, 'url' => route('wiki.product', [$category->slug, $product->slug])],
        ['label' => $page->title],
    ]" />

    <article class="mt-5">
        <header class="border-b border-neutral-200 pb-5">
            <p class="text-sm font-semibold uppercase tracking-wider text-primary-700">{{ $product->name }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ $page->title }}</h1>
            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-neutral-600">
                @if ($page->updated_at)
                    <p>{{ __('site.wiki.last_updated', ['date' => $page->updated_at->format('j F Y')]) }}</p>
                @endif
                {{-- Every wiki page links back to its product page. --}}
                <a href="{{ route('products.show', $product->slug) }}" class="inline-flex min-h-9 items-center gap-1.5 font-semibold text-primary-700 underline-offset-2 hover:underline">
                    {{ __('site.wiki.product_page') }} <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </header>

        {{-- Rendered from Markdown by WikiMarkdown: raw HTML is escaped and unsafe links removed there. --}}
        <div class="rich-text wiki-content mt-6">{!! $rendered['html'] !!}</div>
    </article>

    @if ($previous || $next)
        <nav aria-label="Pages in this product" class="mt-10 grid gap-3 border-t border-neutral-200 pt-6 sm:grid-cols-2">
            @if ($previous)
                <a href="{{ $pageLink($previous) }}" rel="prev" class="group flex min-h-16 flex-col justify-center rounded-card border border-neutral-200 px-4 py-3 hover:border-primary-600">
                    <span class="flex items-center gap-1 text-sm text-neutral-600"><x-icon name="chevron-left" class="h-4 w-4" /> {{ __('site.wiki.previous') }}</span>
                    <span class="text-base font-semibold text-neutral-900 group-hover:text-primary-700">{{ $previous->title }}</span>
                </a>
            @else
                <span class="hidden sm:block"></span>
            @endif
            @if ($next)
                <a href="{{ $pageLink($next) }}" rel="next" class="group flex min-h-16 flex-col items-end justify-center rounded-card border border-neutral-200 px-4 py-3 text-right hover:border-primary-600">
                    <span class="flex items-center gap-1 text-sm text-neutral-600">{{ __('site.wiki.next') }} <x-icon name="chevron-right" class="h-4 w-4" /></span>
                    <span class="text-base font-semibold text-neutral-900 group-hover:text-primary-700">{{ $next->title }}</span>
                </a>
            @endif
        </nav>
    @endif

    @if (count($rendered['toc']) > 1)
        <x-slot:aside>
            <nav aria-label="{{ __('site.wiki.on_this_page') }}" x-data="tocSpy">
                <p class="text-sm font-semibold uppercase tracking-wider text-neutral-600">{{ __('site.wiki.on_this_page') }}</p>
                <ul class="mt-3 flex flex-col border-l border-neutral-200">
                    @foreach ($rendered['toc'] as $entry)
                        <li>
                            <a href="#{{ $entry['id'] }}"
                               :class="current === @js($entry['id']) ? 'border-primary-600 font-semibold text-primary-700' : 'border-transparent text-neutral-600 hover:text-neutral-900'"
                               :aria-current="current === @js($entry['id']) ? 'location' : null"
                               class="-ml-px flex min-h-8 items-center border-l-2 py-1 text-sm {{ $entry['level'] === 3 ? 'pl-7' : 'pl-4' }}">
                                {{ $entry['text'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </x-slot:aside>
    @endif
</x-layouts.wiki>
