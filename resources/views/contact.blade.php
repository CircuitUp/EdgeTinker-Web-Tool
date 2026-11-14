{{-- Contact page. Text: Content Studio → Pages → Contact. Address, email, phone, map: Site settings. --}}
@php
    $settings = app(\App\Support\Settings::class);
    $email = setting('contact.email');
    $phone = setting('contact.phone');
    $mapUrl = setting('contact.map_embed_url');
    $sent = session('contact_sent', false);
    $field = 'block w-full rounded-control border bg-white px-3 py-2 text-base text-neutral-900 placeholder:text-neutral-500';
    $state = fn (string $name) => $errors->has($name) ? 'border-danger' : 'border-neutral-300 hover:border-neutral-400';
@endphp
<x-layouts.site :title="($seo['title'] ?? null) ?: __('site.nav.contact')" :description="($seo['description'] ?? null) ?: Str::limit($content['intro'], 155)" :preview="$isPreview ?? false">
    <div class="border-b border-neutral-200 bg-primary-50">
        <div class="container-page py-8 sm:py-12">
            <x-breadcrumb :items="[['label' => __('site.nav.home'), 'url' => route('home')], ['label' => __('site.nav.contact')]]" />
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ $content['heading'] }}</h1>
            @if ($content['intro'])
                <p class="mt-3 max-w-3xl text-lg text-neutral-600">{{ $content['intro'] }}</p>
            @endif
        </div>
    </div>

    <div class="container-page grid gap-10 py-10 lg:grid-cols-[3fr_2fr] lg:gap-16 lg:py-14">
        {{-- Form --}}
        <section aria-labelledby="contact-form-heading">
            <h2 id="contact-form-heading" class="text-2xl font-bold tracking-tight text-neutral-900">{{ $content['form_heading'] }}</h2>

            @if ($sent)
                {{-- data-focus-on-load: focus moves here so keyboard and screen-reader users get the result straight away (app.js). --}}
                <div class="mt-5 flex gap-3 rounded-card border border-success/30 bg-success-50 px-4 py-4" role="status" tabindex="-1" id="contact-result" data-focus-on-load>
                    <x-icon name="check-circle" class="mt-0.5 text-success" />
                    <p class="text-base font-medium text-neutral-900">{{ $content['success_message'] }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 flex gap-3 rounded-card border border-danger/30 bg-danger-50 px-4 py-4" role="alert" tabindex="-1" id="contact-result" data-focus-on-load>
                    <x-icon name="alert" class="mt-0.5 text-danger" />
                    <p class="text-base font-medium text-neutral-900">{{ __('site.contact.error_summary') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('contact.send') }}" class="mt-6 grid gap-5 sm:grid-cols-2" novalidate>
                @csrf
                <p class="text-sm text-neutral-600 sm:col-span-2">{{ __('site.contact.required_note') }}</p>

                <x-form.field :label="__('site.contact.name')" for="name" required :error="$errors->first('name')">
                    <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required maxlength="120"
                           aria-describedby="name-error" @error('name') aria-invalid="true" @enderror class="{{ $field }} min-h-11 {{ $state('name') }}">
                </x-form.field>

                <x-form.field :label="__('site.contact.email')" for="email" required :error="$errors->first('email')">
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255"
                           aria-describedby="email-error" @error('email') aria-invalid="true" @enderror class="{{ $field }} min-h-11 {{ $state('email') }}">
                </x-form.field>

                <x-form.field :label="__('site.contact.company').' ('.__('site.contact.optional').')'" for="company" :error="$errors->first('company')">
                    <input id="company" name="company" type="text" value="{{ old('company') }}" autocomplete="organization" maxlength="150"
                           class="{{ $field }} min-h-11 {{ $state('company') }}">
                </x-form.field>

                <x-form.field :label="__('site.contact.subject')" for="subject" required :error="$errors->first('subject')">
                    <input id="subject" name="subject" type="text" value="{{ old('subject', $subject ?? '') }}" required maxlength="150"
                           aria-describedby="subject-error" @error('subject') aria-invalid="true" @enderror class="{{ $field }} min-h-11 {{ $state('subject') }}">
                </x-form.field>

                <x-form.field :label="__('site.contact.message')" for="message" required :error="$errors->first('message')" :hint="__('site.contact.message_hint')" class="sm:col-span-2">
                    <textarea id="message" name="message" rows="7" required maxlength="5000" aria-describedby="message-hint message-error"
                              @error('message') aria-invalid="true" @enderror class="{{ $field }} {{ $state('message') }}">{{ old('message') }}</textarea>
                </x-form.field>

                {{-- Honeypot: hidden from people and assistive technology; automated form-fillers tend to complete it. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('site.contact.honeypot') }}</label>
                    <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="sm:col-span-2">
                    <x-button type="submit" size="lg" :disabled="$isPreview ?? false"><x-icon name="mail" /> {{ __('site.contact.send') }}</x-button>
                </div>
            </form>
        </section>

        {{-- Details and map --}}
        <section aria-labelledby="contact-details-heading">
            <h2 id="contact-details-heading" class="text-2xl font-bold tracking-tight text-neutral-900">{{ $content['details_heading'] }}</h2>

            <dl class="mt-6 flex flex-col gap-5">
                <div class="flex gap-4">
                    <dt class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-primary-100 text-primary-700"><x-icon name="map-pin" /><span class="sr-only">{{ __('site.contact.address') }}</span></dt>
                    <dd class="whitespace-pre-line pt-2 text-base text-neutral-900">{{ setting('contact.address') }}</dd>
                </div>
                <div class="flex gap-4">
                    <dt class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-primary-100 text-primary-700"><x-icon name="mail" /><span class="sr-only">{{ __('site.contact.email_label') }}</span></dt>
                    <dd class="min-w-0 pt-2 text-base text-neutral-900 [overflow-wrap:anywhere]">
                        @if ($settings->isPlaceholder('contact.email'))
                            {{ $email }}
                        @else
                            <a href="mailto:{{ $email }}" class="font-medium text-primary-700 underline underline-offset-2 hover:text-primary-800">{{ $email }}</a>
                        @endif
                    </dd>
                </div>
                <div class="flex gap-4">
                    <dt class="flex h-11 w-11 shrink-0 items-center justify-center rounded-card bg-primary-100 text-primary-700"><x-icon name="phone" /><span class="sr-only">{{ __('site.contact.phone') }}</span></dt>
                    <dd class="pt-2 text-base text-neutral-900">
                        @if ($settings->isPlaceholder('contact.phone'))
                            {{ $phone }}
                        @else
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-medium text-primary-700 underline underline-offset-2 hover:text-primary-800">{{ $phone }}</a>
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="mt-8 overflow-hidden rounded-card border border-neutral-200">
                @if ($mapUrl)
                    <iframe src="{{ $mapUrl }}" title="{{ __('site.contact.map_title') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            class="block h-72 w-full border-0"></iframe>
                @else
                    <div class="flex h-56 items-center justify-center bg-neutral-50 px-6 text-center text-base text-neutral-600">
                        {{ __('site.contact.map_placeholder') }}
                    </div>
                @endif
            </div>
        </section>
    </div>

</x-layouts.site>
