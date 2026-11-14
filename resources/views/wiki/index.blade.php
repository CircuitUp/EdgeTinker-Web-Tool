<x-layouts.wiki :tree="$tree">
    <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ setting('site.name') }} {{ __('site.wiki.title') }}</h1>
    <p class="mt-3 text-lg text-neutral-600">{{ __('site.wiki.tagline') }}</p>

    @if (empty($tree))
        <x-empty-state icon="book" class="mt-8" :title="__('site.wiki.empty_title')">{{ __('site.wiki.empty_text') }}</x-empty-state>
    @else
        @foreach ($tree as $category)
            <section class="mt-10" aria-labelledby="wiki-category-{{ $category['slug'] }}">
                <h2 id="wiki-category-{{ $category['slug'] }}" class="border-b border-neutral-200 pb-2 text-xl font-semibold text-neutral-900">{{ $category['name'] }}</h2>
                <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($category['products'] as $product)
                        <li class="rounded-card border border-neutral-200 bg-white p-4 shadow-card">
                            <h3 class="text-lg font-semibold">
                                <a href="{{ route('wiki.product', [$category['slug'], $product['slug']]) }}" class="text-neutral-900 underline-offset-2 hover:text-primary-700 hover:underline">{{ $product['name'] }}</a>
                            </h3>
                            <p class="text-sm text-neutral-600">{{ trans_choice('site.wiki.pages_count', count($product['pages']), ['count' => count($product['pages'])]) }}</p>
                            <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
                                @foreach ($product['pages'] as $item)
                                    <li>
                                        <a href="{{ route('wiki.show', [$category['slug'], $product['slug'], $item['slug']]) }}"
                                           class="inline-flex min-h-8 items-center text-base font-medium text-primary-700 underline-offset-2 hover:underline">{{ $item['title'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</x-layouts.wiki>
