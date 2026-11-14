@props(['id' => 'site-search'])
{{-- Header search. Enter opens the results page in the same tab; while typing, up to six
     matching products are suggested underneath (behaviour: resources/js/components/search-box.js). --}}
@php $current = request()->routeIs('search') && is_string(request('q')) ? request('q') : ''; @endphp
<form action="{{ route('search') }}" method="GET" role="search"
      x-data="searchBox({ url: @js(route('search.suggest')), term: @js($current) })"
      @keydown.escape="close()" @click.outside="close()" @focusout="if (! $el.contains($event.relatedTarget)) close()"
      {{ $attributes->merge(['class' => 'relative']) }}>
    <label for="{{ $id }}" class="sr-only">{{ __('site.search.label') }}</label>
    <input id="{{ $id }}" type="search" name="q" value="{{ $current }}" x-model="term"
           placeholder="{{ __('site.search.placeholder') }}" autocomplete="off" maxlength="100"
           role="combobox" aria-autocomplete="list" aria-controls="{{ $id }}-suggestions"
           aria-expanded="false" :aria-expanded="open ? 'true' : 'false'"
           :aria-activedescendant="active >= 0 ? '{{ $id }}-option-' + active : null"
           @input.debounce.200ms="fetchSuggestions()" @focus="reopen()"
           @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)"
           class="block min-h-11 w-full rounded-control border border-neutral-300 bg-white py-2 pl-3 pr-11 text-base text-neutral-900 placeholder:text-neutral-500 hover:border-neutral-400 [&::-webkit-search-cancel-button]:hidden">
    <button type="submit" class="absolute right-0 top-0 inline-flex h-11 w-11 items-center justify-center rounded-control text-neutral-600 hover:text-primary-700">
        <x-icon name="search" /><span class="sr-only">{{ __('site.search.submit') }}</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute right-0 top-full z-50 mt-1 w-full overflow-hidden rounded-card border border-neutral-200 bg-white shadow-overlay lg:min-w-96">
        <ul id="{{ $id }}-suggestions" role="listbox" aria-label="{{ __('site.search.suggestions') }}">
            <template x-for="(item, index) in items" :key="item.url">
                <li role="option" :id="'{{ $id }}-option-' + index" :aria-selected="active === index ? 'true' : 'false'">
                    <a :href="item.url" tabindex="-1" @mouseenter="active = index"
                       :class="active === index ? 'bg-primary-50' : ''"
                       class="flex min-h-14 items-center gap-3 px-3 py-2">
                        <span class="h-10 w-10 shrink-0 overflow-hidden rounded-control border border-neutral-200 bg-neutral-50">
                            <img :src="item.image" alt="" width="40" height="40" x-show="item.image" class="h-full w-full object-contain">
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-base font-medium text-neutral-900" x-text="item.name"></span>
                            <span class="block truncate font-mono text-sm text-neutral-600" x-text="item.sku"></span>
                        </span>
                        <span class="shrink-0 font-mono text-sm font-semibold text-neutral-900" x-text="item.price ?? ''"></span>
                    </a>
                </li>
            </template>
        </ul>
        <p x-show="! items.length" class="px-3 py-3 text-sm text-neutral-600">{{ __('site.search.no_suggestions') }}</p>
        <a :href="@js(route('search')) + '?q=' + encodeURIComponent(term.trim())" tabindex="-1" x-show="items.length"
           class="flex min-h-11 items-center gap-2 border-t border-neutral-200 bg-neutral-50 px-3 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50">
            <span class="truncate">{{ __('site.search.see_all') }} “<span x-text="term.trim()"></span>”</span>
            <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    </div>
</form>
