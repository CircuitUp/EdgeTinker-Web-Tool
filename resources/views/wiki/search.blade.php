<x-layouts.wiki :title="__('site.search.submit')" :tree="$tree" noindex>
    <x-breadcrumb :items="[['label' => __('site.wiki.title'), 'url' => route('wiki.index')], ['label' => __('site.search.submit')]]" />

    <h1 class="mt-4 text-3xl font-bold tracking-tight text-neutral-900">
        {{ $query !== '' ? __('site.wiki.search_heading', ['query' => $query]) : __('site.wiki.search_heading_empty') }}
    </h1>

    @if ($query === '')
        <p class="mt-4 text-lg text-neutral-600">{{ __('site.wiki.search_prompt') }}</p>
    @else
        <p class="mt-2 text-base font-medium text-neutral-900" role="status">{{ trans_choice('site.wiki.search_count', $results->count(), ['count' => $results->count()]) }}</p>

        @if ($results->isEmpty())
            <x-empty-state icon="search" class="mt-6" :title="__('site.listing.empty_title')">
                {{ __('site.wiki.search_empty', ['query' => $query]) }}
                <x-slot:action><x-button :href="route('wiki.index')" variant="secondary">{{ __('site.wiki.open_contents') }}</x-button></x-slot:action>
            </x-empty-state>
        @else
            <ul class="mt-6 flex flex-col divide-y divide-neutral-200 border-y border-neutral-200">
                @foreach ($results as $result)
                    <li class="py-4">
                        <p class="text-sm text-neutral-600">{{ $result->product->category->name }} · {{ $result->product->name }}</p>
                        <h2 class="text-lg font-semibold">
                            <a href="{{ $result->url() }}" class="text-primary-700 underline-offset-2 hover:underline">{{ $result->title }}</a>
                        </h2>
                        <p class="mt-1 text-base text-neutral-600">{{ \App\Support\WikiMarkdown::plainText($result->body) }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-layouts.wiki>
