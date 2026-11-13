{{-- Global footer. $navCategories is provided by a view composer (AppServiceProvider).
     Deliberately contains no link to Content Studio. --}}
@php
    $heading = 'text-sm font-semibold uppercase tracking-wider text-primary-200';
    $link = 'inline-flex min-h-9 items-center rounded-control text-base text-primary-100 underline-offset-4 hover:text-white hover:underline';
    $socials = collect(['linkedin' => 'LinkedIn', 'github' => 'GitHub', 'youtube' => 'YouTube', 'facebook' => 'Facebook'])
        ->map(fn ($label, $key) => ['label' => $label, 'icon' => $key, 'url' => setting("social.$key")])
        ->filter(fn ($social) => filled($social['url']));
    $email = setting('contact.email');
    $phone = setting('contact.phone');
    $emailIsReal = ! app(\App\Support\Settings::class)->isPlaceholder('contact.email');
    $phoneIsReal = ! app(\App\Support\Settings::class)->isPlaceholder('contact.phone');
@endphp
<footer class="on-dark bg-primary-900 text-white">
    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.3fr]">
        <div>
            <x-site.logo dark />
            <p class="mt-3 max-w-xs text-base text-primary-100">{{ setting('site.tagline') }}</p>

            @if ($socials->isNotEmpty())
                <h2 class="{{ $heading }} mt-6">{{ __('site.footer.follow') }}</h2>
                <ul class="mt-2 flex gap-2">
                    @foreach ($socials as $social)
                        <li>
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener" class="inline-flex h-11 w-11 items-center justify-center rounded-control text-primary-100 hover:bg-white/10 hover:text-white">
                                <x-icon :name="$social['icon']" /><span class="sr-only">{{ $social['label'] }} {{ __('site.nav.new_tab') }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <nav aria-labelledby="footer-products">
            <h2 id="footer-products" class="{{ $heading }}">{{ __('site.footer.products') }}</h2>
            <ul class="mt-3 flex flex-col">
                <li><a href="{{ route('products.index') }}" class="{{ $link }}">{{ __('site.nav.all_categories') }}</a></li>
                @foreach ($navCategories as $category)
                    <li><a href="{{ route('products.category', $category->slug) }}" class="{{ $link }}">{{ $category->name }}</a></li>
                @endforeach
            </ul>
        </nav>

        <nav aria-labelledby="footer-company">
            <h2 id="footer-company" class="{{ $heading }}">{{ __('site.footer.company') }}</h2>
            <ul class="mt-3 flex flex-col">
                <li><a href="{{ route('home') }}" class="{{ $link }}">{{ __('site.nav.home') }}</a></li>
                <li><a href="{{ route('services') }}" class="{{ $link }}">{{ __('site.nav.services') }}</a></li>
                <li>
                    <a href="{{ route('wiki.index') }}" target="_blank" rel="noopener" class="{{ $link }} gap-1.5">
                        {{ __('site.nav.wiki') }}<x-icon name="external" class="h-3.5 w-3.5" /><span class="sr-only">{{ __('site.nav.new_tab') }}</span>
                    </a>
                </li>
                <li><a href="{{ route('contact') }}" class="{{ $link }}">{{ __('site.nav.contact') }}</a></li>
            </ul>
        </nav>

        <div>
            <h2 class="{{ $heading }}">{{ __('site.footer.contact') }}</h2>
            <ul class="mt-3 flex flex-col gap-3 text-base text-primary-100">
                <li class="flex gap-3">
                    <x-icon name="map-pin" class="mt-0.5 text-primary-300" />
                    <span>{{ setting('contact.address') }}</span>
                </li>
                <li class="flex gap-3">
                    <x-icon name="mail" class="mt-0.5 text-primary-300" />
                    @if ($emailIsReal)
                        <a href="mailto:{{ $email }}" class="min-w-0 [overflow-wrap:anywhere] underline-offset-4 hover:text-white hover:underline">{{ $email }}</a>
                    @else
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ $email }}</span>
                    @endif
                </li>
                <li class="flex gap-3">
                    <x-icon name="phone" class="mt-0.5 text-primary-300" />
                    @if ($phoneIsReal)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="underline-offset-4 hover:text-white hover:underline">{{ $phone }}</a>
                    @else
                        <span>{{ $phone }}</span>
                    @endif
                </li>
            </ul>
        </div>
    </div>

    <div class="border-t border-white/15">
        <p class="container-page py-5 text-sm text-primary-200">
            &copy; {{ now()->year }} {{ setting('site.name') }}. {{ __('site.footer.rights') }}
        </p>
    </div>
</footer>
