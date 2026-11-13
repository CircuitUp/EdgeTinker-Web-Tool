{{-- Temporary page for sections built in later phases. Removed as each phase lands. --}}
<x-layouts.site :title="$title">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <section class="py-16 sm:py-24">
        <div class="container-page">
            <x-empty-state icon="info" :title="__('site.coming_soon.title', ['page' => $title])" class="mx-auto max-w-2xl">
                {{ __('site.coming_soon.text', ['phase' => $phase]) }}
                <x-slot:action>
                    <x-button :href="route('home')">{{ __('site.coming_soon.back') }}</x-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    </section>
</x-layouts.site>
