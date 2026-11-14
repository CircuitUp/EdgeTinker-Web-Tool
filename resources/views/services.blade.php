{{-- Services page. All text comes from Content Studio → Pages → Services ($content). --}}
<x-layouts.site :title="($seo['title'] ?? null) ?: $content['heading']" :description="($seo['description'] ?? null) ?: Str::limit($content['intro'], 155)" :preview="$isPreview ?? false">
    <div class="border-b border-neutral-200 bg-primary-50">
        <div class="container-page py-8 sm:py-12">
            <x-breadcrumb :items="[['label' => __('site.nav.home'), 'url' => route('home')], ['label' => __('site.nav.services')]]" />
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl lg:text-5xl">{{ $content['heading'] }}</h1>
            <p class="mt-4 max-w-3xl text-lg text-neutral-600 sm:text-xl">{{ $content['intro'] }}</p>
        </div>
    </div>

    {{-- One card per service --}}
    <section class="py-12 sm:py-16" aria-label="{{ __('site.nav.services') }}">
        <div class="container-page">
            <ul class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($content['services'] as $service)
                    <li class="flex flex-col rounded-card border border-neutral-200 bg-white p-6 shadow-card">
                        <span class="flex h-12 w-12 items-center justify-center rounded-card bg-primary-100 text-primary-700">
                            <x-icon :name="$service['icon']" class="h-6 w-6" />
                        </span>
                        <h2 class="mt-4 text-xl font-semibold text-neutral-900">{{ $service['title'] }}</h2>
                        <p class="mt-2 text-base text-neutral-600">{{ $service['text'] }}</p>

                        @if (! empty($service['deliverables']))
                            <h3 class="mt-5 text-sm font-semibold uppercase tracking-wider text-neutral-600">{{ __('site.services.deliverables') }}</h3>
                            <ul class="mt-2 flex flex-col gap-1.5">
                                @foreach ($service['deliverables'] as $deliverable)
                                    <li class="flex gap-2 text-base text-neutral-700">
                                        <x-icon name="check" class="mt-0.5 h-5 w-5 text-primary-600" /><span>{{ $deliverable }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="mt-auto pt-6">
                            <a href="{{ route('contact', ['subject' => __('site.services.enquiry_subject', ['service' => $service['title']])]) }}"
                               class="inline-flex min-h-11 items-center gap-1.5 text-base font-semibold text-primary-700 underline-offset-4 hover:underline">
                                {{ __('site.services.enquire_about', ['service' => $service['title']]) }} <x-icon name="arrow-right" class="h-4 w-4" />
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How we work --}}
    @if (! empty($content['process']))
        <section class="bg-neutral-50 py-12 sm:py-16" aria-labelledby="services-process">
            <div class="container-page">
                <x-section-heading id="services-process" :title="$content['process_heading']" />
                <ol class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($content['process'] as $step)
                        <li class="rounded-card border border-neutral-200 bg-white p-5">
                            <p class="flex items-center gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-600 font-mono text-lg font-semibold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                                <span class="sr-only">{{ __('site.services.step', ['number' => $loop->iteration]) }}:</span>
                                <span class="text-lg font-semibold text-neutral-900">{{ $step['title'] }}</span>
                            </p>
                            <p class="mt-3 text-base text-neutral-600">{{ $step['text'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Enquiry --}}
    <section class="py-12 sm:py-16" aria-labelledby="services-enquiry">
        <div class="container-page">
            <div class="on-dark flex flex-col items-start gap-6 rounded-panel bg-primary-900 px-6 py-10 sm:px-10 lg:flex-row lg:items-center lg:justify-between lg:py-12">
                <div class="max-w-2xl">
                    <h2 id="services-enquiry" class="text-2xl font-bold tracking-tight text-white sm:text-3xl">{{ $content['cta_heading'] }}</h2>
                    @if ($content['cta_text'])
                        <p class="mt-2 text-lg text-primary-100">{{ $content['cta_text'] }}</p>
                    @endif
                </div>
                <x-button :href="route('contact', ['subject' => __('site.services.general_subject')])" variant="inverse" size="lg" class="shrink-0">
                    <x-icon name="mail" /> {{ $content['cta_label'] }}
                </x-button>
            </div>
        </div>
    </section>
</x-layouts.site>
