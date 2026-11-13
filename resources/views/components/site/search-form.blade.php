@props(['id' => 'site-search'])
{{-- Header search. Submits to the search results page (same tab). Instant suggestions are added in Phase 2. --}}
<form action="{{ route('search') }}" method="GET" role="search" {{ $attributes->merge(['class' => 'relative']) }}>
    <label for="{{ $id }}" class="sr-only">{{ __('site.search.label') }}</label>
    <input id="{{ $id }}" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
           placeholder="{{ __('site.search.placeholder') }}" autocomplete="off" maxlength="100"
           class="block min-h-11 w-full rounded-control border border-neutral-300 bg-white py-2 pl-3 pr-11 text-base text-neutral-900 placeholder:text-neutral-500 hover:border-neutral-400">
    <button type="submit" class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-control text-neutral-600 hover:text-primary-700">
        <x-icon name="search" /><span class="sr-only">{{ __('site.search.submit') }}</span>
    </button>
</form>
