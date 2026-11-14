{{-- Friendly "page not found". Uses the normal site layout so visitors keep the menu and search. --}}
<x-layouts.site title="Page not found">
    <x-slot:head><meta name="robots" content="noindex"></x-slot:head>

    <section class="py-16 sm:py-24">
        <div class="container-page">
            <div class="mx-auto max-w-2xl text-center">
                <p class="font-mono text-base font-semibold text-primary-700">404</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">We could not find that page</h1>
                <p class="mt-4 text-lg text-neutral-600">
                    The address may be mistyped, or the page may have been moved or removed. Try searching for what you need, or start from one of these.
                </p>

                <x-site.search-form id="not-found-search" class="mx-auto mt-8 max-w-md text-left" />

                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <x-button :href="route('home')">Go to the home page</x-button>
                    <x-button :href="route('products.index')" variant="secondary">Browse products</x-button>
                    <x-button :href="route('contact')" variant="ghost">Contact us</x-button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
